<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        User::create([
            'name' => env('SEED_OWNER_NAME', 'Gimora Digital'),
            'email' => env('SEED_OWNER_EMAIL', 'admin@gimoradigital.id'),
            'password' => env('SEED_OWNER_PASSWORD', 'password'),
            'workspace_id' => $workspace->id,
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
