<?php

namespace Tests\Feature;

use App\Mcp\Execution\ConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpConfirmationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const USER_ID = 'd7537b93-f733-4c6f-bdb7-d75cacb8802a';
    private const WORKSPACE_ID = 'c730595e-9010-4b04-a90c-69edca895ac2';
    private const COLLECTION_ID = '789a28c0-8094-4f7e-9d7c-ba6ba602caeb';
    private const ENVIRONMENT_ID = 'c951fa51-33d4-471a-b2bd-9749c96b2d27';
    private const UPDATE_ID = '105b8b1a-cee9-4e95-81c7-0b9f879f2f60';
    private const LOOKUP_ID = 'a9758cea-ef5b-4527-aa2b-3140f36f4524';

    public function test_authenticated_approval_executes_and_replays_the_same_write(): void
    {
        config([
            'mcp.execution.enabled' => true,
            'mcp.execution.confirmation_ttl_seconds' => 300,
            'mcp.execution.berzuda_url' => 'https://requestlab.test/api/mcp/execute',
            'mcp.execution.signing_secret' => 'a-strong-shared-secret-with-32-characters',
            'mcp.execution.timeout_seconds' => 10,
            'mcp.supabase_url' => 'https://requestlab.supabase.co',
            'mcp.supabase_publishable_key' => 'publishable-test-key',
        ]);
        $digest = str_repeat('a', 64);
        $confirmation = (new ConfirmationService())->issue(
            self::USER_ID,
            $digest,
            ['method' => 'PUT', 'qr_link' => '[REDACTED]'],
            [
                'plan' => ['workflow' => ['name' => 'issue_license', 'version' => '1.0.0'], 'request_ids' => [self::LOOKUP_ID, self::UPDATE_ID]],
                'lookup_host' => 'uat.example.test',
                'update_request_id' => self::UPDATE_ID,
                'environment_id' => self::ENVIRONMENT_ID,
                'collection_id' => self::COLLECTION_ID,
                'workspace_id' => self::WORKSPACE_ID,
                'national_id' => '436415528',
                'qr_link' => 'https://qr.example.test/license',
                'reg_status' => 'Pending-Assessment',
                'update_definition_digest' => str_repeat('d', 64),
            ],
        );
        $gatewayCalls = 0;
        Http::fake(function (Request $request) use (&$gatewayCalls) {
            if (str_ends_with($request->url(), '/auth/v1/user')) {
                return Http::response(['id' => self::USER_ID, 'email' => 'user@example.test']);
            }
            if ($request->url() === 'https://requestlab.test/api/mcp/execute') {
                $gatewayCalls++;
                return Http::response([
                    'request_id' => self::UPDATE_ID,
                    'environment_id' => self::ENVIRONMENT_ID,
                    'resolved_host' => 'uat.example.test',
                    'method' => 'PUT',
                    'status' => 200,
                    'duration_ms' => 20,
                    'size_bytes' => 50,
                    'body' => '{"message":"updated"}',
                ]);
            }
            return Http::response([], 404);
        });
        $body = ['plan_digest' => $digest, 'idempotency_key' => 'issue-license:approval-test'];
        $url = '/api/mcp/confirmations/'.$confirmation['confirmation_id'].'/approve';

        $first = $this->withToken('user-access-token')->postJson($url, $body)
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.replayed', false);
        $this->withToken('user-access-token')->postJson($url, $body)
            ->assertOk()
            ->assertJsonPath('data.execution_id', $first->json('data.execution_id'))
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(1, $gatewayCalls);
    }
}
