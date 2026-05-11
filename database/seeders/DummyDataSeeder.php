<?php

namespace Database\Seeders;

use App\Models\AnalyticsSnapshot;
use App\Models\ContentIdea;
use App\Models\ContentPillar;
use App\Models\HookTemplate;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();
        $user = User::first();
        $pillars = ContentPillar::withoutGlobalScopes()->where('workspace_id', $workspace->id)->get();

        // ─── Analytics Snapshots (30 days) ───
        $baseFollowers = 2663;
        for ($i = 30; $i >= 0; $i--) {
            $dailyGain = rand(3, 12);
            $baseFollowers += $dailyGain;

            AnalyticsSnapshot::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'snapshot_date' => now()->subDays($i)->toDateString(),
                'followers_count' => $baseFollowers,
                'following_count' => rand(180, 200),
                'impressions' => rand(1200, 2400),
                'reach' => rand(800, 1600),
                'engagement' => rand(150, 500),
                'engagement_rate' => round(rand(480, 720) / 100, 2),
                'posts_published' => rand(0, 3),
                'saves' => rand(20, 80),
                'shares' => rand(10, 40),
            ]);
        }

        // ─── Hook Templates ───
        $hooks = [
            ['hook_text' => 'Berhenti lakuin 3 kesalahan ini kalau akun IG kamu mau berkembang di 2024...', 'category' => 'listicle', 'score' => 95, 'save_count' => 342, 'view_count' => 12400],
            ['hook_text' => 'Rahasia bikin konten 1 bulan cuma dalam 2 jam (Tools & Template)...', 'category' => 'how_to', 'score' => 88, 'save_count' => 289, 'view_count' => 9800],
            ['hook_text' => 'POV: Kamu nemu AI yang bisa mikirin ide konten otomatis.', 'category' => 'pov', 'score' => 82, 'save_count' => 156, 'view_count' => 15200],
        ];

        foreach ($hooks as $hook) {
            HookTemplate::withoutGlobalScopes()->create([
                ...$hook,
                'workspace_id' => $workspace->id,
                'usage_count' => rand(5, 20),
            ]);
        }

        // ─── Content Ideas ───
        $ideas = [
            ['title' => '5 Cara Optimasi Profil LinkedIn', 'status' => 'draft', 'pillar_name' => 'Tech Tips'],
            ['title' => 'Kenapa AI Tidak Akan Menggantimu', 'status' => 'in_progress', 'pillar_name' => 'Edukasi AI'],
            ['title' => 'Behind The Scenes: Shooting Iklan', 'status' => 'draft', 'pillar_name' => 'Behind the Scenes'],
            ['title' => 'Promo Mid-Year Sale Diskon 50%', 'status' => 'draft', 'pillar_name' => 'Promo'],
        ];

        foreach ($ideas as $idea) {
            $pillar = $pillars->firstWhere('name', $idea['pillar_name']);
            ContentIdea::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'content_pillar_id' => $pillar?->id,
                'title' => $idea['title'],
                'status' => $idea['status'],
                'target_date' => now()->addDays(rand(1, 14))->toDateString(),
                'created_by' => $user->id,
            ]);
        }

        // ─── Posts ───
        $posts = [
            [
                'body' => 'Mulai kehabisan ide konten? Coba gunakan 5 prompt ChatGPT ini untuk menghasilkan ide-ide segar dan menarik audiens Anda. Simpan post ini untuk referensi nanti! #ChatGPT #ContentCreator #AI #GimoraDigital',
                'hook' => '5 Prompt ChatGPT untuk Content Creator',
                'status' => 'scheduled',
                'scheduled_at' => now()->addDay()->setTime(9, 0),
                'pillar_name' => 'Edukasi AI',
                'publish_mode' => 'manual',
            ],
            [
                'body' => 'Engagement rate stagnan? Coba strategi jitu ini untuk kembali berinteraksi aktif dengan audiens...',
                'hook' => 'Cara Meningkatkan Engagement Rate di Instagram',
                'status' => 'draft',
                'scheduled_at' => null,
                'pillar_name' => 'Tech Tips',
                'publish_mode' => 'manual',
            ],
            [
                'body' => 'Kenali tim hebat di balik kesuksesan kampanye klien-klien kami. Inilah suasana kerja kami...',
                'hook' => 'Tim Gimora Digital: Bekerja di Balik Layar',
                'status' => 'published',
                'scheduled_at' => now()->setTime(10, 0),
                'pillar_name' => 'Behind the Scenes',
                'publish_mode' => 'manual',
            ],
        ];

        foreach ($posts as $post) {
            $pillar = $pillars->firstWhere('name', $post['pillar_name']);
            Post::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'body' => $post['body'],
                'hook' => $post['hook'],
                'status' => $post['status'],
                'scheduled_at' => $post['scheduled_at'],
                'published_at' => $post['status'] === 'published' ? now() : null,
                'content_pillar_id' => $pillar?->id,
                'publish_mode' => $post['publish_mode'],
                'created_by' => $user->id,
            ]);
        }
    }
}
