<?php
namespace App\Exceptions;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;
class Handler extends ExceptionHandler {
    protected $dontFlash=['password','password_confirmation'];
    public function register(){}
    public function render($request, Throwable $e){
        if($request->is('api/*')){
            if($e instanceof ValidationException) return response()->json(['success'=>false,'status'=>'invalid_request','message'=>'The request is invalid.','errors'=>$e->errors()],422);
            $status=method_exists($e,'getStatusCode')?$e->getStatusCode():500;
            return response()->json(['success'=>false,'status'=>'error','message'=>$status===500&&!config('app.debug')?'Document verification failed.':$e->getMessage()],$status);
        }
        return parent::render($request,$e);
    }
}
