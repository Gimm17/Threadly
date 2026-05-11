<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiModelConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiModelConfigController extends Controller
{
    public function index(): Response
    {
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
        ]);
    }

    public function update(Request $request, AiModelConfig $config): RedirectResponse
    {
        $validated = $request->validate([
            'model_id' => ['required', 'string', 'max:100'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:8000'],
            'system_prompt' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $config->update($validated);

        return redirect()->route('settings.ai-models')
            ->with('success', 'Konfigurasi AI berhasil diperbarui.');
    }
}
