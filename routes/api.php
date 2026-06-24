<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VerificationController;
use App\Services\OcrDependencyChecker;
Route::get('/v1/health',function(OcrDependencyChecker $checker){$dependencies=$checker->status();$ready=!in_array(false,array_column($dependencies,'available'),true);return response()->json(['status'=>$ready?'ready':'not_ready','service'=>'id-document-verifier','dependencies'=>$dependencies],$ready?200:503);});
Route::middleware('document.api.key')->group(function(){
    Route::post('/v1/verifications',[VerificationController::class,'store']);
    Route::get('/v1/verifications/{requestId}',[VerificationController::class,'show'])->whereUuid('requestId');
});
