<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyDocumentRequest;
use App\Models\Verification;
use App\Services\DocumentVerificationService;
class VerificationController extends Controller {
 public function store(VerifyDocumentRequest $request,DocumentVerificationService $service){$v=$service->verify($request->validated());return response()->json($this->payload($v),$v->status==='failed'?422:200);}
 public function show($requestId){$v=Verification::where('request_id',$requestId)->firstOrFail();return response()->json($this->payload($v));}
 private function payload(Verification $v){return ['success'=>$v->status!=='failed','request_id'=>$v->request_id,'status'=>$v->status,'verified'=>$v->status==='verified','ocr'=>['confidence'=>$v->ocr_confidence,'quality'=>$this->quality($v->ocr_confidence)],'provided'=>$v->provided_data,'extracted'=>$v->extracted_data,'comparison'=>$v->comparison,'failure_reason'=>$v->failure_reason,'processing_ms'=>$v->processing_ms,'created_at'=>optional($v->created_at)->toIso8601String()];}
 private function quality($score){if($score===null)return null;if($score>=.85)return'high';if($score>=.65)return'medium';return'low';}
}
