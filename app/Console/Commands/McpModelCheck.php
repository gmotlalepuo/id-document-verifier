<?php

namespace App\Console\Commands;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Model\ModelClient;
use App\Mcp\Planning\PlanningService;
use Illuminate\Console\Command;

class McpModelCheck extends Command
{
    protected $signature = 'mcp:model-check {--plan : Generate and validate a real plan}';
    protected $description = 'Check Ollama/Qwen readiness and optionally run a schema-constrained planning smoke test';

    public function handle(ModelClient $model, PlanningService $planning): int
    {
        $status = $model->status();
        $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (!($status['ready'] ?? false)) {
            return self::FAILURE;
        }
        if (!$this->option('plan')) {
            return self::SUCCESS;
        }

        try {
            $result = $planning->plan(
                'Inspect the active workspace and identify the reviewed issue_license workflow. Do not execute anything.',
                'plan_only',
                ['workspace_id' => '00000000-0000-4000-8000-000000000001'],
            );
        } catch (McpException $exception) {
            $this->error($exception->errorCode.': '.$exception->getMessage());
            if ($exception->details) {
                $this->line(json_encode($exception->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
