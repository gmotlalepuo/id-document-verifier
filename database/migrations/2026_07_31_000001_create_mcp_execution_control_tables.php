<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->char('plan_digest', 64);
            $table->json('preview');
            $table->text('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mcp_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('workspace_id')->index();
            $table->uuid('collection_id')->index();
            $table->uuid('request_id')->index();
            $table->uuid('environment_id')->index();
            $table->string('workflow_name', 120)->nullable();
            $table->string('workflow_version', 40)->nullable();
            $table->string('resolved_host', 255);
            $table->string('method', 10);
            $table->string('autonomy_mode', 32);
            $table->uuid('confirmation_id')->nullable()->unique();
            $table->uuid('confirmed_by')->nullable();
            $table->string('idempotency_key', 120);
            $table->char('operation_digest', 64);
            $table->string('status', 32)->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('upstream_status')->nullable();
            $table->json('outcome_summary')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'mcp_execution_user_idempotency_unique');
            $table->foreign('confirmation_id')->references('id')->on('mcp_confirmations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_executions');
        Schema::dropIfExists('mcp_confirmations');
    }
};
