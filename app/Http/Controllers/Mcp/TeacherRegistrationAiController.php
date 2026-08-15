<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Exceptions\McpException;
use App\Mcp\Auth\SupabaseAuthenticator;
use App\Mcp\TeacherRegistration\TeacherRegistrationSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TeacherRegistrationAiController extends Controller
{
    public function __construct(
        private readonly TeacherRegistrationSummaryService $summaries,
        private readonly SupabaseAuthenticator $supabase,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $token = preg_replace('/^Bearer\s+/i', '', trim((string) $request->header('Authorization', ''))) ?: '';
        $configured = (string) config('mcp.teacher_registration.service_token');
        $serviceAuthorized = $configured !== '' && $token !== '' && hash_equals($configured, $token);
        if (!$serviceAuthorized) {
            try { $this->supabase->authenticate($request->header('Authorization')); }
            catch (McpException $exception) { return response()->json(['error' => 'AUTH_REQUIRED', 'message' => 'A valid service token or Supabase bearer token is required.'], 401); }
        }
        $validator = Validator::make($request->json()->all(), ['national_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{3,64}$/'], 'teacher_registration' => ['required', 'array'], 'comments' => ['present', 'array'], 'audit_trails' => ['present', 'array'], 'options' => ['sometimes', 'array']]);
        if ($validator->fails()) return response()->json(['error' => 'VALIDATION_ERROR', 'message' => 'The Teacher Registration payload is invalid.', 'details' => $validator->errors()->toArray()], 422);
        try { return response()->json($this->summaries->summarize($validator->validated())); }
        catch (McpException $exception) { return response()->json(['error' => $exception->errorCode, 'message' => $exception->getMessage()], $exception->httpStatus); }
        catch (Throwable $exception) { report($exception); return response()->json(['error' => 'SUMMARY_UNAVAILABLE', 'message' => 'The AI summary could not be generated.'], 502); }
    }
}
