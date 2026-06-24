<?php
namespace App\Services;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use RuntimeException;
class SafeDocumentDownloader {
 public function download(string $url,string $directory):array {
  $client=new Client(['timeout'=>20,'connect_timeout'=>8,'http_errors'=>false,'verify'=>true]);
  for($redirects=0;$redirects<=3;$redirects++){
   $this->assertSafeUrl($url);
   $response=$client->request('GET',$url,['allow_redirects'=>false,'headers'=>['Accept'=>'application/pdf,image/*','User-Agent'=>'ID-Document-Verifier/1.0']]);
   $code=$response->getStatusCode();
   if(in_array($code,[301,302,303,307,308],true)){$location=$response->getHeaderLine('Location');if(!$location)throw new RuntimeException('Document redirect has no destination.');$url=$this->resolveUrl($url,$location);continue;}
   if($code<200||$code>=300)throw new RuntimeException('Document server returned HTTP '.$code.'.');
   $declared=(int)$response->getHeaderLine('Content-Length');$max=config('ocr.max_download_bytes');
   if($declared>$max)throw new RuntimeException('Document exceeds the configured size limit.');
   $bytes=(string)$response->getBody(); if(strlen($bytes)>$max)throw new RuntimeException('Document exceeds the configured size limit.');
   $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/tiff'=>'tiff','image/webp'=>'webp'];
   if(!isset($extensions[$mime]))throw new RuntimeException('Unsupported document type: '.$mime.'.');
   $path=$directory.'/source.'.$extensions[$mime]; if(file_put_contents($path,$bytes)===false)throw new RuntimeException('Could not store the downloaded document.');
   return ['path'=>$path,'mime'=>$mime,'bytes'=>strlen($bytes)];
  }
  throw new RuntimeException('Too many document redirects.');
 }
 private function assertSafeUrl(string $url):void {
  $parts=parse_url($url);if(($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user']))throw new RuntimeException('Only public HTTPS document URLs are allowed.');
  if(filter_var($parts['host'],FILTER_VALIDATE_IP))$ips=[$parts['host']];else{$ips=gethostbynamel($parts['host'])?:[];}
  if(!$ips)throw new RuntimeException('Document host could not be resolved.');
  if(!config('ocr.allow_private_urls'))foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Private or reserved document hosts are not allowed.');
 }
 private function resolveUrl(string $base,string $location):string {return (string)UriResolver::resolve(new Uri($base),new Uri($location));}
}
