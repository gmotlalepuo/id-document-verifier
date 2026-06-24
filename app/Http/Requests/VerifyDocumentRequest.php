<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class VerifyDocumentRequest extends FormRequest {
 public function authorize(){return true;}
 public function rules(){return ['document_url'=>['required','url','max:2048','regex:/^https:\/\//i'],'surname'=>'required|string|max:100','forenames'=>'required|string|max:200','id_number'=>'required|string|min:5|max:30'];}
 public function messages(){return ['document_url.regex'=>'The document URL must use HTTPS.'];}
}
