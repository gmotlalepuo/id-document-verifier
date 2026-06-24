<?php
namespace Tests\Unit;
use App\Services\BotswanaIdExtractor;
use PHPUnit\Framework\TestCase;
class BotswanaIdExtractorTest extends TestCase {
 public function test_extracts_front_fields(){ $r=(new BotswanaIdExtractor)->extract("REPUBLIC OF BOTSWANA NATIONAL IDENTITY CARD\nID NUMBER 436415528\nSURNAME MOTLALEPUO\nFORENAMES GARENOSI\nDATE OF BIRTH 24/06/1996");$this->assertSame('436415528',$r['id_number']);$this->assertSame('MOTLALEPUO',$r['surname']);$this->assertSame('GARENOSI',$r['forenames']);}
 public function test_uses_mrz_names(){ $r=(new BotswanaIdExtractor)->extract("9606243M3207213BWA05695622<<<<7\nMOTLALEPUO<<GARENOSI<<<<<<<<");$this->assertSame('MOTLALEPUO',$r['surname']);$this->assertSame('GARENOSI',$r['forenames']);}
 public function test_removes_leading_x_only_when_another_ocr_pass_supports_it(){ $r=(new BotswanaIdExtractor)->extractBest(["SURNAME JANUARIE FORENAMES LEETALIA XCHRIZELDA DATE OF BIRTH", "SURNAME JANUARIE FORENAMES LEETALIA CHRIZELDA DATE OF BIRTH"]);$this->assertSame('LEETALIA CHRIZELDA',$r['forenames']);}
 public function test_preserves_legitimate_x_name_when_ocr_passes_agree(){ $r=(new BotswanaIdExtractor)->extractBest(["SURNAME EXAMPLE FORENAMES LEETALIA XOLISWA DATE OF BIRTH", "SURNAME EXAMPLE FORENAMES LEETALIA XOLISWA DATE OF BIRTH"]);$this->assertSame('LEETALIA XOLISWA',$r['forenames']);}
}
