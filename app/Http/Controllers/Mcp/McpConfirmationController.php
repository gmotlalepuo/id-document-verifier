<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Auth\SupabaseAuthenticator;
use App\Mcp\Exceptions\McpException;
use App\Mcp\Execution\ApprovedWorkflowExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class McpConfirmationController extends Controller
{
    public function approve(
        string $confirmation,
        Request $request,
        SupabaseAuthenticator $authenticator,
        ApprovedWorkflowExecutor $executor,
    ): JsonResponse {
        try {
            $user = $authenticator->authenticate($request->header('Authorization'));
            $validator = Validator::make($request->json()->all(), [
                'plan_digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'idempotency_key' => ['required', 'string', 'min:16', 'max:120', 'regex:/^[A-Za-z0-9._:-]+$/'],
            ]);
            if ($validator->fails()) {
                throw new McpException(
                    'INVALID_APPROVAL',
                    'The confirmation approval payload is invalid.',
                    ['errors' => $validator->errors()->toArray()],
                    422,
                );
            }
            $values = $validator->validated();
            return response()->json(['data' => $executor->execute(
                $user,
                $confirmation,
                $values['plan_digest'],
                $values['idempotency_key'],
            )]);
        } catch (McpException $exception) {
            return response()->json([
                'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()] + $exception->details,
            ], $exception->httpStatus);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'The confirmed workflow could not be executed.'],
            ], 500);
        }
    }
}
