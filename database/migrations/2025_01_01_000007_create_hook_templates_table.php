<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hook_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->text('hook_text');
            $table->enum('category', ['question', 'controversial', 'story', 'statistic', 'how_to', 'listicle', 'pov', 'other'])->default('other');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('save_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_ai_generated')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'score']);
            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hook_templates');
    }
};
