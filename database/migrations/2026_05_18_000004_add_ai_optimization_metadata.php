<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->unsignedInteger('estimated_input_tokens')->nullable()->after('total_tokens');
            $table->unsignedInteger('estimated_output_tokens')->nullable()->after('estimated_input_tokens');
            $table->boolean('cache_hit')->default(false)->after('estimated_output_tokens');
            $table->string('cost_source')->nullable()->after('cost');
        });

        Schema::table('generated_media', function (Blueprint $table) {
            $table->text('original_prompt')->nullable()->after('prompt');
            $table->longText('enhanced_prompt')->nullable()->after('original_prompt');
            $table->text('negative_prompt')->nullable()->after('enhanced_prompt');
            $table->json('model_params')->nullable()->after('generation_mode');
            $table->json('overlay_config')->nullable()->after('model_params');
            $table->string('prompt_template_version')->nullable()->after('overlay_config');
            $table->string('generation_status')->default('completed')->after('prompt_template_version');
            $table->decimal('estimated_cost', 10, 6)->nullable()->after('generation_status');
            $table->text('error_message')->nullable()->after('estimated_cost');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_input_tokens',
                'estimated_output_tokens',
                'cache_hit',
                'cost_source',
            ]);
        });

        Schema::table('generated_media', function (Blueprint $table) {
            $table->dropColumn([
                'original_prompt',
                'enhanced_prompt',
                'negative_prompt',
                'model_params',
                'overlay_config',
                'prompt_template_version',
                'generation_status',
                'estimated_cost',
                'error_message',
            ]);
        });
    }
};
