<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature');           // hook_generator, copywriting, insight, image_generation
            $table->string('model_id');          // e.g. deepseek/deepseek-v4-pro
            $table->string('provider')->default('tokenrouter');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();        // estimated cost USD
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
