<?php

namespace Database\Seeders;

use App\Models\AiModelConfig;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AiModelConfigSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        $configs = [
            [
                'feature' => 'hook_generator',
                'provider' => 'tokenrouter',
                'model_id' => 'deepseek/deepseek-v4-flash',
                'temperature' => 0.60,
                'max_tokens' => 700,
                'system_prompt' => 'Kamu adalah pakar copywriting Threads untuk brand Indonesia. Buat hook spesifik, singkat, tidak clickbait, dan maksimal 150 karakter.',
                'is_active' => true,
            ],
            [
                'feature' => 'copywriting',
                'provider' => 'tokenrouter',
                'model_id' => 'deepseek/deepseek-v4-pro',
                'temperature' => 0.50,
                'max_tokens' => 1200,
                'system_prompt' => 'Kamu adalah content writer profesional untuk brand Gimora Digital. Tulis konten Threads edukatif, praktis, natural, dan tidak berlebihan. Patuhi batas karakter yang diminta.',
                'is_active' => true,
            ],
            [
                'feature' => 'image_generation',
                'provider' => 'tokenrouter',
                'model_id' => 'google/gemini-3.1-flash-image-preview',
                'temperature' => 0.80,
                'max_tokens' => 4096,
                'system_prompt' => null,
                'is_active' => true,
            ],
            [
                'feature' => 'insight',
                'provider' => 'tokenrouter',
                'model_id' => 'deepseek/deepseek-v4-flash',
                'temperature' => 0.40,
                'max_tokens' => 900,
                'system_prompt' => 'Kamu adalah analis media sosial yang ahli di platform Threads (Meta). Berikan insight actionable dalam bahasa Indonesia yang singkat dan jelas. Selalu output dalam format JSON array yang valid.',
                'is_active' => true,
            ],
        ];

        foreach ($configs as $config) {
            AiModelConfig::withoutGlobalScopes()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'feature' => $config['feature'],
                ],
                $config,
            );
        }
    }
}
