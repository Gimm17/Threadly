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
                'model_id' => 'gpt-4o',
                'temperature' => 0.70,
                'max_tokens' => 1000,
                'system_prompt' => 'Kamu adalah pakar copywriting media sosial Indonesia. Buat hook yang singkat, menarik, dan memancing rasa penasaran audiens Threads. Gunakan bahasa Indonesia yang natural dan engaging. Maksimal 150 karakter.',
                'is_active' => true,
            ],
            [
                'feature' => 'copywriting',
                'provider' => 'tokenrouter',
                'model_id' => 'claude-3.5-sonnet',
                'temperature' => 0.50,
                'max_tokens' => 2500,
                'system_prompt' => 'Kamu adalah content writer profesional untuk brand Gimora Digital. Tulis konten Threads yang edukatif, engaging, dan sesuai brand voice: profesional tapi santai, informatif tapi tidak membosankan. Maksimal 500 karakter.',
                'is_active' => true,
            ],
            [
                'feature' => 'image_generation',
                'provider' => 'tokenrouter',
                'model_id' => 'google/gemini-3-pro-image-preview',
                'temperature' => 0.80,
                'max_tokens' => 500,
                'system_prompt' => null,
                'is_active' => true,
            ],
        ];

        foreach ($configs as $config) {
            AiModelConfig::withoutGlobalScopes()->create([
                ...$config,
                'workspace_id' => $workspace->id,
            ]);
        }
    }
}
