<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpKnowledgeTest extends TestCase
{
    private const USER_ID = 'd7537b93-f733-4c6f-bdb7-d75cacb8802a';
    private const WORKSPACE_ID = '6fb5ad6f-f34a-49a5-a130-e8b6d22631b0';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mcp.supabase_url' => 'https://requestlab.supabase.co',
            'mcp.supabase_publishable_key' => 'publishable-test-key',
        ]);
    }

    public function test_named_license_workflow_is_available_through_mcp(): void
    {
        $this->fakeAuthenticatedUser();

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->callTool('get_workflow', ['name' => 'issue_license']))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.workflow.name', 'issue_license')
            ->assertJsonPath('result.structuredContent.data.workflow.steps.1.confirmation', 'required')
            ->assertJsonPath('result.structuredContent.data.workflow.steps.1.inputs.status', 'literal:Endorsement-Complete')
            ->assertJsonPath('result.structuredContent.data.ready', true);
    }

    public function test_endpoint_knowledge_reports_verified_bindings(): void
    {
        $this->fakeAuthenticatedUser();

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->callTool('get_endpoint_knowledge', [
                'semantic_ref' => 'get_registration_by_national_id',
            ]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.binding.status', 'bound')
            ->assertJsonPath('result.structuredContent.data.binding.request_id', 'a9758cea-ef5b-4527-aa2b-3140f36f4524')
            ->assertJsonPath('result.structuredContent.data.response_extractions.0.name', 'qr_link')
            ->assertJsonPath('result.structuredContent.data.response_extractions.0.path', '$.teacher_registrations.qr_link');
    }

    public function test_endpoint_candidate_search_is_workspace_scoped_through_supabase_rls(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/auth/v1/user')) {
                return Http::response(['id' => self::USER_ID]);
            }
            return Http::response([[
                'id' => '9ce16c40-e16e-40e8-b76c-edcc1c3db865',
                'collection_id' => 'a0d6108f-e23c-4f0a-a480-b21cd1be1cc1',
                'folder_id' => null,
                'name' => 'Get teacher by national ID',
                'documentation' => 'Find a teacher registration.',
                'method' => 'GET',
                'url' => '{{base_url}}/teachers/{{national_id}}',
                'postman_collections' => [
                    'workspace_id' => self::WORKSPACE_ID,
                    'name' => 'Teacher registration',
                ],
            ]]);
        });

        $this->withToken('user-access-token')
            ->postJson('/api/mcp', $this->callTool('search_endpoint_candidates', [
                'workspace_id' => self::WORKSPACE_ID,
                'query' => 'teacher national ID',
                'limit' => 5,
            ]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.0.name', 'Get teacher by national ID')
            ->assertJsonPath('result.structuredContent.data.0.side_effect_hint', 'read');

        Http::assertSent(fn (Request $request) =>
            str_contains($request->url(), '/rest/v1/postman_requests')
            && str_contains(urldecode($request->url()), 'postman_collections.workspace_id=eq.'.self::WORKSPACE_ID)
            && $request->hasHeader('Authorization', 'Bearer user-access-token')
        );
    }

    private function fakeAuthenticatedUser(): void
    {
        Http::fake([
            'https://requestlab.supabase.co/auth/v1/user' => Http::response(['id' => self::USER_ID]),
        ]);
    }

    private function callTool(string $name, array $arguments): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ];
    }
}
