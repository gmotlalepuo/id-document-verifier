<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Auth\SupabaseAuthenticator;
use App\Mcp\Data\RequestLabRepository;
use App\Mcp\Exceptions\McpException;
use App\Models\McpExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class McpExecutionAuditController extends Controller
{
    public function index(Request $request, SupabaseAuthenticator $auth, RequestLabRepository $repository): JsonResponse
    {
        try {
            $workspaceId = $request->query('workspace_id');
            if (!is_string($workspaceId) || !preg_match('/^[0-9a-f-]{36}$/i', $workspaceId)) throw new McpException('INVALID_TOOL_ARGUMENTS', 'A valid workspace is required.', httpStatus: 422);
            $user = $auth->authenticate($request->header('Authorization'));
            $visible = collect($repository->forUser($user)->listWorkspaces())->contains(fn ($row) => ($row['id'] ?? null) === $workspaceId);
            if (!$visible) throw new McpException('WORKSPACE_ACCESS_DENIED', 'The workspace is unavailable.', httpStatus: 403);
            $items = McpExecution::query()->where('user_id', $user->id)->where('workspace_id', $workspaceId)
                ->orderByDesc('created_at')->limit(50)->get()->map(fn ($item) => [
                    'id' => $item->id, 'workflow_name' => $item->workflow_name, 'workflow_version' => $item->workflow_version,
                    'method' => $item->method, 'resolved_host' => $item->resolved_host, 'status' => $item->status,
                    'duration_ms' => $item->duration_ms, 'upstream_status' => $item->upstream_status,
                    'outcome_summary' => $item->outcome_summary, 'created_at' => $item->created_at->toIso8601String(),
                ])->all();
            return response()->json(['data' => $items]);
        } catch (McpException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->httpStatus);
        } catch (Throwable $e) {
            report($e); return response()->json(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Execution history could not be loaded.']], 500);
        }
    }
}
