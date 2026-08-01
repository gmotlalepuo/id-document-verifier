<?php

namespace App\Mcp\Planning;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Tools\ToolDefinitionCatalog;

class ModelPlanValidator
{
    private const MODES = ['plan_only', 'read_only_auto', 'confirm_writes'];
    private const EFFECTS = ['none', 'read', 'create', 'update', 'delete'];

    public function __construct(private readonly ToolDefinitionCatalog $tools)
    {
    }

    public function validate(array $plan, string $requestedMode, array $expectedContext): array
    {
        $this->exactKeys($plan, ['contract_version', 'intent', 'mode', 'context', 'inputs', 'steps'], ['missing_inputs'], 'plan');
        $this->require($plan['contract_version'] === '1.0', 'contract_version must be 1.0.');
        $this->require(is_string($plan['intent']) && preg_match('/^[a-z][a-z0-9_]{0,119}$/', $plan['intent']) === 1, 'intent is invalid.');
        $this->require(in_array($plan['mode'], self::MODES, true) && $plan['mode'] === $requestedMode, 'mode does not match the request.');
        $this->require(is_array($plan['context']) && $plan['context'] === $expectedContext, 'context was changed by the model.');
        $this->require(is_array($plan['inputs']) && !$this->containsNonScalar($plan['inputs']), 'inputs must contain scalar values.');

        $missing = $plan['missing_inputs'] ?? [];
        $this->require(is_array($missing) && count($missing) === count(array_unique($missing)), 'missing_inputs must be unique.');
        foreach ($missing as $input) {
            $this->require(is_string($input) && $input !== '' && strlen($input) <= 120, 'missing_inputs contains an invalid name.');
        }

        $this->require(is_array($plan['steps']) && count($plan['steps']) <= 20, 'steps is invalid.');
        $toolNames = $this->tools->modelToolNames();
        $ids = [];
        foreach ($plan['steps'] as $index => $step) {
            $this->require(is_array($step), 'Each step must be an object.');
            $this->exactKeys($step, ['id', 'tool', 'operation', 'effect', 'arguments', 'depends_on', 'requires_confirmation'], ['extract'], 'step '.$index);
            $this->require(is_string($step['id']) && preg_match('/^[a-z][a-z0-9_]{0,79}$/', $step['id']) === 1, 'Step ID is invalid.');
            $this->require(!in_array($step['id'], $ids, true), 'Step IDs must be unique.');
            $ids[] = $step['id'];
            $this->require(is_string($step['tool']) && in_array($step['tool'], $toolNames, true), 'Step tool is not allowlisted.');
            $this->require(is_string($step['operation']) && $step['operation'] !== '' && strlen($step['operation']) <= 160, 'Step operation is invalid.');
            $this->require(in_array($step['effect'], self::EFFECTS, true), 'Step effect is invalid.');
            $this->require(is_array($step['arguments']), 'Step arguments must be an object.');
            $this->require(is_array($step['depends_on']) && count($step['depends_on']) === count(array_unique($step['depends_on'])), 'Step dependencies are invalid.');
            $this->require(is_bool($step['requires_confirmation']), 'Step confirmation flag must be boolean.');
            $write = in_array($step['effect'], ['create', 'update', 'delete'], true);
            $this->require($step['requires_confirmation'] === $write, 'Step confirmation does not match its effect.');
            if (isset($step['extract'])) {
                $this->require(is_array($step['extract']) && !$this->containsNonString($step['extract']), 'Step extraction rules are invalid.');
            }
        }

        foreach ($plan['steps'] as $step) {
            foreach ($step['depends_on'] as $dependency) {
                $this->require(is_string($dependency) && $dependency !== $step['id'] && in_array($dependency, $ids, true), 'Step dependency does not exist.');
            }
        }

        return $plan;
    }

    private function exactKeys(array $value, array $required, array $optional, string $scope): void
    {
        foreach ($required as $key) {
            $this->require(array_key_exists($key, $value), $scope.' is missing '.$key.'.');
        }
        $unexpected = array_diff(array_keys($value), array_merge($required, $optional));
        $this->require($unexpected === [], $scope.' contains unsupported fields.');
    }

    private function containsNonScalar(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_scalar($value) && $value !== null) {
                return true;
            }
        }
        return false;
    }

    private function containsNonString(array $values): bool
    {
        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                return true;
            }
        }
        return false;
    }

    private function require(bool $condition, string $reason): void
    {
        if (!$condition) {
            throw new McpException('MODEL_OUTPUT_INVALID', 'Qwen returned a plan that failed validation.', ['reason' => $reason], 422);
        }
    }
}
