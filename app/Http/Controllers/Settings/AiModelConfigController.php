<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiModelConfig;
use App\Models\AiUsageLog;
use App\Support\WorkspaceTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiModelConfigController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $wsId = $user->workspace_id;
        $workspaceTimezone = WorkspaceTime::timezone($user->workspace?->timezone);
        [$todayStartUtc, $todayEndUtc] = WorkspaceTime::todayUtcBounds($workspaceTimezone);

        // AI usage stats for the current workspace-local day.
        $todayLogs = AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc]);

        $totalRequestsToday = (clone $todayLogs)->count();
        $totalTokensToday = (clone $todayLogs)->sum('total_tokens');
        $totalCostToday = (clone $todayLogs)->sum('cost');
        $successRateToday = $totalRequestsToday > 0
            ? round((clone $todayLogs)->where('is_success', true)->count() / $totalRequestsToday * 100)
            : 100;

        // Usage per feature during the last 7 workspace-local days.
        $last7Days = AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->where('created_at', '>=', WorkspaceTime::daysAgoUtc($workspaceTimezone, 7))
            ->selectRaw('feature, COUNT(*) as requests, SUM(COALESCE(total_tokens, 0)) as tokens, SUM(COALESCE(cost, 0)) as cost')
            ->groupBy('feature')
            ->get()
            ->map(fn ($row) => [
                'feature' => $row->feature,
                'requests' => $row->requests,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 4),
            ]);

        // Recent logs.
        $recentLogs = AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'feature' => $log->feature,
                'model_id' => $log->model_id,
                'total_tokens' => $log->total_tokens,
                'cost' => round((float) $log->cost, 6),
                'response_time_ms' => $log->response_time_ms,
                'is_success' => $log->is_success,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->diffForHumans(),
            ]);

        return Inertia::render('Settings/AiModels', [
            'configs' => AiModelConfig::orderBy('feature')->get()->map(fn ($c) => [
                'id' => $c->id,
                'feature' => $c->feature,
                'feature_label' => $c->feature_label,
                'feature_icon' => $c->feature_icon,
                'provider' => $c->provider,
                'model_id' => $c->model_id,
                'temperature' => $c->temperature,
                'max_tokens' => $c->max_tokens,
                'system_prompt' => $c->system_prompt,
                'is_active' => $c->is_active,
            ]),
            'modelCatalog' => config('ai-models'),
            'usageStats' => [
                'total_requests_today' => $totalRequestsToday,
                'total_tokens_today' => (int) $totalTokensToday,
                'total_cost_today' => round((float) $totalCostToday, 4),
                'success_rate_today' => $successRateToday,
                'timezone' => $workspaceTimezone,
            ],
            'usagePerFeature' => $last7Days,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function update(Request $request, AiModelConfig $config): RedirectResponse
    {
        $validated = $request->validate([
            'model_id' => ['required', 'string', 'max:100'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:16000'],
            'system_prompt' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $config->update($validated);

        return redirect()->route('settings.ai-models')
            ->with('success', 'Konfigurasi AI berhasil diperbarui.');
    }
}
