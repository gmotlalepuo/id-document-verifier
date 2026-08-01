<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class McpContractTest extends TestCase
{
    public function test_machine_readable_contracts_are_valid_json_schemas(): void
    {
        foreach ([
            'model-plan.schema.json',
            'workflow.schema.json',
            'database-dictionary.schema.json',
            'endpoint-knowledge.schema.json',
        ] as $filename) {
            $path = dirname(__DIR__, 2).'/docs/mcp/contracts/'.$filename;
            $this->assertFileExists($path);

            $schema = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
            $this->assertSame('object', $schema['type']);
            $this->assertFalse($schema['additionalProperties']);
            $this->assertNotEmpty($schema['required']);
        }
    }

    public function test_phase_zero_documents_and_exact_status_value_are_version_controlled(): void
    {
        $root = dirname(__DIR__, 2).'/docs/mcp';
        foreach (['ARCHITECTURE.md', 'POLICIES.md', 'BERZUDA_INTEGRATION.md', 'ACCEPTANCE_CRITERIA.md'] as $filename) {
            $this->assertFileExists($root.'/'.$filename);
        }

        $policy = file_get_contents($root.'/POLICIES.md');
        $this->assertStringContainsString('Endorsement-Complete', $policy);
        $this->assertStringContainsString('The model cannot create or approve a confirmation.', $policy);
    }
}
