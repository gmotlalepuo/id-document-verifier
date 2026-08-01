<?php

namespace App\Console\Commands;

use App\Mcp\Planning\PlanningService;
use Illuminate\Console\Command;
use Throwable;

class McpEvaluate extends Command
{
    protected $signature = 'mcp:evaluate {--case=}';
    protected $description = 'Run the reviewed Qwen MCP planning evaluation set';

    public function handle(PlanningService $planning): int
    {
        // Exercise the reviewed rollout normalizer only; planning never executes HTTP requests.
        config(['mcp.execution.enabled' => true]);
        $dataset = json_decode(file_get_contents(base_path('docs/mcp/evaluations/planning.json')), true);
        $cases = $dataset['cases'] ?? [];
        $selected = $this->option('case');
        if ($selected) $cases = array_values(array_filter($cases, fn ($case) => ($case['name'] ?? '') === $selected));
        if (!$cases) { $this->error('No evaluation cases matched.'); return self::FAILURE; }

        $failed = 0;
        $context = [
            'workspace_id' => config('mcp.rollout.issue_license.workspace_id'),
            'collection_id' => config('mcp.rollout.issue_license.collection_id'),
            'environment_id' => config('mcp.rollout.issue_license.environment_id'),
            'request_id' => null,
        ];
        foreach ($cases as $case) {
            try {
                $plan = $planning->plan($case['message'], 'confirm_writes', $context)['plan'];
                $tool = $plan['steps'][0]['tool'] ?? null;
                $ok = ($plan['intent'] ?? null) === $case['expected_intent']
                    && array_values($plan['missing_inputs'] ?? []) === $case['expected_missing_inputs']
                    && $tool === $case['expected_tool'];
                $this->line(($ok ? '<info>PASS</info> ' : '<error>FAIL</error> ').$case['name']);
                if (!$ok) {
                    $failed++;
                    $this->line('  expected: '.json_encode(['intent' => $case['expected_intent'], 'missing_inputs' => $case['expected_missing_inputs'], 'tool' => $case['expected_tool']]));
                    $this->line('  actual:   '.json_encode(['intent' => $plan['intent'] ?? null, 'missing_inputs' => $plan['missing_inputs'] ?? [], 'tool' => $tool]));
                }
            } catch (Throwable $e) {
                $failed++; $this->line('<error>FAIL</error> '.$case['name'].' — '.$e->getMessage());
            }
        }
        $this->info((count($cases) - $failed).'/'.count($cases).' evaluation cases passed.');
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
