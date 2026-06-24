<?php
namespace App\Http\Middleware;

use Closure;

class RequireDocumentVerificationApiKey
{
    public function handle($request, Closure $next)
    {
        $expected = (string) config('ocr.api_key');
        $provided = (string) $request->header('X-API-Key');

        if ($expected === '' || $expected === 'change-me' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'status' => 'unauthorized',
                'message' => 'A valid X-API-Key header is required.',
            ], 401);
        }

        return $next($request);
    }
}
