<?php

namespace Tests\Feature;

use App\Models\McpExecution;
use App\Mcp\Auth\AuthenticatedUser;
use App\Mcp\Conversation\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpConversationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const USER = 'd7537b93-f733-4c6f-bdb7-d75cacb8802a';
    private const OTHER = 'e8537b93-f733-4c6f-bdb7-d75cacb8802b';
    private const WORKSPACE = 'c730595e-9010-4b04-a90c-69edca895ac2';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mcp.supabase_url' => 'https://requestlab.supabase.co', 'mcp.supabase_publishable_key' => 'test-key']);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/auth/v1/user')) return Http::response(['id' => self::USER]);
            if (str_contains($request->url(), '/rest/v1/postman_workspaces')) return Http::response([['id' => self::WORKSPACE, 'name' => 'UAT']]);
            return Http::response([], 404);
        });
    }

    public function test_conversation_is_persisted_and_scoped_to_authenticated_user(): void
    {
        $created = $this->withToken('token')->postJson('/api/mcp/conversations', ['workspace_id' => self::WORKSPACE])
            ->assertCreated()->assertJsonPath('data.title', 'New chat');
        $id = $created->json('data.id');
        $this->withToken('token')->postJson("/api/mcp/conversations/$id/messages", ['role' => 'user', 'text' => 'Issue license 436415528'])
            ->assertCreated()->assertJsonPath('data.text', 'Issue license 436415528');
        $this->withToken('token')->getJson("/api/mcp/conversations/$id")
            ->assertOk()->assertJsonPath('data.messages.0.role', 'user');
        $this->assertDatabaseMissing('mcp_messages', ['content' => 'Issue license 436415528']);
        $context = app(ConversationService::class)->planningContext(
            new AuthenticatedUser(self::USER, null, 'token'), $id, self::WORKSPACE,
        );
        $this->assertStringContainsString('USER: Issue license 436415528', $context);

        \App\Models\McpConversation::whereKey($id)->update(['user_id' => self::OTHER]);
        $this->withToken('token')->getJson("/api/mcp/conversations/$id")->assertNotFound();
    }

    public function test_execution_list_returns_only_safe_fields_for_current_user(): void
    {
        McpExecution::create([
            'id' => (string) Str::uuid(), 'user_id' => self::USER, 'workspace_id' => self::WORKSPACE,
            'collection_id' => (string) Str::uuid(), 'request_id' => (string) Str::uuid(), 'environment_id' => (string) Str::uuid(),
            'resolved_host' => 'uat.example.test', 'method' => 'PUT', 'autonomy_mode' => 'confirm_writes',
            'idempotency_key' => 'safe-key-12345678', 'operation_digest' => str_repeat('a', 64), 'status' => 'succeeded',
            'outcome_summary' => ['message' => 'Updated', 'qr_link' => '[REDACTED]'],
        ]);
        $this->withToken('token')->getJson('/api/mcp/executions?workspace_id='.self::WORKSPACE)
            ->assertOk()->assertJsonPath('data.0.resolved_host', 'uat.example.test')
            ->assertJsonMissingPath('data.0.idempotency_key')->assertJsonMissingPath('data.0.operation_digest');
    }
}
