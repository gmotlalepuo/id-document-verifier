<?php
namespace App\Services;
use Symfony\Component\Process\Process;
use RuntimeException;
class TesseractOcr {
 public function read(array $images):array {
  $texts=[];$confidences=[];
  foreach($images as $image){
   $imageTexts=[];
   $p=new Process([config('ocr.tesseract'),$image,'stdout','-l',config('ocr.language'),'--oem','1','--psm','6','tsv']);$p->setTimeout(config('ocr.timeout'));$p->run();
   if(!$p->isSuccessful())throw new RuntimeException('OCR failed: '.trim($p->getErrorOutput()));
   $lines=preg_split('/\R/',trim($p->getOutput()));$words=[];
   foreach(array_slice($lines,1) as $line){$c=str_getcsv($line,"\t");if(count($c)<12)continue;$word=trim($c[11]);$confidence=(float)$c[10];if($word!==''&&$confidence>=0){$words[]=$word;$confidences[]=$confidence/100;}}
   foreach([6,11] as $pageSegmentationMode){
    $plain=new Process([config('ocr.tesseract'),$image,'stdout','-l',config('ocr.language'),'--oem','1','--psm',(string)$pageSegmentationMode,'preserve_interword_spaces=1']);
    $plain->setTimeout(config('ocr.timeout'));
    $plain->run();
    if($plain->isSuccessful()&&trim($plain->getOutput())!=='')$imageTexts[]=trim($plain->getOutput());
   }
   if(!$imageTexts)$imageTexts[]=implode(' ',$words);
   array_push($texts,...$imageTexts);
  }
  $confidence=$confidences?array_sum($confidences)/count($confidences):0.0;
  $texts=array_values(array_unique(array_filter($texts)));
  return ['text'=>implode("\n",$texts),'variants'=>$texts,'confidence'=>round($confidence,4)];
 }
}
