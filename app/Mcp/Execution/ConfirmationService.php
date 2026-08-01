<?php

namespace App\Mcp\Execution;

use App\Mcp\Exceptions\McpException;
use App\Models\McpConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfirmationService
{
    public function issue(string $userId, string $planDigest, array $preview, array $payload): array
    {
        $confirmation = McpConfirmation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'plan_digest' => $planDigest,
            'preview' => $preview,
            'payload' => $payload,
            'expires_at' => now()->addSeconds(config('mcp.execution.confirmation_ttl_seconds')),
        ]);

        return [
            'confirmation_id' => $confirmation->id,
            'plan_digest' => $planDigest,
            'expires_at' => $confirmation->expires_at->toIso8601String(),
            'preview' => $preview,
        ];
    }

    public function approveAndConsume(string $userId, string $confirmationId, string $planDigest): McpConfirmation
    {
        return DB::transaction(function () use ($userId, $confirmationId, $planDigest) {
            $confirmation = McpConfirmation::query()
                ->whereKey($confirmationId)
                ->lockForUpdate()
                ->first();

            if (!$confirmation || !hash_equals($confirmation->user_id, $userId)) {
                throw new McpException('CONFIRMATION_INVALID', 'The write confirmation is invalid.', httpStatus: 422);
            }
            if (!hash_equals($confirmation->plan_digest, $planDigest)) {
                throw new McpException('CONFIRMATION_PLAN_CHANGED', 'The approved plan no longer matches this execution.', httpStatus: 409);
            }
            if ($confirmation->consumed_at !== null) {
                throw new McpException('CONFIRMATION_ALREADY_USED', 'The write confirmation has already been used.', httpStatus: 409);
            }
            if ($confirmation->expires_at->isPast()) {
                throw new McpException('CONFIRMATION_EXPIRED', 'The write confirmation has expired.', httpStatus: 410);
            }

            $confirmation->forceFill(['approved_at' => now(), 'consumed_at' => now()])->save();
            return $confirmation;
        });
    }
}
