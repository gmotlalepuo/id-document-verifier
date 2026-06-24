<?php
namespace App\Http;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
class Kernel extends HttpKernel {
    protected $middleware=[\App\Http\Middleware\TrustProxies::class,\Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class];
    protected $middlewareGroups=['api'=>['throttle:60,1',\Illuminate\Routing\Middleware\SubstituteBindings::class]];
    protected $routeMiddleware=['document.api.key'=>\App\Http\Middleware\RequireDocumentVerificationApiKey::class,'throttle'=>\Illuminate\Routing\Middleware\ThrottleRequests::class];
}
