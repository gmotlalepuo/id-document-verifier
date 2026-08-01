<?php

namespace App\Mcp\Execution;

use App\Mcp\Exceptions\McpException;
use App\Models\McpExecution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class ExecutionLedger
{
    public function findByConfirmation(string $userId, string $confirmationId): ?McpExecution
    {
        return McpExecution::query()
            ->where('user_id', $userId)
            ->where('confirmation_id', $confirmationId)
            ->first();
    }

    public function reserve(array $audit): array
    {
        try {
            $execution = McpExecution::create($audit + [
                'id' => (string) Str::uuid(),
                'status' => 'in_progress',
            ]);
            return ['execution' => $execution, 'replayed' => false];
        } catch (QueryException $exception) {
            $existing = McpExecution::query()
                ->where('user_id', $audit['user_id'])
                ->where('idempotency_key', $audit['idempotency_key'])
                ->first();

            if (!$existing) {
                throw $exception;
            }
            if (!hash_equals($existing->operation_digest, $audit['operation_digest'])) {
                throw new McpException(
                    'IDEMPOTENCY_CONFLICT',
                    'This idempotency key was already used for a different operation.',
                    httpStatus: 409,
                );
            }

            return ['execution' => $existing, 'replayed' => true];
        }
    }

    public function complete(
        McpExecution $execution,
        string $status,
        int $durationMs,
        ?int $upstreamStatus,
        array $outcomeSummary,
    ): McpExecution {
        $execution->forceFill([
            'status' => $status,
            'duration_ms' => $durationMs,
            'upstream_status' => $upstreamStatus,
            'outcome_summary' => $outcomeSummary,
        ])->save();

        return $execution->fresh();
    }
}
