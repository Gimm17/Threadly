<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WorkspaceSeeder::class,
            UserSeeder::class,
            ContentPillarSeeder::class,
            AiModelConfigSeeder::class,
            DummyDataSeeder::class,
        ]);
    }
}
