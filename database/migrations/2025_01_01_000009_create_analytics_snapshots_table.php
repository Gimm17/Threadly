<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('engagement')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0.00);
            $table->unsignedInteger('posts_published')->default(0);
            $table->unsignedBigInteger('saves')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'snapshot_date']);
            $table->index(['workspace_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
