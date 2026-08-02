<?php

namespace App\Mcp\Planning;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Model\ModelClient;

class PlanningService
{
    public function __construct(
        private readonly ModelClient $model,
        private readonly PlanningPrompt $prompt,
        private readonly PlanSchema $schema,
        private readonly ModelPlanValidator $validator,
    ) {
    }

    public function plan(string $message, string $mode, array $context): array
    {
        $response = $this->model->generatePlan(
            $this->prompt->messages($message, $mode, $context),
            $this->schema->get(),
        );

        try {
            $plan = json_decode($response->content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new McpException('MODEL_OUTPUT_INVALID', 'Qwen returned invalid JSON.', httpStatus: 422);
        }
        if (!is_array($plan)) {
            throw new McpException('MODEL_OUTPUT_INVALID', 'Qwen did not return a plan object.', httpStatus: 422);
        }

        $this->validator->validate($plan, $mode, $context);
        $plan = $this->normalizeReviewedWorkflow($plan, $mode, $context);

        return [
            'plan' => $this->validator->validate($plan, $mode, $context),
            'model' => [
                'runtime' => 'ollama',
                'name' => $response->model,
                'prompt_tokens' => $response->promptTokens,
                'completion_tokens' => $response->completionTokens,
                'duration_ms' => $response->durationNanoseconds === null ? null : (int) round($response->durationNanoseconds / 1_000_000),
            ],
            'execution_enabled' => (bool) config('mcp.execution.enabled'),
            'write_approval' => 'separate_authenticated_route',
        ];
    }

    private function normalizeReviewedWorkflow(array $plan, string $mode, array $context): array
    {
        if (!config('mcp.execution.enabled') || ($plan['intent'] ?? null) !== 'issue_license') {
            return $plan;
        }

        $inputs = is_array($plan['inputs'] ?? null) ? $plan['inputs'] : [];
        $nationalId = $inputs['national_id'] ?? null;
        $missing = [];
        if (!is_string($nationalId) || preg_match('/^[A-Za-z0-9-]{5,40}$/', $nationalId) !== 1) {
            $missing[] = 'national_id';
            unset($inputs['national_id']);
        }
        foreach (['collection_id', 'environment_id'] as $field) {
            if (!is_string($context[$field] ?? null) || $context[$field] === '') {
                $missing[] = $field;
            }
        }

        $plan['contract_version'] = '1.0';
        $plan['mode'] = $mode;
        $plan['context'] = $context;
        $plan['inputs'] = $inputs;
        $plan['missing_inputs'] = $missing;
        $plan['steps'] = $missing ? [] : [[
            'id' => 'prepare_issue_license',
            'tool' => 'prepare_issue_license',
            'operation' => 'Look up the registration in UAT and prepare the Endorsement-Complete write for user confirmation.',
            'effect' => 'read',
            'arguments' => [
                'workspace_id' => $context['workspace_id'],
                'collection_id' => $context['collection_id'],
                'environment_id' => $context['environment_id'],
                'national_id' => $nationalId,
            ],
            'depends_on' => [],
            'requires_confirmation' => false,
        ]];

        return $plan;
    }
}
