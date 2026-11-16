<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiUsageLog;
use App\Models\Workspace;
use App\Support\WorkspaceTime;
use RuntimeException;

class AIBudgetGuard
{
    public function ensureAllowed(?int $workspaceId, float $estimatedCost): void
    {
        $limit = (float) env('AI_DAILY_COST_LIMIT_USD', 0);
        if ($limit <= 0 || ! $workspaceId) {
            return;
        }

        $timezone = Workspace::query()->whereKey($workspaceId)->value('timezone');
        [$todayStartUtc, $todayEndUtc] = WorkspaceTime::todayUtcBounds($timezone);

        $spentToday = (float) AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->sum('cost');

        if (($spentToday + $estimatedCost) > $limit) {
            throw new RuntimeException('Batas biaya AI harian untuk workspace ini sudah tercapai.');
        }
    }
}
