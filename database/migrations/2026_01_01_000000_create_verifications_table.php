<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateVerificationsTable extends Migration {
 public function up(){ Schema::create('verifications',function(Blueprint $t){$t->id();$t->uuid('request_id')->unique();$t->string('document_url_hash',64);$t->json('provided_data');$t->json('extracted_data')->nullable();$t->json('comparison')->nullable();$t->string('status',32)->index();$t->decimal('ocr_confidence',5,4)->nullable();$t->text('failure_reason')->nullable();$t->unsignedInteger('processing_ms')->nullable();$t->timestamps();}); }
 public function down(){Schema::dropIfExists('verifications');}
}
