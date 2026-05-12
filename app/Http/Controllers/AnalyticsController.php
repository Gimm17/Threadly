<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AnalyticsSnapshot;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Services\ThreadsApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $workspace = Workspace::find($workspaceId);
        $hasApiAccess = $workspace && !empty($workspace->threads_access_token);

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
            'hasApiAccess' => $hasApiAccess,
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

    /**
     * Sync analytics data from Threads API and save as today's snapshot.
     */
    public function syncFromApi(ThreadsApiService $threadsApi): RedirectResponse
    {
        $workspace = Workspace::find(auth()->user()->workspace_id);

        if (!$workspace || empty($workspace->threads_access_token)) {
            return redirect()->back()
                ->with('error', 'Threads API belum dikonfigurasi. Tambahkan access token di Settings.');
        }

        try {
            // Get user-level insights
            $userInsights = $threadsApi->getUserInsights($workspace->threads_access_token);

            // Get recent threads and aggregate their insights
            $threads = $threadsApi->getUserThreads($workspace->threads_access_token, 50);

            $totalLikes = 0;
            $totalReplies = 0;
            $totalReposts = 0;
            $totalQuotes = 0;
            $totalViews = 0;

            foreach ($threads as $thread) {
                $insights = $threadsApi->getThreadInsights($workspace->threads_access_token, $thread['id']);
                $totalLikes += $insights['likes'] ?? 0;
                $totalReplies += $insights['replies'] ?? 0;
                $totalReposts += $insights['reposts'] ?? 0;
                $totalQuotes += $insights['quotes'] ?? 0;
                $totalViews += $insights['views'] ?? 0;
            }

            $followersCount = $userInsights['followers_count'] ?? 0;
            $totalEngagement = $totalLikes + $totalReplies + $totalReposts + $totalQuotes;
            $engagementRate = $followersCount > 0
                ? round(($totalEngagement / $followersCount) * 100, 2)
                : 0;

            // Save as today's snapshot
            AnalyticsSnapshot::withoutGlobalScopes()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'snapshot_date' => now()->toDateString(),
                ],
                [
                    'followers_count' => $followersCount,
                    'impressions' => $totalViews,
                    'likes' => $totalLikes,
                    'replies' => $totalReplies,
                    'reposts' => $totalReposts,
                    'quotes' => $totalQuotes,
                    'engagement_rate' => $engagementRate,
                ],
            );

            return redirect()->back()
                ->with('success', "Analytics berhasil disinkronkan dari Threads API! ({$followersCount} followers, {$totalEngagement} engagements)");

        } catch (\Exception $e) {
            Log::error('Analytics sync failed', ['error' => $e->getMessage()]);

            return redirect()->back()
                ->with('error', 'Gagal mengambil data dari Threads API: ' . $e->getMessage());
        }
    }
}
