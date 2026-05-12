<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiModelConfig;
use App\Models\AiUsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AiModelConfigController extends Controller
{
    public function index(): Response
    {
        $wsId = auth()->user()->workspace_id;

        // ─── AI Usage Stats (today) ───
        $today = Carbon::today();
        $todayLogs = AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->whereDate('created_at', $today);

        $totalRequestsToday = (clone $todayLogs)->count();
        $totalTokensToday = (clone $todayLogs)->sum('total_tokens');
        $totalCostToday = (clone $todayLogs)->sum('cost');
        $successRateToday = $totalRequestsToday > 0
            ? round((clone $todayLogs)->where('is_success', true)->count() / $totalRequestsToday * 100)
            : 100;

        // ─── Usage per feature (last 7 days) ───
        $last7Days = AiUsageLog::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('feature, COUNT(*) as requests, SUM(total_tokens) as tokens, SUM(cost) as cost')
            ->groupBy('feature')
            ->get()
            ->map(fn ($row) => [
                'feature' => $row->feature,
                'requests' => $row->requests,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 4),
            ]);

        // ─── Recent logs (last 20) ───
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
