<?php

namespace App\Mcp\Execution;

use App\Mcp\Auth\AuthenticatedUser;
use App\Mcp\Exceptions\McpException;
use Throwable;

class ApprovedWorkflowExecutor
{
    public function __construct(
        private readonly ConfirmationService $confirmations,
        private readonly ExecutionLedger $ledger,
        private readonly RequestLabExecutor $executor,
        private readonly RolloutPolicy $rollout,
    ) {
    }

    public function execute(
        AuthenticatedUser $user,
        string $confirmationId,
        string $planDigest,
        string $idempotencyKey,
    ): array {
        if (!config('mcp.execution.enabled')) {
            throw new McpException('EXECUTION_DISABLED', 'Workflow execution is disabled by server configuration.', httpStatus: 503);
        }

        $existing = $this->ledger->findByConfirmation($user->id, $confirmationId);
        if ($existing) {
            if (!hash_equals($existing->operation_digest, $planDigest) || $existing->idempotency_key !== $idempotencyKey) {
                throw new McpException(
                    'IDEMPOTENCY_CONFLICT',
                    'This confirmation was already submitted with different execution details.',
                    ['execution_id' => $existing->id],
                    409,
                );
            }
            if ($existing->status === 'in_progress') {
                throw new McpException('EXECUTION_IN_PROGRESS', 'This confirmed operation is already in progress.', ['execution_id' => $existing->id], 409);
            }
            return $this->result($existing, true);
        }

        $confirmation = $this->confirmations->approveAndConsume($user->id, $confirmationId, $planDigest);
        $payload = $confirmation->payload;
        if (!is_array($payload) || ($payload['plan']['workflow']['name'] ?? null) !== 'issue_license') {
            throw new McpException('CONFIRMATION_INVALID', 'The confirmed workflow payload is invalid.', httpStatus: 422);
        }
        $this->rollout->assertIssueLicense([
            'workspace_id' => $payload['workspace_id'] ?? '', 'collection_id' => $payload['collection_id'] ?? '',
            'environment_id' => $payload['environment_id'] ?? '',
            'lookup_request_id' => $payload['plan']['request_ids'][0] ?? '',
            'update_request_id' => $payload['update_request_id'] ?? '',
            'workflow_version' => $payload['plan']['workflow']['version'] ?? '',
        ]);

        $reservation = $this->ledger->reserve([
            'user_id' => $user->id,
            'workspace_id' => $payload['workspace_id'],
            'collection_id' => $payload['collection_id'],
            'request_id' => $payload['update_request_id'],
            'environment_id' => $payload['environment_id'],
            'workflow_name' => 'issue_license',
            'workflow_version' => $payload['plan']['workflow']['version'],
            'resolved_host' => $payload['lookup_host'],
            'method' => 'PUT',
            'autonomy_mode' => 'confirm_writes',
            'confirmation_id' => $confirmation->id,
            'confirmed_by' => $user->id,
            'idempotency_key' => $idempotencyKey,
            'operation_digest' => $planDigest,
        ]);
        $execution = $reservation['execution'];
        if ($reservation['replayed']) {
            if ($execution->status === 'in_progress') {
                throw new McpException('EXECUTION_IN_PROGRESS', 'This confirmed operation is already in progress.', ['execution_id' => $execution->id], 409);
            }
            return $this->result($execution, true);
        }

        $started = hrtime(true);
        try {
            $evidence = $this->executor->execute(
                $user,
                $payload['update_request_id'],
                $payload['environment_id'],
                $payload['collection_id'],
                'PUT',
                [
                    'national_id' => $payload['national_id'],
                    'endorsement_status' => 'Endorsement-Complete',
                    'qr_link' => $payload['qr_link'],
                ],
                $payload['update_definition_digest'],
                $payload['lookup_host'],
            );
            $success = ($evidence['status'] ?? 500) >= 200 && ($evidence['status'] ?? 500) < 300;
            $execution = $this->ledger->complete(
                $execution,
                $success ? 'succeeded' : 'failed',
                $this->durationMs($started),
                is_int($evidence['status'] ?? null) ? $evidence['status'] : null,
                [
                    'result' => $success ? 'Update accepted by the UAT endpoint.' : 'The UAT endpoint rejected the update.',
                    'response_bytes' => is_int($evidence['size_bytes'] ?? null) ? $evidence['size_bytes'] : null,
                ],
            );
            if (!$success) {
                throw new McpException(
                    'UPDATE_FAILED',
                    'The registration update was rejected by the UAT endpoint.',
                    ['execution_id' => $execution->id, 'status' => $evidence['status'] ?? null],
                    422,
                );
            }
            return $this->result($execution, false);
        } catch (McpException $exception) {
            if ($execution->status === 'in_progress') {
                $this->ledger->complete(
                    $execution,
                    'failed',
                    $this->durationMs($started),
                    null,
                    ['result' => 'Execution failed.', 'error_code' => $exception->errorCode],
                );
            }
            throw $exception;
        } catch (Throwable $exception) {
            $this->ledger->complete(
                $execution,
                'failed',
                $this->durationMs($started),
                null,
                ['result' => 'Execution failed.', 'error_code' => 'INTERNAL_ERROR'],
            );
            throw $exception;
        }
    }

    private function durationMs(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }

    private function result($execution, bool $replayed): array
    {
        return [
            'execution_id' => $execution->id,
            'status' => $execution->status,
            'upstream_status' => $execution->upstream_status,
            'duration_ms' => $execution->duration_ms,
            'outcome' => $execution->outcome_summary,
            'replayed' => $replayed,
        ];
    }
}
