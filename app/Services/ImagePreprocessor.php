<?php
namespace App\Services;
use Symfony\Component\Process\Process;
use RuntimeException;
class ImagePreprocessor {
 public function prepare(string $source,string $mime,string $directory):array {
  $inputs=[];
  if($mime==='application/pdf'){
   $prefix=$directory.'/page';$this->run([config('ocr.pdftoppm'),'-f','1','-l','3','-r','300','-png',$source,$prefix]);$inputs=glob($prefix.'-*.png')?:[];
  } else $inputs=[$source];
  if(!$inputs)throw new RuntimeException('No readable pages were found in the document.');
  $outputs=[];
  foreach($inputs as $i=>$input){$out=$directory.'/enhanced-'.$i.'.png';$this->run([config('ocr.magick'),$input,'-auto-orient','-colorspace','Gray','-resize','200%','-contrast-stretch','1%x1%','-sharpen','0x1','-deskew','40%',$out]);$outputs[]=$out;}
  return $outputs;
 }
 private function run(array $command):void {$p=new Process($command);$p->setTimeout(config('ocr.timeout'));$p->run();if(!$p->isSuccessful())throw new RuntimeException('Document preprocessing failed: '.trim($p->getErrorOutput()?:$p->getOutput()));}
}
