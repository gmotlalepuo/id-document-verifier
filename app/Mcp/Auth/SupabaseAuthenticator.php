<?php

namespace App\Mcp\Auth;

use App\Mcp\Exceptions\McpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SupabaseAuthenticator
{
    public function authenticate(?string $authorization): AuthenticatedUser
    {
        $token = $this->bearerToken($authorization);
        $url = config('mcp.supabase_url');
        $key = config('mcp.supabase_publishable_key');

        if (!$url || !$key) {
            throw new McpException(
                'MCP_NOT_CONFIGURED',
                'The MCP server is not configured for Supabase authentication.',
                httpStatus: 503,
            );
        }

        try {
            $response = Http::baseUrl($url)
                ->acceptJson()
                ->withHeaders(['apikey' => $key])
                ->withToken($token)
                ->timeout(config('mcp.timeout_seconds'))
                ->get('/auth/v1/user');
        } catch (ConnectionException) {
            throw new McpException(
                'AUTH_SERVICE_UNAVAILABLE',
                'Supabase authentication is temporarily unavailable.',
                httpStatus: 503,
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new McpException('AUTH_REQUIRED', 'A valid Supabase session is required.', httpStatus: 401);
        }

        if (!$response->successful()) {
            throw new McpException(
                'AUTH_SERVICE_UNAVAILABLE',
                'Supabase authentication could not be completed.',
                httpStatus: 503,
            );
        }

        $id = $response->json('id');
        if (!is_string($id) || $id === '') {
            throw new McpException('AUTH_REQUIRED', 'The authenticated user is invalid.', httpStatus: 401);
        }

        return new AuthenticatedUser($id, $response->json('email'), $token);
    }

    private function bearerToken(?string $authorization): string
    {
        if (!is_string($authorization) || !preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)) {
            throw new McpException('AUTH_REQUIRED', 'A Supabase bearer token is required.', httpStatus: 401);
        }

        return $matches[1];
    }
}
