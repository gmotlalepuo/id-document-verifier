<?php

namespace Tests\Unit;

use App\Mcp\Knowledge\KnowledgeCatalog;
use PHPUnit\Framework\TestCase;

class KnowledgeCatalogTest extends TestCase
{
    public function test_database_dictionary_covers_the_requestlab_contract_tables(): void
    {
        $dictionary = (new KnowledgeCatalog())->databaseDictionary();
        $tables = array_column($dictionary['tables'], 'name');

        $this->assertSame('workspace', $dictionary['tenant_boundary']);
        $this->assertContains('postman_workspaces', $tables);
        $this->assertContains('postman_workspace_members', $tables);
        $this->assertContains('postman_workspace_invites', $tables);
        $this->assertContains('postman_collections', $tables);
        $this->assertContains('postman_folders', $tables);
        $this->assertContains('postman_requests', $tables);
        $this->assertContains('postman_environments', $tables);
    }

    public function test_license_workflow_preserves_extractions_dependencies_and_exact_status(): void
    {
        $result = (new KnowledgeCatalog())->getWorkflow('issue_license');
        $workflow = $result['workflow'];

        $this->assertSame('lookup_registration', $workflow['steps'][0]['id']);
        $this->assertSame('$.teacher_registrations.qr_link', $workflow['steps'][0]['extract']['qr_link']);
        $this->assertSame('$.teacher_registrations.reg_status', $workflow['steps'][0]['extract']['reg_status']);
        $this->assertSame(['lookup_registration'], $workflow['steps'][1]['depends_on']);
        $this->assertSame('required', $workflow['steps'][1]['confirmation']);
        $this->assertSame('literal:Endorsement-Complete', $workflow['steps'][1]['inputs']['status']);
        $this->assertTrue($result['ready']);
        $this->assertSame([], $result['unresolved_references']);
    }

    public function test_verified_bindings_are_execution_ready(): void
    {
        $catalog = new KnowledgeCatalog();
        $lookup = $catalog->getEndpoint('get_registration_by_national_id');
        $update = $catalog->getEndpoint('update_registration_status');

        $this->assertSame('bound', $lookup['binding']['status']);
        $this->assertSame('a9758cea-ef5b-4527-aa2b-3140f36f4524', $lookup['binding']['request_id']);
        $this->assertSame('$.teacher_registrations.qr_link', $lookup['response_extractions'][0]['path']);
        $this->assertSame('bound', $update['binding']['status']);
        $this->assertSame('105b8b1a-cee9-4e95-81c7-0b9f879f2f60', $update['binding']['request_id']);
        $this->assertSame('Endorsement-Complete', $update['required_inputs'][2]['constant']);
        $this->assertTrue($catalog->status()['ready_for_execution']);
    }

    public function test_planning_context_is_bounded_and_contains_reviewed_knowledge(): void
    {
        $context = (new KnowledgeCatalog())->planningContext();

        $this->assertSame('workspace', $context['tenant_boundary']);
        $this->assertSame('postman_workspaces', $context['tables'][0]['name']);
        $this->assertArrayNotHasKey('fields', $context['tables'][0]);
        $this->assertSame('get_registration_by_national_id', $context['endpoints'][0]['semantic_ref']);
        $this->assertSame('issue_license', $context['workflows'][0]['name']);
        $this->assertTrue($context['execution_readiness']);
    }
}
