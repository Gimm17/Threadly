<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AnalyticsSnapshot;
use App\Services\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(): Response
    {
        $workspaceId = auth()->user()->workspace_id;

        $snapshots = AnalyticsSnapshot::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('snapshot_date')
            ->limit(30)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->snapshot_date?->format('Y-m-d'),
                'date_display' => $s->snapshot_date?->translatedFormat('d M Y'),
                'followers_count' => $s->followers_count,
                'impressions' => $s->impressions,
                'likes' => $s->likes,
                'replies' => $s->replies,
                'reposts' => $s->reposts,
                'quotes' => $s->quotes,
                'engagement_rate' => $s->engagement_rate,
            ]);

        return Inertia::render('Analytics/Index', [
            'snapshots' => $snapshots,
            'metrics' => $this->analyticsService->getWorkspaceMetrics($workspaceId),
            'chartData' => $this->analyticsService->getEngagementChart($workspaceId),
        ]);
    }

    public function storeSnapshot(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'replies' => ['nullable', 'integer', 'min:0'],
            'reposts' => ['nullable', 'integer', 'min:0'],
            'quotes' => ['nullable', 'integer', 'min:0'],
            'engagement_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $workspaceId = auth()->user()->workspace_id;

        AnalyticsSnapshot::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'snapshot_date' => $validated['snapshot_date'],
            ],
            [
                ...$validated,
                'workspace_id' => $workspaceId,
            ],
        );

        return redirect()->back()
            ->with('success', 'Data analytics berhasil disimpan.');
    }
}
