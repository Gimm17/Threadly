<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsSnapshot;
use App\Models\Post;
use App\Models\Workspace;

class AnalyticsService
{
    public function getWorkspaceMetrics(int $workspaceId): array
    {
        $latest = AnalyticsSnapshot::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('snapshot_date')
            ->first();

        $previous = AnalyticsSnapshot::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('snapshot_date')
            ->skip(1)
            ->first();

        $scheduledCount = Post::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->count();

        return [
            'followers' => [
                'value' => $latest?->followers_count ?? 0,
                'delta' => $latest && $previous
                    ? $latest->followers_count - $previous->followers_count
                    : 0,
            ],
            'engagement_rate' => [
                'value' => $latest?->engagement_rate ?? 0,
                'delta' => $latest && $previous
                    ? round($latest->engagement_rate - $previous->engagement_rate, 1)
                    : 0,
            ],
            'impressions' => [
                'value' => $latest?->impressions ?? 0,
                'delta' => $latest && $previous
                    ? $latest->impressions - $previous->impressions
                    : 0,
            ],
            'scheduled_posts' => [
                'value' => $scheduledCount,
                'label' => 'Minggu ini',
            ],
        ];
    }

    public function getEngagementChart(int $workspaceId, int $days = 30): array
    {
        $snapshots = AnalyticsSnapshot::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date')
            ->get();

        return [
            'labels' => $snapshots->pluck('snapshot_date')->map(fn ($d) => $d->format('d M'))->toArray(),
            'datasets' => [
                [
                    'label' => 'Engagement',
                    'data' => $snapshots->pluck('engagement')->toArray(),
                ],
            ],
        ];
    }
}
