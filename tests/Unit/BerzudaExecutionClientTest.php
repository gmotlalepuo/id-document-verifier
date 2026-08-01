<?php

namespace Tests\Unit;

use App\Mcp\Auth\AuthenticatedUser;
use App\Mcp\Exceptions\McpException;
use App\Mcp\Execution\BerzudaExecutionClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BerzudaExecutionClientTest extends TestCase
{
    private const URL = 'https://requestlab.test/api/mcp/execute';
    private const SECRET = 'a-strong-shared-secret-with-32-characters';
    private const REQUEST_ID = 'a9758cea-ef5b-4527-aa2b-3140f36f4524';
    private const ENVIRONMENT_ID = 'c951fa51-33d4-471a-b2bd-9749c96b2d27';
    private const COLLECTION_ID = '789a28c0-8094-4f7e-9d7c-ba6ba602caeb';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mcp.execution.berzuda_url' => self::URL,
            'mcp.execution.signing_secret' => self::SECRET,
            'mcp.execution.timeout_seconds' => 10,
        ]);
    }

    public function test_it_signs_exact_body_and_forwards_user_token(): void
    {
        Http::fake([self::URL => Http::response([
            'request_id' => self::REQUEST_ID,
            'environment_id' => self::ENVIRONMENT_ID,
            'resolved_host' => 'uat.example.test',
            'method' => 'GET',
            'status' => 200,
            'duration_ms' => 25,
            'body' => '{}',
        ])]);

        $result = $this->client()->execute(
            $this->user(),
            self::REQUEST_ID,
            self::ENVIRONMENT_ID,
            self::COLLECTION_ID,
            'GET',
            ['national_id' => 'redacted-test-id'],
            str_repeat('d', 64),
        );

        $this->assertSame(200, $result['status']);
        Http::assertSent(function (Request $request) {
            $timestamp = $request->header('X-MCP-Timestamp')[0] ?? '';
            $signature = $request->header('X-MCP-Signature')[0] ?? '';
            return $request->url() === self::URL
                && $request->hasHeader('Authorization', 'Bearer user-access-token')
                && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->body(), self::SECRET), $signature);
        });
    }

    public function test_it_rejects_mismatched_execution_evidence(): void
    {
        Http::fake([self::URL => Http::response([
            'request_id' => '105b8b1a-cee9-4e95-81c7-0b9f879f2f60',
            'environment_id' => self::ENVIRONMENT_ID,
            'resolved_host' => 'uat.example.test',
            'method' => 'GET',
            'status' => 200,
            'duration_ms' => 25,
            'body' => '{}',
        ])]);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('evidence does not match');
        $this->client()->execute(
            $this->user(),
            self::REQUEST_ID,
            self::ENVIRONMENT_ID,
            self::COLLECTION_ID,
            'GET',
            [],
            str_repeat('d', 64),
        );
    }

    public function test_it_returns_structured_gateway_errors(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'The stored request method changed after approval.'], 409)]);

        try {
            $this->client()->execute(
                $this->user(),
                self::REQUEST_ID,
                self::ENVIRONMENT_ID,
                self::COLLECTION_ID,
                'GET',
                [],
                str_repeat('d', 64),
            );
            $this->fail('Expected the gateway failure to be surfaced.');
        } catch (McpException $exception) {
            $this->assertSame('EXECUTION_GATEWAY_ERROR', $exception->errorCode);
            $this->assertSame(409, $exception->httpStatus);
        }
    }

    private function client(): BerzudaExecutionClient
    {
        return new BerzudaExecutionClient();
    }

    private function user(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            'd7537b93-f733-4c6f-bdb7-d75cacb8802a',
            'user@example.test',
            'user-access-token',
        );
    }
}
