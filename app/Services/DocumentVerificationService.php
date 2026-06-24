<?php
namespace App\Services;
use App\Models\Verification;
use Illuminate\Support\Str;
use Throwable;
class DocumentVerificationService {
 private $downloader,$preprocessor,$ocr,$extractor,$comparator;
 public function __construct(SafeDocumentDownloader $downloader,ImagePreprocessor $preprocessor,TesseractOcr $ocr,BotswanaIdExtractor $extractor,IdentityComparator $comparator){$this->downloader=$downloader;$this->preprocessor=$preprocessor;$this->ocr=$ocr;$this->extractor=$extractor;$this->comparator=$comparator;}
 public function verify(array $input):Verification {
  $started=microtime(true);$id=(string)Str::uuid();$provided=['surname'=>$input['surname'],'forenames'=>$input['forenames'],'id_number'=>$input['id_number']];
  $record=Verification::create(['request_id'=>$id,'document_url_hash'=>hash('sha256',$input['document_url']),'provided_data'=>$provided,'status'=>'processing']);
  $directory=storage_path('app/verification/'.$id);if(!is_dir($directory))mkdir($directory,0700,true);
  try{
   $document=$this->downloader->download($input['document_url'],$directory);$images=$this->preprocessor->prepare($document['path'],$document['mime'],$directory);$ocr=$this->ocr->read($images);$extracted=$this->extractor->extractBest($ocr['variants']??[$ocr['text']]);$comparison=$this->comparator->compare($provided,$extracted);
   $record->update(['extracted_data'=>$extracted,'comparison'=>$comparison,'status'=>$comparison['status'],'ocr_confidence'=>$ocr['confidence'],'processing_ms'=>(int)((microtime(true)-$started)*1000)]);
  }catch(Throwable $e){$record->update(['status'=>'failed','failure_reason'=>$e->getMessage(),'processing_ms'=>(int)((microtime(true)-$started)*1000)]);}
  finally{if(!config('ocr.keep_files'))$this->removeDirectory($directory);}
  return $record->fresh();
 }
 private function removeDirectory(string $directory):void {if(!is_dir($directory))return;foreach(new \FilesystemIterator($directory) as $file){if($file->isDir())$this->removeDirectory($file->getPathname());else@unlink($file->getPathname());}@rmdir($directory);}
}
