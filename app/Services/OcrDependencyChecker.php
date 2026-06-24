<?php
namespace App\Services;
use Symfony\Component\Process\Process;
class OcrDependencyChecker {
 public function status():array {
  $tools=['tesseract'=>config('ocr.tesseract'),'imagemagick'=>config('ocr.magick'),'poppler'=>config('ocr.pdftoppm')];$result=[];
  foreach($tools as $name=>$command){try{$versionFlag=$name==='poppler'?'-v':'--version';$p=new Process([$command,$versionFlag]);$p->setTimeout(3);$p->run();$result[$name]=['available'=>$p->isSuccessful(),'command'=>$command];}catch(\Throwable $e){$result[$name]=['available'=>false,'command'=>$command];}}
  return $result;
 }
}
