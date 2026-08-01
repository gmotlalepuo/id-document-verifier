<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Auth\AuthenticatedUser;
use App\Mcp\Auth\SupabaseAuthenticator;
use App\Mcp\Exceptions\McpException;
use App\Mcp\Tools\McpToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class McpController extends Controller
{
    public function __construct(
        private readonly SupabaseAuthenticator $authenticator,
        private readonly McpToolRegistry $tools,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $id = $payload['id'] ?? null;

        if (($payload['jsonrpc'] ?? null) !== '2.0' || !is_string($payload['method'] ?? null)) {
            return $this->protocolError($id, -32600, 'Invalid JSON-RPC request.');
        }
        try {
            $user = $this->authenticator->authenticate($request->header('Authorization'));
            if (!array_key_exists('id', $payload)) {
                return response()->json(null, 202);
            }
            $result = match ($payload['method']) {
                'initialize' => $this->initialize(),
                'ping' => (object) [],
                'tools/list' => ['tools' => $this->tools->definitions()],
                'tools/call' => $this->callTool($payload['params'] ?? [], $user),
                default => throw new McpException('METHOD_NOT_FOUND', 'Unknown MCP method: '.$payload['method'].'.', httpStatus: 404),
            };
            return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
        } catch (McpException $exception) {
            if ($payload['method'] === 'tools/call' && !in_array($exception->errorCode, ['AUTH_REQUIRED', 'AUTH_SERVICE_UNAVAILABLE', 'MCP_NOT_CONFIGURED'], true)) {
                return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $this->toolError($exception)]);
            }
            return $this->protocolError(
                $id,
                $exception->errorCode === 'METHOD_NOT_FOUND' ? -32601 : -32000,
                $exception->getMessage(),
                ['code' => $exception->errorCode] + $exception->details,
                $exception->httpStatus,
            );
        } catch (Throwable $exception) {
            report($exception);
            return $this->protocolError($id, -32603, 'Internal MCP server error.', status: 500);
        }
    }

    private function initialize(): array
    {
        return [
            'protocolVersion' => config('mcp.protocol_version'),
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => config('mcp.name'), 'version' => config('mcp.version')],
            'instructions' => config('mcp.execution.enabled')
                ? 'Use reviewed tools only. prepare_issue_license may run its UAT lookup and create a confirmation preview, but it cannot perform the write. A user must approve the exact plan through the separate approval route. Secrets are redacted.'
                : 'Use discovery and read tools only. plan_request uses local Qwen to produce a validated plan but never executes it. Values classified as secrets are always redacted. External request execution and mutations are disabled.',
        ];
    }

    private function callTool(mixed $params, AuthenticatedUser $user): array
    {
        if (!is_array($params) || !is_string($params['name'] ?? null) || !is_array($params['arguments'] ?? [])) {
            throw new McpException('INVALID_TOOL_ARGUMENTS', 'A tool name and arguments object are required.', httpStatus: 422);
        }
        $data = $this->tools->call($params['name'], $params['arguments'] ?? [], $user);
        return [
            'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]],
            'structuredContent' => ['data' => $data],
            'isError' => false,
        ];
    }

    private function toolError(McpException $exception): array
    {
        $error = ['code' => $exception->errorCode, 'message' => $exception->getMessage()] + $exception->details;
        return [
            'content' => [['type' => 'text', 'text' => json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]],
            'structuredContent' => ['error' => $error],
            'isError' => true,
        ];
    }

    private function protocolError(mixed $id, int $code, string $message, array $data = [], int $status = 400): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data) {
            $error['data'] = $data;
        }
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error], $status);
    }
}
