<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\InsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateDailyInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly ?int $workspaceId = null,
    ) {}

    public function handle(InsightService $insightService): void
    {
        if ($this->workspaceId) {
            // Generate for a specific workspace
            $insightService->generateAndCacheInsights($this->workspaceId);
            return;
        }

        // Generate for all active workspaces
        $workspaces = Workspace::all();

        foreach ($workspaces as $workspace) {
            try {
                $insightService->generateAndCacheInsights($workspace->id);
                Log::info('Daily insights generated', ['workspace_id' => $workspace->id]);
            } catch (\Exception $e) {
                Log::warning('Failed to generate insights for workspace', [
                    'workspace_id' => $workspace->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
