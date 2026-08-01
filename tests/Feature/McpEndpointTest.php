<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    private const USER_ID = 'd7537b93-f733-4c6f-bdb7-d75cacb8802a';
    private const WORKSPACE_ID = '6fb5ad6f-f34a-49a5-a130-e8b6d22631b0';
    private const COLLECTION_ID = 'a0d6108f-e23c-4f0a-a480-b21cd1be1cc1';
    private const REQUEST_ID = '9ce16c40-e16e-40e8-b76c-edcc1c3db865';
    private const ENVIRONMENT_ID = 'df2b56df-6083-407c-9f38-1a2262602650';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mcp.supabase_url' => 'https://requestlab.supabase.co',
            'mcp.supabase_publishable_key' => 'publishable-test-key',
        ]);
    }

    public function test_mcp_requires_a_supabase_bearer_token(): void
    {
        $this->postJson('/api/mcp', $this->rpc('initialize'))
            ->assertUnauthorized()
            ->assertJsonPath('error.data.code', 'AUTH_REQUIRED');
    }

    public function test_initialize_returns_server_capabilities_after_authentication(): void
    {
        $this->fakeAuthenticatedUser();

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->rpc('initialize'))
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-03-26')
            ->assertJsonPath('result.capabilities.tools.listChanged', false);
    }

    public function test_notifications_are_also_authenticated(): void
    {
        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ])->assertUnauthorized();
    }

    public function test_tools_list_exposes_phase_one_read_tools_only(): void
    {
        $this->fakeAuthenticatedUser();

        $response = $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->rpc('tools/list'))
            ->assertOk();

        $names = array_column($response->json('result.tools'), 'name');
        $this->assertContains('resolve_request', $names);
        $this->assertContains('list_workspaces', $names);
        $this->assertContains('model_status', $names);
        $this->assertContains('plan_request', $names);
        $this->assertNotContains('run_request', $names);
        $this->assertNotContains('update_request', $names);
    }

    public function test_list_workspaces_forwards_the_user_token_so_rls_remains_authoritative(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/auth/v1/user')) {
                return Http::response(['id' => self::USER_ID, 'email' => 'user@example.com']);
            }
            return Http::response([[
                'id' => self::WORKSPACE_ID,
                'user_id' => self::USER_ID,
                'name' => 'Teacher Registration',
                'created_at' => '2026-07-30T10:00:00Z',
            ]]);
        });

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->rpc('tools/call', [
                'name' => 'list_workspaces',
                'arguments' => [],
            ]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.0.name', 'Teacher Registration');

        Http::assertSent(fn (Request $request) =>
            str_contains($request->url(), '/rest/v1/postman_workspaces')
            && $request->hasHeader('Authorization', 'Bearer user-access-token')
            && $request->hasHeader('apikey', 'publishable-test-key')
        );
    }

    public function test_resolve_request_redacts_secrets_and_resolves_non_secret_values(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/auth/v1/user')) {
                return Http::response(['id' => self::USER_ID, 'email' => 'user@example.com']);
            }
            if (str_contains($request->url(), 'postman_requests')) {
                return Http::response([[
                    'id' => self::REQUEST_ID,
                    'collection_id' => self::COLLECTION_ID,
                    'folder_id' => null,
                    'name' => 'Get teacher',
                    'documentation' => '',
                    'sort_order' => 0,
                    'method' => 'GET',
                    'url' => '{{base_url}}/teachers/{{national_id}}',
                    'params' => [],
                    'headers' => [['key' => 'Authorization', 'value' => 'Bearer {{api_token}}', 'enabled' => true]],
                    'body_mode' => 'none',
                    'body_raw' => '',
                    'body_form' => [],
                    'auth' => ['type' => 'none'],
                ]]);
            }
            return Http::response([[
                'id' => self::ENVIRONMENT_ID,
                'workspace_id' => self::WORKSPACE_ID,
                'collection_id' => self::COLLECTION_ID,
                'name' => 'UAT',
                'variables' => [
                    ['key' => 'base_url', 'value' => 'https://uat.example.com', 'enabled' => true],
                    ['key' => 'national_id', 'value' => '436415528', 'enabled' => true],
                    ['key' => 'api_token', 'value' => 'super-secret-token', 'enabled' => true],
                ],
            ]]);
        });

        $response = $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->rpc('tools/call', [
                'name' => 'resolve_request',
                'arguments' => [
                    'request_id' => self::REQUEST_ID,
                    'environment_id' => self::ENVIRONMENT_ID,
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.url', 'https://uat.example.com/teachers/436415528')
            ->assertJsonPath('result.structuredContent.data.headers.0.value', '[REDACTED]');

        $this->assertStringNotContainsString('super-secret-token', $response->getContent());
    }

    public function test_unresolved_variables_return_a_structured_tool_error_without_execution(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/auth/v1/user')) {
                return Http::response(['id' => self::USER_ID]);
            }
            if (str_contains($request->url(), 'postman_requests')) {
                return Http::response([[
                    'id' => self::REQUEST_ID,
                    'collection_id' => self::COLLECTION_ID,
                    'method' => 'GET',
                    'url' => '{{base_url}}/teachers/{{national_id}}',
                    'params' => [],
                    'headers' => [],
                    'body_mode' => 'none',
                    'body_raw' => '',
                    'body_form' => [],
                    'auth' => ['type' => 'none'],
                ]]);
            }
            return Http::response([[
                'id' => self::ENVIRONMENT_ID,
                'collection_id' => self::COLLECTION_ID,
                'variables' => [['key' => 'base_url', 'value' => 'https://uat.example.com', 'enabled' => true]],
            ]]);
        });

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->rpc('tools/call', [
                'name' => 'resolve_request',
                'arguments' => [
                    'request_id' => self::REQUEST_ID,
                    'environment_id' => self::ENVIRONMENT_ID,
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.error.code', 'UNRESOLVED_VARIABLE')
            ->assertJsonPath('result.structuredContent.error.missing_variables.0', 'national_id');
    }

    public function test_invalid_tool_arguments_are_rejected_before_data_access(): void
    {
        $this->fakeAuthenticatedUser();

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->rpc('tools/call', [
                'name' => 'list_collections',
                'arguments' => ['workspace_id' => 'not-a-uuid'],
            ]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.error.code', 'INVALID_TOOL_ARGUMENTS');

        Http::assertSentCount(1);
    }

    private function fakeAuthenticatedUser(): void
    {
        Http::fake([
            'https://requestlab.supabase.co/auth/v1/user' => Http::response([
                'id' => self::USER_ID,
                'email' => 'user@example.com',
            ]),
        ]);
    }

    private function rpc(string $method, array $params = []): array
    {
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method];
        if ($params) {
            $payload['params'] = $params;
        }
        return $payload;
    }
}
