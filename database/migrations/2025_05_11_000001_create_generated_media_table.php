<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->default('image/png');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('prompt');
            $table->string('provider')->default('tokenrouter');
            $table->string('model_id');
            $table->string('style')->nullable(); // e.g. realistic, illustration, cartoon
            $table->string('aspect_ratio')->default('1:1');
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'type']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_media');
    }
};
