<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiModelConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.tokenrouter.key', '');
        $this->baseUrl = config('services.tokenrouter.base_url', 'https://api.tokenrouter.ai/v1');
    }

    /**
     * Send a completion request to TokenRouter (OpenAI-compatible).
     *
     * @throws RuntimeException when AI call fails
     */
    public function complete(
        string  $feature,
        string  $userPrompt,
        ?string $systemPrompt = null,
        ?int    $workspaceId = null,
    ): string {
        if (empty($this->apiKey)) {
            throw new RuntimeException('TokenRouter API key is not configured.');
        }

        $config = $this->getConfig($feature, $workspaceId);

        $response = Http::withToken($this->apiKey)
            ->withOptions(['verify' => false])
            ->timeout(30)
            ->retry(2, 1000)
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $config->model_id,
                'temperature' => (float) $config->temperature,
                'max_tokens' => $config->max_tokens,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt ?? $config->system_prompt ?? 'You are a helpful assistant.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "AI API error [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    private function getConfig(string $feature, ?int $workspaceId): AiModelConfig
    {
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;

        return AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->where('feature', $feature)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
