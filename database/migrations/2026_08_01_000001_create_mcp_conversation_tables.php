<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('workspace_id')->index();
            $table->uuid('collection_id')->nullable()->index();
            $table->uuid('environment_id')->nullable()->index();
            $table->uuid('request_id')->nullable()->index();
            $table->string('title', 120);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'workspace_id', 'last_message_at'], 'mcp_conversation_scope_index');
        });

        Schema::create('mcp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->index();
            $table->string('role', 16);
            $table->text('content');
            $table->text('metadata')->nullable();
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('mcp_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_messages');
        Schema::dropIfExists('mcp_conversations');
    }
};
