<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_model_configs')
            ->where('provider', 'tokenrouter')
            ->where('feature', 'copywriting')
            ->whereIn('model_id', [
                'anthropic/claude-sonnet-4.5',
                'deepseek/deepseek-v4-flash',
            ])
            ->update([
                'model_id' => 'deepseek/deepseek-v4-pro',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Keep the lower-cost default on rollback to avoid surprise cost increases.
    }
};
