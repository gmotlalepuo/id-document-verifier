<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Verification extends Model {
    protected $fillable=['request_id','document_url_hash','provided_data','extracted_data','comparison','status','ocr_confidence','failure_reason','processing_ms'];
    protected $casts=['provided_data'=>'array','extracted_data'=>'array','comparison'=>'array','ocr_confidence'=>'float','processing_ms'=>'integer'];
}
