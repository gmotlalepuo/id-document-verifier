<?php
namespace App\Services;
class IdentityComparator {
 public function compare(array $provided,array $extracted):array {
  $fields=[];$fields['surname']=$this->nameField($provided['surname'],$extracted['surname']??null);
  $fields['forenames']=$this->forenamesField($provided['forenames'],$extracted['forenames']??null);
  $fields['id_number']=$this->idField($provided['id_number'],$extracted['id_number']??null);
  $comparable=array_filter($fields,function($f){return $f['comparable'];});$mismatches=array_filter($comparable,function($f){return !$f['match'];});
  if(count($comparable)<count($fields))$status='inconclusive';elseif($mismatches)$status='mismatch';else$status='verified';
  return ['status'=>$status,'all_fields_match'=>$status==='verified','fields'=>$fields,'summary'=>['matched'=>count(array_filter($comparable,function($f){return $f['match'];})),'mismatched'=>count($mismatches),'unable_to_compare'=>count($fields)-count($comparable)]];
 }
 private function nameField($expected,$actual){if(!$actual)return $this->missing($expected);$a=$this->normalizeName($expected);$b=$this->normalizeName($actual);$score=$this->similarity($a,$b);return $this->result($expected,$actual,$score,$score>=config('ocr.min_score'));}
 private function forenamesField($expected,$actual){
  if(!$actual)return $this->missing($expected);

  $provided=$this->tokens($expected);
  $document=$this->tokens($actual);
  $available=$document;
  $matched=0;
  $similarityTotal=0.0;

  foreach($provided as $token){
   $bestScore=0.0;
   $bestIndex=null;
   foreach($available as $index=>$candidate){
    $candidateScore=$this->similarity($token,$candidate);
    if($candidateScore>$bestScore){$bestScore=$candidateScore;$bestIndex=$index;}
   }
   $similarityTotal+=$bestScore;
   if($bestIndex!==null){unset($available[$bestIndex]);}
   if($bestScore>=config('ocr.min_score')){$matched++;}
  }

  $largestCount=max(count($provided),count($document));
  $score=$largestCount>0?$similarityTotal/$largestCount:0.0;
  $sameCount=count($provided)===count($document);
  $match=$sameCount&&$matched===count($provided);

  if(count($provided)<count($document)){
   $reason='The information provided is missing a middle name shown on the document.';
  }elseif(count($provided)>count($document)){
   $reason='A middle name in the provided information is not present on the document.';
  }else{
   $reason=$match?'All forenames match within the configured tolerance.':'The forenames do not match within the configured tolerance.';
  }

  return $this->result($expected,$actual,$score,$match,$reason);
 }
 private function idField($expected,$actual){if(!$actual)return $this->missing($expected);$a=preg_replace('/\D/','',$expected);$b=preg_replace('/\D/','',$actual);return $this->result($expected,$actual,$a===$b?1.0:0.0,$a!==''&&$a===$b);}
 private function normalizeName($v){$v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v);return trim(preg_replace('/\s+/',' ',preg_replace('/[^A-Z ]/',' ',strtoupper($v))));}
 private function tokens($v){return array_values(array_filter(explode(' ',$this->normalizeName($v))));}
 private function similarity($a,$b){if($a===''||$b==='')return 0.0;$max=max(strlen($a),strlen($b));return round(1-(levenshtein($a,$b)/$max),4);}
 private function missing($expected){return ['provided'=>$expected,'extracted'=>null,'comparable'=>false,'match'=>null,'score'=>null,'reason'=>'The field could not be reliably extracted from the document.'];}
 private function result($expected,$actual,$score,$match,$reason=null){return ['provided'=>$expected,'extracted'=>$actual,'comparable'=>true,'match'=>$match,'score'=>round($score,4),'reason'=>$reason??($match?'Values match within the configured tolerance.':'Values do not match within the configured tolerance.')];}
}
