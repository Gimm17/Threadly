<?php

namespace Database\Seeders;

use App\Models\ContentPillar;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ContentPillarSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        $pillars = [
            [
                'name' => 'Tech Tips',
                'description' => 'Tips & Trik Teknologi',
                'color_hex' => '#3b82f6',
                'icon' => 'device-desktop',
                'frequency_unit' => 'week',
                'frequency_value' => 2,
                'sort_order' => 1,
            ],
            [
                'name' => 'Case Study',
                'description' => 'Analisis Mendalam',
                'color_hex' => '#1a3263',
                'icon' => 'chart-bar',
                'frequency_unit' => 'week',
                'frequency_value' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => 'Edukasi AI',
                'description' => 'Panduan Penggunaan AI',
                'color_hex' => '#f59e0b',
                'icon' => 'bulb',
                'frequency_unit' => 'week',
                'frequency_value' => 3,
                'sort_order' => 3,
            ],
            [
                'name' => 'Behind the Scenes',
                'description' => 'Kehidupan Kantor',
                'color_hex' => '#6b7280',
                'icon' => 'camera',
                'frequency_unit' => 'month',
                'frequency_value' => 2,
                'sort_order' => 4,
            ],
            [
                'name' => 'Promo',
                'description' => 'Penawaran Spesial',
                'color_hex' => '#ef4444',
                'icon' => 'speakerphone',
                'frequency_unit' => 'month',
                'frequency_value' => 1,
                'sort_order' => 5,
            ],
        ];

        foreach ($pillars as $pillar) {
            ContentPillar::withoutGlobalScopes()->create([
                ...$pillar,
                'workspace_id' => $workspace->id,
            ]);
        }
    }
}
