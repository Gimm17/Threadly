<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HookTemplate;
use App\Models\Post;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(): Response
    {
        $workspaceId = auth()->user()->workspace_id;

        return Inertia::render('Dashboard/Index', [
            'metrics' => $this->analyticsService->getWorkspaceMetrics($workspaceId),
            'chartData' => $this->analyticsService->getEngagementChart($workspaceId),
            'upcomingPosts' => Post::with('contentPillar')
                ->upcoming()
                ->limit(5)
                ->get()
                ->map(fn ($post) => [
                    'id' => $post->id,
                    'hook' => $post->hook,
                    'body' => $post->body,
                    'status' => $post->status,
                    'scheduled_at' => $post->scheduled_at?->format('Y-m-d H:i'),
                    'scheduled_day' => $post->scheduled_at?->translatedFormat('l'),
                    'scheduled_time' => $post->scheduled_at?->format('H:i') . ' WIB',
                    'pillar' => $post->contentPillar?->name,
                    'pillar_color' => $post->contentPillar?->color_hex,
                ]),
            'topHooks' => HookTemplate::bySaveRate()
                ->limit(3)
                ->get()
                ->map(fn ($hook) => [
                    'id' => $hook->id,
                    'hook_text' => $hook->hook_text,
                    'save_count' => $hook->save_count,
                    'view_count' => $hook->view_count,
                    'category' => $hook->category,
                ]),
            'insights' => [
                [
                    'type' => 'success',
                    'icon' => 'trending-up',
                    'title' => 'Waktu Posting Optimal',
                    'body' => 'Rekomendasi posting hari ini pada pukul 19:00 WIB untuk engagement maksimal.',
                ],
                [
                    'type' => 'info',
                    'icon' => 'bulb',
                    'title' => 'Topik Trending',
                    'body' => 'Audiens merespon positif konten edukasi seputar "AI Tools". Perbanyak pilar ini.',
                ],
                [
                    'type' => 'warning',
                    'icon' => 'alert-triangle',
                    'title' => 'Penurunan Reach',
                    'body' => 'Postingan hari Selasa mengalami penurunan reach 12%. Evaluasi ulang hashtag.',
                ],
            ],
        ]);
    }
}
