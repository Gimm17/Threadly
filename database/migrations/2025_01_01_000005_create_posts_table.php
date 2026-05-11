<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_idea_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('hook', 160)->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed', 'cancelled'])->default('draft');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('threads_post_id')->nullable();
            $table->enum('publish_mode', ['manual', 'auto'])->default('manual');
            $table->dateTime('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('ai_model_used')->nullable();
            $table->string('link_url')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'scheduled_at']);
            $table->index(['workspace_id', 'content_pillar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
