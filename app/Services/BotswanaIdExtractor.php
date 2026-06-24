<?php
namespace App\Services;
class BotswanaIdExtractor {
 public function extractBest(array $texts):array {
  $results=array_map(fn($text)=>$this->extract((string)$text),array_filter($texts));
  if(!$results)return $this->extract('');
  $selected=$results[0];
  foreach(array_slice($results,1) as $candidate){
   $selected['forenames']=$this->preferSupportedForenames($selected['forenames'],$candidate['forenames']);
  }
  return $selected;
 }

 public function extract(string $text):array {
  $upper=strtoupper(str_replace(["\r",'|'],["\n",'I'],$text));$flat=preg_replace('/[ \t]+/',' ',$upper);
  $id=$this->firstMatch(["/(?:ID\s*(?:NUMBER|NO)?|IDENTITY\s*(?:NUMBER|NO)?)[^0-9]{0,15}([0-9OIL]{9})/","/\b([0-9]{9})\b/"],$flat,true);
  $surname=$this->firstMatch(["/SURNAME\s*[:\-]?\s*([A-Z][A-Z' -]{1,50}?)(?=\s+(?:FORENAMES?|GIVEN|DATE|SEX|NATIONALITY|$))/","/\n\s*([A-Z]{2,})<<[A-Z<]+/"],$upper);
  $forenames=$this->firstMatch(["/(?:FORENAMES?|GIVEN\s*NAMES?)\s*[:\-]?\s*([A-Z][A-Z' -]{1,80}?)(?=\s+(?:DATE|SEX|PLACE|NATIONALITY|$))/"],$flat);
  if(preg_match('/\b([A-Z]{2,})<<([A-Z<]{2,})/',$upper,$m)){if(!$surname)$surname=$m[1];if(!$forenames)$forenames=str_replace('<',' ',trim($m[2],'<'));}
  return ['surname'=>$this->cleanName($surname),'forenames'=>$this->cleanName($forenames),'id_number'=>$id,'document_type'=>stripos($upper,'BOTSWANA')!==false?'botswana_identity_document':'unknown'];
 }
 private function firstMatch(array $patterns,string $text,bool $digits=false){foreach($patterns as $p)if(preg_match($p,$text,$m)){if($digits)return strtr($m[1],['O'=>'0','I'=>'1','L'=>'1']);return trim($m[1]);}return null;}
 private function cleanName($value){if(!$value)return null;$value=preg_replace('/[^A-Z\' -]/',' ',$value);return trim(preg_replace('/\s+/',' ',$value));}
 private function preferSupportedForenames($first,$second){
  if(!$first)return $second;
  if(!$second||$first===$second)return $first;
  $a=explode(' ',$first);$b=explode(' ',$second);
  if(count($a)!==count($b))return $first;
  $differences=0;$preferred=$a;
  foreach($a as $index=>$token){
   if($token===$b[$index])continue;
   $differences++;
   if($differences>1)return $first;
   if(str_starts_with($token,'X')&&substr($token,1)===$b[$index])$preferred[$index]=$b[$index];
   elseif(str_starts_with($b[$index],'X')&&substr($b[$index],1)===$token)$preferred[$index]=$token;
   else return $first;
  }
  return $differences===1?implode(' ',$preferred):$first;
 }
}
