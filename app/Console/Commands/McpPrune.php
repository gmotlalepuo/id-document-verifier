<?php

namespace App\Console\Commands;

use App\Models\McpConversation;
use App\Models\McpExecution;
use Illuminate\Console\Command;

class McpPrune extends Command
{
    protected $signature = 'mcp:prune';
    protected $description = 'Remove expired MCP conversation and execution records';

    public function handle(): int
    {
        $conversations = McpConversation::query()->where('updated_at', '<', now()->subDays(max(1, config('mcp.conversation.retention_days'))))->delete();
        $executions = McpExecution::query()->where('created_at', '<', now()->subDays(max(1, config('mcp.execution.audit_retention_days'))))->delete();
        $this->info("Pruned {$conversations} conversations and {$executions} execution records.");
        return self::SUCCESS;
    }
}
