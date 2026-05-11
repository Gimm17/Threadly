<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HookTemplate;
use App\Models\Post;
use App\Services\AnalyticsService;
use App\Services\InsightService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly InsightService $insightService,
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
            'insights' => $this->insightService->getDailyInsights($workspaceId),
        ]);
    }
}
