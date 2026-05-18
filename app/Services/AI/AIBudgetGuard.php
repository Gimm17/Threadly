<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiUsageLog;
use RuntimeException;

class AIBudgetGuard
{
    public function ensureAllowed(?int $workspaceId, float $estimatedCost): void
    {
        $limit = (float) env('AI_DAILY_COST_LIMIT_USD', 0);
        if ($limit <= 0 || ! $workspaceId) {
            return;
        }

        $spentToday = (float) AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereDate('created_at', now()->toDateString())
            ->sum('cost');

        if (($spentToday + $estimatedCost) > $limit) {
            throw new RuntimeException('Batas biaya AI harian untuk workspace ini sudah tercapai.');
        }
    }
}
