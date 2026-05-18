<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_model_configs')
            ->where('provider', 'tokenrouter')
            ->where('model_id', 'anthropic/claude-sonnet-4.5')
            ->whereIn('feature', ['hook_generator', 'copywriting', 'insight'])
            ->update([
                'model_id' => 'deepseek/deepseek-v4-flash',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally keep the cheaper model on rollback to avoid surprise cost increases.
    }
};
