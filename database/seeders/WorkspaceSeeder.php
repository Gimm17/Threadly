<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::create([
            'name' => env('SEED_WORKSPACE_NAME', 'Gimora Digital'),
            'threads_handle' => env('SEED_THREADS_HANDLE', 'gimoradigital.id'),
            'timezone' => 'Asia/Jakarta',
            'plan' => 'pro',
            'settings' => [
                'post_reminder_minutes' => 30,
                'default_publish_mode' => 'manual',
            ],
        ]);
    }
}
