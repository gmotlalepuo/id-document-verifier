<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Mcp\McpController;
use App\Http\Controllers\Mcp\McpConfirmationController;
use App\Http\Controllers\Mcp\McpConversationController;
use App\Http\Controllers\Mcp\McpExecutionAuditController;
use App\Services\OcrDependencyChecker;
Route::get('/v1/health',function(OcrDependencyChecker $checker){$dependencies=$checker->status();$ready=!in_array(false,array_column($dependencies,'available'),true);return response()->json(['status'=>$ready?'ready':'not_ready','service'=>'id-document-verifier','dependencies'=>$dependencies],$ready?200:503);});
Route::post('/mcp', [McpController::class, 'handle']);
Route::post('/mcp/confirmations/{confirmation}/approve', [McpConfirmationController::class, 'approve'])
    ->whereUuid('confirmation');
Route::get('/mcp/conversations', [McpConversationController::class, 'index']);
Route::post('/mcp/conversations', [McpConversationController::class, 'store']);
Route::get('/mcp/conversations/{conversation}', [McpConversationController::class, 'show'])->whereUuid('conversation');
Route::post('/mcp/conversations/{conversation}/messages', [McpConversationController::class, 'message'])->whereUuid('conversation');
Route::delete('/mcp/conversations/{conversation}', [McpConversationController::class, 'destroy'])->whereUuid('conversation');
Route::get('/mcp/executions', [McpExecutionAuditController::class, 'index']);
Route::middleware('document.api.key')->group(function(){
    Route::post('/v1/verifications',[VerificationController::class,'store']);
    Route::get('/v1/verifications/{requestId}',[VerificationController::class,'show'])->whereUuid('requestId');
});
