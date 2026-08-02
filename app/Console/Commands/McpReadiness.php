<?php

namespace App\Console\Commands;

use App\Mcp\Model\ModelClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class McpReadiness extends Command
{
    protected $signature = 'mcp:readiness {--skip-model} {--allow-disabled}';
    protected $description = 'Verify MCP production configuration, storage, rollout scope, and model availability';

    public function handle(ModelClient $model): int
    {
        $checks = [];
        $checks['database'] = $this->check(fn () => DB::connection()->getPdo() !== null);
        $checks['execution_enabled_or_intentionally_disabled'] = (bool) config('mcp.execution.enabled') || (bool) $this->option('allow-disabled');
        $checks['signing_secret'] = strlen((string) config('mcp.execution.signing_secret')) >= 32;
        $checks['berzuda_https'] = str_starts_with((string) config('mcp.execution.berzuda_url'), 'https://');
        $rollout = config('mcp.rollout.issue_license', []);
        foreach (['workspace_id', 'collection_id', 'environment_id', 'lookup_request_id', 'update_request_id'] as $field) {
            $checks['allowlist_'.$field] = is_string($rollout[$field] ?? null) && preg_match('/^[0-9a-f-]{36}$/i', $rollout[$field]) === 1;
        }
        $checks['allowlist_workflow_version'] = ($rollout['workflow_version'] ?? null) === '1.0.0';
        if (!$this->option('skip-model')) {
            $checks['qwen_model'] = $this->check(fn () => ($model->status()['model_installed'] ?? false) === true);
        }
        foreach ($checks as $name => $passed) $this->line(($passed ? '<info>PASS</info> ' : '<error>FAIL</error> ').$name);
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function check(callable $callback): bool
    {
        try { return $callback() === true; } catch (Throwable) { return false; }
    }
}
