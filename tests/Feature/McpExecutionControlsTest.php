<?php

namespace Tests\Feature;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Execution\ConfirmationService;
use App\Mcp\Execution\ExecutionLedger;
use App\Models\McpConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpExecutionControlsTest extends TestCase
{
    use RefreshDatabase;

    private const USER_ID = 'd7537b93-f733-4c6f-bdb7-d75cacb8802a';

    public function test_confirmation_is_bound_to_user_digest_expiry_and_single_use(): void
    {
        config(['mcp.execution.confirmation_ttl_seconds' => 300]);
        $service = new ConfirmationService();
        $issued = $service->issue(self::USER_ID, str_repeat('a', 64), ['method' => 'PUT'], ['workflow' => 'test']);

        $confirmation = $service->approveAndConsume(
            self::USER_ID,
            $issued['confirmation_id'],
            str_repeat('a', 64),
        );

        $this->assertSame($issued['confirmation_id'], $confirmation->id);
        $this->assertNotNull($confirmation->consumed_at);

        try {
            $service->approveAndConsume(self::USER_ID, $issued['confirmation_id'], str_repeat('a', 64));
            $this->fail('Expected a used confirmation to be rejected.');
        } catch (McpException $exception) {
            $this->assertSame('CONFIRMATION_ALREADY_USED', $exception->errorCode);
        }
    }

    public function test_confirmation_rejects_changed_plan_and_expired_token(): void
    {
        $service = new ConfirmationService();
        $changed = $service->issue(self::USER_ID, str_repeat('a', 64), [], ['workflow' => 'test']);

        try {
            $service->approveAndConsume(self::USER_ID, $changed['confirmation_id'], str_repeat('b', 64));
            $this->fail('Expected a changed plan to be rejected.');
        } catch (McpException $exception) {
            $this->assertSame('CONFIRMATION_PLAN_CHANGED', $exception->errorCode);
        }

        $expired = $service->issue(self::USER_ID, str_repeat('c', 64), [], ['workflow' => 'test']);
        McpConfirmation::query()->whereKey($expired['confirmation_id'])->update(['expires_at' => now()->subSecond()]);

        try {
            $service->approveAndConsume(self::USER_ID, $expired['confirmation_id'], str_repeat('c', 64));
            $this->fail('Expected an expired confirmation to be rejected.');
        } catch (McpException $exception) {
            $this->assertSame('CONFIRMATION_EXPIRED', $exception->errorCode);
        }
    }

    public function test_execution_ledger_replays_same_operation_and_rejects_key_reuse(): void
    {
        $ledger = new ExecutionLedger();
        $audit = $this->audit(str_repeat('a', 64));

        $first = $ledger->reserve($audit);
        $second = $ledger->reserve($audit);

        $this->assertFalse($first['replayed']);
        $this->assertTrue($second['replayed']);
        $this->assertSame($first['execution']->id, $second['execution']->id);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('idempotency key was already used');
        $ledger->reserve($this->audit(str_repeat('b', 64)));
    }

    private function audit(string $digest): array
    {
        return [
            'user_id' => self::USER_ID,
            'workspace_id' => 'c730595e-9010-4b04-a90c-69edca895ac2',
            'collection_id' => '789a28c0-8094-4f7e-9d7c-ba6ba602caeb',
            'request_id' => '105b8b1a-cee9-4e95-81c7-0b9f879f2f60',
            'environment_id' => 'c951fa51-33d4-471a-b2bd-9749c96b2d27',
            'workflow_name' => 'issue_license',
            'workflow_version' => '1.0.0',
            'resolved_host' => 'uat.example.test',
            'method' => 'PUT',
            'autonomy_mode' => 'confirm_writes',
            'confirmation_id' => null,
            'confirmed_by' => null,
            'idempotency_key' => 'issue-license-1',
            'operation_digest' => $digest,
        ];
    }
}
