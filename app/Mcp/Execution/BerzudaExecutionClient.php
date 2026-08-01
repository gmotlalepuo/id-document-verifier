<?php

namespace App\Mcp\Execution;

use App\Mcp\Auth\AuthenticatedUser;
use App\Mcp\Exceptions\McpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BerzudaExecutionClient implements RequestLabExecutor
{
    public function execute(
        AuthenticatedUser $user,
        string $requestId,
        string $environmentId,
        string $expectedCollectionId,
        string $expectedMethod,
        array $overrides,
        string $expectedDefinitionDigest,
        ?string $expectedHost = null,
    ): array {
        $url = config('mcp.execution.berzuda_url');
        $secret = config('mcp.execution.signing_secret');
        if (!is_string($url) || $url === '' || !is_string($secret) || strlen($secret) < 32) {
            throw new McpException(
                'EXECUTION_NOT_CONFIGURED',
                'The RequestLab execution gateway is not configured.',
                httpStatus: 503,
            );
        }

        $payload = [
            'request_id' => $requestId,
            'environment_id' => $environmentId,
            'expected_collection_id' => $expectedCollectionId,
            'expected_method' => strtoupper($expectedMethod),
            'overrides' => (object) $overrides,
            'expected_definition_digest' => $expectedDefinitionDigest,
            'expected_host' => $expectedHost,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        try {
            $response = Http::withToken($user->accessToken)
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-MCP-Timestamp' => $timestamp,
                    'X-MCP-Signature' => $signature,
                ])
                ->timeout(config('mcp.execution.timeout_seconds'))
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException) {
            throw new McpException(
                'EXECUTION_GATEWAY_UNAVAILABLE',
                'The RequestLab execution gateway is unavailable.',
                httpStatus: 503,
            );
        }

        $result = $response->json();
        if (!$response->successful() || !is_array($result)) {
            $message = is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'The RequestLab execution gateway rejected the request.';
            throw new McpException(
                'EXECUTION_GATEWAY_ERROR',
                $message,
                ['status' => $response->status()],
                $response->status() >= 400 && $response->status() < 500 ? $response->status() : 502,
            );
        }

        foreach (['request_id', 'environment_id', 'resolved_host', 'method', 'status', 'duration_ms', 'body'] as $field) {
            if (!array_key_exists($field, $result)) {
                throw new McpException(
                    'EXECUTION_EVIDENCE_INVALID',
                    'The RequestLab execution gateway returned incomplete evidence.',
                    ['missing' => $field],
                    502,
                );
            }
        }
        if ($result['request_id'] !== $requestId || $result['environment_id'] !== $environmentId || $result['method'] !== strtoupper($expectedMethod)) {
            throw new McpException(
                'EXECUTION_EVIDENCE_MISMATCH',
                'The RequestLab execution evidence does not match the approved request.',
                httpStatus: 502,
            );
        }
        if ($expectedHost !== null && !hash_equals(strtolower($expectedHost), strtolower((string) $result['resolved_host']))) {
            throw new McpException(
                'EXECUTION_HOST_MISMATCH',
                'The resolved execution host changed after approval.',
                httpStatus: 409,
            );
        }

        return $result;
    }
}
