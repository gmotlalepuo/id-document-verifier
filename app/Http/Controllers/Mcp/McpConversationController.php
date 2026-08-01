<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Auth\SupabaseAuthenticator;
use App\Mcp\Conversation\ConversationService;
use App\Mcp\Exceptions\McpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class McpConversationController extends Controller
{
    public function index(Request $request, SupabaseAuthenticator $auth, ConversationService $service): JsonResponse
    {
        return $this->run(function () use ($request, $auth, $service) {
            $values = $this->validatedInput($request->query(), ['workspace_id' => ['required', 'uuid']]);
            return ['data' => $service->list($auth->authenticate($request->header('Authorization')), $values['workspace_id'])];
        });
    }

    public function store(Request $request, SupabaseAuthenticator $auth, ConversationService $service): JsonResponse
    {
        return $this->run(function () use ($request, $auth, $service) {
            $values = $this->validatedInput($request->json()->all(), [
                'workspace_id' => ['required', 'uuid'], 'collection_id' => ['nullable', 'uuid'],
                'environment_id' => ['nullable', 'uuid'], 'request_id' => ['nullable', 'uuid'], 'title' => ['nullable', 'string', 'max:120'],
            ]);
            return ['data' => $service->create($auth->authenticate($request->header('Authorization')), $values)];
        }, 201);
    }

    public function show(string $conversation, Request $request, SupabaseAuthenticator $auth, ConversationService $service): JsonResponse
    {
        return $this->run(fn () => ['data' => $service->get($auth->authenticate($request->header('Authorization')), $conversation)]);
    }

    public function message(string $conversation, Request $request, SupabaseAuthenticator $auth, ConversationService $service): JsonResponse
    {
        return $this->run(function () use ($conversation, $request, $auth, $service) {
            $values = $this->validatedInput($request->json()->all(), [
                'role' => ['required', 'in:user,agent,error'], 'text' => ['required', 'string', 'max:20000'],
                'metadata' => ['nullable', 'array'],
            ]);
            return ['data' => $service->append($auth->authenticate($request->header('Authorization')), $conversation, $values['role'], $values['text'], $values['metadata'] ?? null)];
        }, 201);
    }

    public function destroy(string $conversation, Request $request, SupabaseAuthenticator $auth, ConversationService $service): JsonResponse
    {
        return $this->run(function () use ($conversation, $request, $auth, $service) {
            $service->archive($auth->authenticate($request->header('Authorization')), $conversation);
            return ['data' => ['archived' => true]];
        });
    }

    private function validatedInput(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) throw new McpException('INVALID_TOOL_ARGUMENTS', 'The conversation request is invalid.', httpStatus: 422);
        return $validator->validated();
    }

    private function run(callable $callback, int $success = 200): JsonResponse
    {
        try { return response()->json($callback(), $success); }
        catch (McpException $e) { return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->httpStatus); }
        catch (Throwable $e) { report($e); return response()->json(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'The conversation request could not be completed.']], 500); }
    }
}
