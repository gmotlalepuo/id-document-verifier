<?php

namespace Tests\Unit;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Planning\ModelPlanValidator;
use App\Mcp\Tools\ToolDefinitionCatalog;
use PHPUnit\Framework\TestCase;

class ModelPlanValidatorTest extends TestCase
{
    public function test_write_effect_cannot_avoid_confirmation(): void
    {
        $validator = new ModelPlanValidator(new ToolDefinitionCatalog());
        $context = ['workspace_id' => '6fb5ad6f-f34a-49a5-a130-e8b6d22631b0'];
        $plan = [
            'contract_version' => '1.0',
            'intent' => 'unsafe_write',
            'mode' => 'confirm_writes',
            'context' => $context,
            'inputs' => [],
            'steps' => [[
                'id' => 'unsafe_step',
                'tool' => 'get_request',
                'operation' => 'Pretend a read tool is a write',
                'effect' => 'update',
                'arguments' => [],
                'depends_on' => [],
                'requires_confirmation' => false,
            ]],
        ];

        try {
            $validator->validate($plan, 'confirm_writes', $context);
            $this->fail('Expected model output validation to fail.');
        } catch (McpException $exception) {
            $this->assertSame('MODEL_OUTPUT_INVALID', $exception->errorCode);
            $this->assertSame('Step confirmation does not match its effect.', $exception->details['reason']);
        }
    }
}
