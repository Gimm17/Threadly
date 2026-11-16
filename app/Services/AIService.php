<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiModelConfig;
use App\Models\AiUsageLog;
use App\Services\AI\AIBudgetGuard;
use App\Services\AI\CostEstimator;
use App\Services\AI\ModelCatalogService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AIService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.tokenrouter.key', '');
        $this->baseUrl = (string) config('services.tokenrouter.base_url', 'https://api.tokenrouter.com/v1');
    }

    private function verifySsl(): bool
    {
        return filter_var(config('services.tokenrouter.verify_ssl', true), FILTER_VALIDATE_BOOL);
    }

    private function textTimeout(): int
    {
        return max(30, min(120, (int) config('services.tokenrouter.text_timeout', 60)));
    }

    private function extendTextExecutionTime(): void
    {
        $seconds = $this->textTimeout() + 30;

        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) $seconds);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }

    private function imageTimeout(): int
    {
        return max(60, min(900, (int) config('services.tokenrouter.image_timeout', 180)));
    }

    private function extendImageExecutionTime(): void
    {
        // Image models can run longer than PHP's default 30 second request limit.
        $seconds = $this->imageTimeout() + 30;

        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) $seconds);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
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
        ?int    $maxTokens = null,
        bool    $jsonMode = false,
    ): string {
        if (empty($this->apiKey)) {
            throw new RuntimeException('TokenRouter API key is not configured.');
        }

        $this->extendTextExecutionTime();

        $config = $this->getConfig($feature, $workspaceId);
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;
        $startTime = microtime(true);
        $resolvedSystemPrompt = $systemPrompt ?? $config->system_prompt ?? 'You are a helpful assistant.';
        $resolvedMaxTokens = $maxTokens ? min($config->max_tokens, $maxTokens) : $config->max_tokens;
        $inputEstimate = app(CostEstimator::class)->estimateTokens([$resolvedSystemPrompt, $userPrompt]);
        app(AIBudgetGuard::class)->ensureAllowed(
            $wsId,
            app(CostEstimator::class)->textCost($config->model_id, $inputEstimate, $resolvedMaxTokens),
        );

        $payload = [
            'model' => $config->model_id,
            'temperature' => (float) $config->temperature,
            'max_tokens' => $resolvedMaxTokens,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $resolvedSystemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        if ($jsonMode && app(ModelCatalogService::class)->supportsJsonMode($config->model_id)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => $this->verifySsl()])
                ->timeout($this->textTimeout())
                ->retry(2, 2000, fn ($e) => $this->shouldRetryProviderRequest($e))
                ->post($this->baseUrl . '/chat/completions', $payload);

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                $message = $this->providerErrorMessage('AI API error', $response->status(), $response->body());
                $this->logUsage($wsId, $feature, $config->model_id, 0, 0, 0, $elapsedMs, false, $message);
                throw new RuntimeException($message);
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? ($promptTokens + $completionTokens);

            $this->logUsage($wsId, $feature, $config->model_id, $promptTokens, $completionTokens, $totalTokens, $elapsedMs, true);

            return $content;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (RequestException $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->providerRequestExceptionMessage('AI API error', $e);
            $this->logUsage($wsId, $feature, $config->model_id, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException($message);
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->sanitizeAiError($e->getMessage());
            $this->logUsage($wsId, $feature, $config->model_id, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException('AI request failed: ' . $message);
        }
    }

    public function completeJson(
        string  $feature,
        string  $userPrompt,
        ?string $systemPrompt = null,
        ?int    $workspaceId = null,
        ?int    $maxTokens = null,
    ): string {
        return $this->complete(
            feature: $feature,
            userPrompt: $userPrompt,
            systemPrompt: $systemPrompt,
            workspaceId: $workspaceId,
            maxTokens: $maxTokens,
            jsonMode: true,
        );
    }

    /**
     * Determine the correct endpoint strategy for a given image model.
     *
     * TokenRouter API has two image generation paths:
     *  - /v1/images/generations  -> OpenAI image models, ByteDance Seedream, etc.
     *  - /v1/chat/completions    -> Google Gemini image models (require modalities: ["text","image"])
     *
     * @return 'images'|'chat'
     */
    private function resolveImageEndpoint(string $modelId): string
    {
        return app(ModelCatalogService::class)->endpointType($modelId);
    }

    /**
     * Generate an image with smart endpoint routing.
     *
     * Automatically detects the model vendor and routes to the correct
     * TokenRouter endpoint:
     *  - Google Gemini -> POST /v1/chat/completions  (modalities: ["text","image"])
     *  - OpenAI / ByteDance / others -> POST /v1/images/generations
     *
     * @return array{path: string, name: string, mime: string, size: int}
     * @throws RuntimeException when AI call fails
     */
    public function generateImage(
        string  $prompt,
        string  $style = 'realistic',
        string  $aspectRatio = '1:1',
        string  $quality = 'auto',
        string  $background = 'auto',
        ?string $modelOverride = null,
        ?int    $workspaceId = null,
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException('TokenRouter API key is not configured.');
        }

        $config = $this->getConfig('image_generation', $workspaceId);
        $modelId = $modelOverride ?: $config->model_id;
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;
        $startTime = microtime(true);
        $endpointType = $this->resolveImageEndpoint($modelId);

        $this->extendImageExecutionTime();
        $this->guardImageBudget($wsId, $modelId);

        $userContent = str_contains($prompt, 'Create a premium social media poster')
            ? $prompt
            : "Generate a high-quality {$style} style image. Image description: {$prompt}";

        $size = app(ModelCatalogService::class)->sizeForAspectRatio($aspectRatio);

        try {
            if ($endpointType === 'chat') {
                // Google Gemini path: /v1/chat/completions
                $payload = [
                    'model' => $modelId,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $userContent,
                        ],
                    ],
                    'modalities' => ['text', 'image'],
                    'size' => $size,
                    'max_tokens' => min($config->max_tokens, 4096),
                ];

                Log::info('AI Image via /chat/completions', ['model' => $modelId, 'endpoint' => 'chat']);

                $response = Http::withToken($this->apiKey)
                    ->withOptions(['verify' => $this->verifySsl()])
                    ->timeout($this->imageTimeout())
                    ->retry(2, 5000, fn ($e) => $this->shouldRetryProviderRequest($e))
                    ->post($this->baseUrl . '/chat/completions', $payload);
            } else {
                // OpenAI / ByteDance / default path: /v1/images/generations
                $payload = [
                    'model' => $modelId,
                    'prompt' => $userContent,
                    'n' => 1,
                    'size' => $size,
                    'quality' => $quality,
                ];

                if ($background !== 'auto') {
                    $payload['background'] = $background;
                }

                Log::info('AI Image via /images/generations', ['model' => $modelId, 'endpoint' => 'images']);

                $response = Http::withToken($this->apiKey)
                    ->withOptions(['verify' => $this->verifySsl()])
                    ->timeout($this->imageTimeout())
                    ->retry(2, 3000, fn ($e) => $this->shouldRetryProviderRequest($e))
                    ->post($this->baseUrl . '/images/generations', $payload);
            }

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                $message = $this->providerErrorMessage('AI Image API error', $response->status(), $response->body());
                $this->logUsage($wsId, 'image_generation', $modelId, 0, 0, 0, $elapsedMs, false, $message);
                throw new RuntimeException($message);
            }

            $data = $response->json();

            $usage = $data['usage'] ?? [];
            $this->logUsage($wsId, 'image_generation', $modelId, $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0, $usage['total_tokens'] ?? 0, $elapsedMs, true);

            return $this->extractAndSaveImage($data, $wsId);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (RequestException $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->providerRequestExceptionMessage('AI Image API error', $e);
            $this->logUsage($wsId, 'image_generation', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException($message);
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->sanitizeAiError($e->getMessage());
            $this->logUsage($wsId, 'image_generation', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException('AI image request failed: ' . $message);
        }
    }

    /**
     * Edit an image via /v1/images/edits (multipart/form-data).
     *
     * @param  string|array  $imagePaths  One or more local file paths
     * @return array{path: string, name: string, mime: string, size: int}
     * @throws RuntimeException when AI call fails
     */
    public function editImage(
        string       $prompt,
        string|array $imagePaths,
        string       $size = '1024x1024',
        int          $n = 1,
        ?string      $modelOverride = null,
        ?int         $workspaceId = null,
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException('TokenRouter API key is not configured.');
        }

        $config = $this->getConfig('image_generation', $workspaceId);
        $modelId = $modelOverride ?: $config->model_id;
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;
        $startTime = microtime(true);

        $this->extendImageExecutionTime();

        try {
            $imagePaths = is_array($imagePaths) ? array_values($imagePaths) : [$imagePaths];

            if ($this->resolveImageEndpoint($modelId) === 'chat') {
                return $this->generateImageFromReference(
                    prompt: $prompt,
                    referenceImageUrl: $this->localImagePathToDataUri($imagePaths[0] ?? ''),
                    aspectRatio: $this->aspectRatioFromSize($size),
                    modelOverride: $modelId,
                    workspaceId: $workspaceId,
                );
            }

            $this->guardImageBudget($wsId, $modelId);

            $multipart = [
                ['name' => 'model', 'contents' => $modelId],
                ['name' => 'prompt', 'contents' => $prompt],
                ['name' => 'n', 'contents' => (string) $n],
                ['name' => 'size', 'contents' => $size],
            ];

            // Attach image file(s) as multipart
            foreach ($imagePaths as $imgPath) {
                $fullPath = Storage::disk('public')->path($imgPath);
                if (!file_exists($fullPath)) {
                    throw new RuntimeException("Image file not found: {$imgPath}");
                }
                $fieldName = count($imagePaths) > 1 ? 'image[]' : 'image';
                $multipart[] = [
                    'name' => $fieldName,
                    'contents' => fopen($fullPath, 'r'),
                    'filename' => basename($fullPath),
                ];
            }

            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => $this->verifySsl()])
                ->timeout($this->imageTimeout())
                ->asMultipart()
                ->post($this->baseUrl . '/images/edits', $multipart);

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                $message = $this->providerErrorMessage('AI Image Edit API error', $response->status(), $response->body());
                $this->logUsage($wsId, 'image_edit', $config->model_id, 0, 0, 0, $elapsedMs, false, $message);
                throw new RuntimeException($message);
            }

            $data = $response->json();

            $usage = $data['usage'] ?? [];
            $this->logUsage($wsId, 'image_edit', $config->model_id, $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0, $usage['total_tokens'] ?? 0, $elapsedMs, true);

            return $this->extractAndSaveImage($data, $wsId);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (RequestException $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->providerRequestExceptionMessage('AI Image Edit API error', $e);
            $this->logUsage($wsId, 'image_edit', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException($message);
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->sanitizeAiError($e->getMessage());
            $this->logUsage($wsId, 'image_edit', $config->model_id, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException('AI image edit failed: ' . $message);
        }
    }

    /**
     * Generate an image via Multimodal Chat Completions: /v1/chat/completions
     * with modalities: ["text", "image"]
     *
     * @return array{path: string, name: string, mime: string, size: int}
     * @throws RuntimeException when AI call fails
     */
    public function generateImageViaChat(
        string  $prompt,
        string  $style = 'realistic',
        string  $aspectRatio = '1:1',
        ?string $modelOverride = null,
        ?int    $workspaceId = null,
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException('TokenRouter API key is not configured.');
        }

        $config = $this->getConfig('image_generation', $workspaceId);
        $modelId = $modelOverride ?: $config->model_id;
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;
        $startTime = microtime(true);

        $this->extendImageExecutionTime();
        $this->guardImageBudget($wsId, $modelId);

        $userContent = str_contains($prompt, 'Create a premium social media poster')
            ? $prompt
            : "Generate a high-quality {$style} style image. Image description: {$prompt}";

        $size = app(ModelCatalogService::class)->sizeForAspectRatio($aspectRatio);

        try {
            $payload = [
                'model' => $modelId,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
                'modalities' => ['text', 'image'],
                'size' => $size,
            ];

            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => $this->verifySsl()])
                ->timeout($this->imageTimeout())
                ->retry(2, 3000, fn ($e) => $this->shouldRetryProviderRequest($e))
                ->post($this->baseUrl . '/chat/completions', $payload);

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                $message = $this->providerErrorMessage('AI Chat Image API error', $response->status(), $response->body());
                $this->logUsage($wsId, 'image_chat', $modelId, 0, 0, 0, $elapsedMs, false, $message);
                throw new RuntimeException($message);
            }

            $data = $response->json();

            $usage = $data['usage'] ?? [];
            $this->logUsage($wsId, 'image_chat', $modelId, $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0, $usage['total_tokens'] ?? 0, $elapsedMs, true);

            return $this->extractAndSaveImage($data, $wsId);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (RequestException $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->providerRequestExceptionMessage('AI Chat Image API error', $e);
            $this->logUsage($wsId, 'image_chat', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException($message);
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->sanitizeAiError($e->getMessage());
            $this->logUsage($wsId, 'image_chat', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException('AI chat image generation failed: ' . $message);
        }
    }

    /**
     * Generate image from reference image via Multimodal Chat Completions.
     * Image-conditioned generation: /v1/chat/completions with image_url input.
     *
     * @return array{path: string, name: string, mime: string, size: int}
     * @throws RuntimeException when AI call fails
     */
    public function generateImageFromReference(
        string  $prompt,
        string  $referenceImageUrl,
        string  $aspectRatio = '1:1',
        string  $detail = 'high',
        ?string $modelOverride = null,
        ?int    $workspaceId = null,
    ): array {
        if (empty($this->apiKey)) {
            throw new RuntimeException('TokenRouter API key is not configured.');
        }

        $config = $this->getConfig('image_generation', $workspaceId);
        $modelId = $modelOverride ?: $config->model_id;
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;
        $startTime = microtime(true);

        $this->extendImageExecutionTime();
        $this->guardImageBudget($wsId, $modelId);

        $size = app(ModelCatalogService::class)->sizeForAspectRatio($aspectRatio);

        try {
            $payload = [
                'model' => $modelId,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $referenceImageUrl,
                                    'detail' => $detail,
                                ],
                            ],
                        ],
                    ],
                ],
                'modalities' => ['text', 'image'],
                'size' => $size,
            ];

            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => $this->verifySsl()])
                ->timeout($this->imageTimeout())
                ->retry(2, 3000, fn ($e) => $this->shouldRetryProviderRequest($e))
                ->post($this->baseUrl . '/chat/completions', $payload);

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                $message = $this->providerErrorMessage('AI Reference Image API error', $response->status(), $response->body());
                $this->logUsage($wsId, 'image_reference', $modelId, 0, 0, 0, $elapsedMs, false, $message);
                throw new RuntimeException($message);
            }

            $data = $response->json();

            $usage = $data['usage'] ?? [];
            $this->logUsage($wsId, 'image_reference', $modelId, $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0, $usage['total_tokens'] ?? 0, $elapsedMs, true);

            return $this->extractAndSaveImage($data, $wsId);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (RequestException $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->providerRequestExceptionMessage('AI Reference Image API error', $e);
            $this->logUsage($wsId, 'image_reference', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException($message);
        } catch (\Exception $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $message = $this->sanitizeAiError($e->getMessage());
            $this->logUsage($wsId, 'image_reference', $modelId, 0, 0, 0, $elapsedMs, false, $message);
            throw new RuntimeException('AI reference image generation failed: ' . $message);
        }
    }

    /**
     * Extract image data from various AI response formats and save to storage.
     *
     * Supports:
     * - /v1/images/generations format: data[].url or data[].b64_json
     * - /v1/chat/completions format: choices[].message with image content
     *
     * @return array{path: string, name: string, mime: string, size: int}
     * @throws RuntimeException when no image data found
     */
    private function extractAndSaveImage(array $data, ?int $wsId): array
    {
        $base64 = null;
        $mime = 'image/png';

        // Format 1: /v1/images/generations -> data[].b64_json
        $base64 = data_get($data, 'data.0.b64_json');

        // Format 2: /v1/images/generations -> data[].url
        if (!$base64) {
            $url = data_get($data, 'data.0.url');
            if ($url) {
                $base64 = $this->downloadImageAsBase64($url, $mime);
            }
        }

        // Format 3: /v1/chat/completions -> choices[].message.content (multimodal)
        if (!$base64) {
            $contentParts = data_get($data, 'choices.0.message.content');

            // content can be a string or array of parts
            if (is_array($contentParts)) {
                foreach ($contentParts as $part) {
                    if (($part['type'] ?? '') === 'image_url') {
                        $imgUrl = $part['image_url']['url'] ?? null;
                        if ($imgUrl) {
                            $base64 = $this->downloadImageAsBase64($imgUrl, $mime);
                            if ($base64) break;
                        }
                    }

                    if (($part['type'] ?? '') === 'inline_data' || isset($part['inline_data'])) {
                        $inline = $part['inline_data'] ?? $part;
                        $inlineData = $inline['data'] ?? null;
                        if ($inlineData) {
                            $mime = $inline['mime_type'] ?? $inline['mimeType'] ?? $mime;
                            $base64 = str_contains((string) $inlineData, 'base64,')
                                ? $this->downloadImageAsBase64((string) $inlineData, $mime)
                                : (string) $inlineData;
                            if ($base64) break;
                        }
                    }

                    if (($part['type'] ?? '') === 'image' && isset($part['data'])) {
                        $mime = $part['mime_type'] ?? $part['mimeType'] ?? $mime;
                        $base64 = (string) $part['data'];
                        if ($base64) break;
                    }
                }
            }

            // Legacy: choices[].message.images array
            if (!$base64) {
                $images = data_get($data, 'choices.0.message.images');
                if (is_array($images)) {
                    foreach ($images as $img) {
                        $imgUrl = $img['image_url']['url'] ?? $img['url'] ?? null;
                        if ($imgUrl) {
                            $base64 = $this->downloadImageAsBase64($imgUrl, $mime);
                            if ($base64) break;
                        }

                        $inlineData = $img['data'] ?? $img['b64_json'] ?? null;
                        if ($inlineData) {
                            $mime = $img['mime_type'] ?? $img['mimeType'] ?? $mime;
                            $base64 = (string) $inlineData;
                            if ($base64) break;
                        }
                    }
                }
            }
        }

        if (!$base64) {
            throw new RuntimeException('AI did not return image data. Response: ' . substr(json_encode($data), 0, 500));
        }

        // Save to storage
        $ext = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
        $fileName = 'ai_' . time() . '_' . uniqid() . '.' . $ext;
        $storagePath = "workspaces/{$wsId}/generated/{$fileName}";

        Storage::disk('public')->put($storagePath, base64_decode($base64));
        $fileSize = Storage::disk('public')->size($storagePath);

        return [
            'path' => $storagePath,
            'name' => $fileName,
            'mime' => $mime,
            'size' => $fileSize,
        ];
    }

    /**
     * Download an image URL (or parse data URI) and return base64 string.
     * Updates $mime by reference.
     */
    private function downloadImageAsBase64(string $url, string &$mime): ?string
    {
        // Data URI with base64
        if (str_contains($url, 'base64,')) {
            $dataParts = explode('base64,', $url, 2);
            if (preg_match('/data:([^;]+)/', $url, $m)) {
                $mime = $m[1];
            }
            return $dataParts[1] ?? null;
        }

        // Regular HTTP(S) URL: download it.
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            try {
                $imgResponse = Http::withOptions(['verify' => $this->verifySsl()])->timeout(60)->get($url);
                if ($imgResponse->successful()) {
                    $mime = $imgResponse->header('Content-Type') ?? 'image/png';
                    return base64_encode($imgResponse->body());
                }
            } catch (\Exception $e) {
                Log::warning('Failed to download AI image URL', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    private function providerRequestExceptionMessage(string $label, RequestException $e): string
    {
        if ($e->response) {
            return $this->providerErrorMessage($label, $e->response->status(), $e->response->body());
        }

        return "{$label}: " . $this->sanitizeAiError($e->getMessage());
    }

    private function shouldRetryProviderRequest(\Throwable $e): bool
    {
        if ($e instanceof RequestException && $e->response) {
            return $e->response->serverError() || $e->response->status() === 429;
        }

        return true;
    }

    private function providerErrorMessage(string $label, int $status, string $body): string
    {
        if ($status === 401) {
            return "{$label} [401]: API key tidak valid untuk provider/base URL yang dipakai. Cek TOKENROUTER_API_KEY dan TOKENROUTER_BASE_URL, lalu restart server Laravel.";
        }

        if ($status === 403) {
            return "{$label} [403]: API key tidak punya akses ke model yang dipilih. Cek model AI image di Settings atau permission provider.";
        }

        if ($status === 404) {
            return "{$label} [404]: model image tidak tersedia di provider/base URL yang dipakai. Pilih model image lain di Settings, misalnya Gemini Flash Image atau Seedream, lalu jalankan ulang queue.";
        }

        return "{$label} [{$status}]: " . mb_substr($this->sanitizeAiError($body), 0, 500);
    }

    private function sanitizeAiError(string $message): string
    {
        $message = preg_replace('/sk-[A-Za-z0-9_\\-]{8,}/', 'sk-...[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\\s+[A-Za-z0-9_\\.\\-]+/i', 'Bearer [redacted]', $message) ?? $message;

        return mb_substr($message, 0, 700);
    }

    /**
     * Log AI usage to the database.
     */
    private function logUsage(
        ?int    $workspaceId,
        string  $feature,
        string  $modelId,
        int     $promptTokens,
        int     $completionTokens,
        int     $totalTokens,
        int     $responseTimeMs,
        bool    $isSuccess,
        ?string $errorMessage = null,
    ): void {
        try {
            AiUsageLog::create([
                'workspace_id' => $workspaceId,
                'user_id' => auth()->id(),
                'feature' => $feature,
                'model_id' => $modelId,
                'provider' => 'tokenrouter',
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
            'cost' => $this->estimateCost($feature, $modelId, $promptTokens, $completionTokens, $totalTokens),
            'response_time_ms' => $responseTimeMs,
            'is_success' => $isSuccess,
            'error_message' => $errorMessage,
            'estimated_input_tokens' => $promptTokens,
            'estimated_output_tokens' => $completionTokens,
            'cost_source' => 'model_catalog',
        ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log AI usage', ['error' => $e->getMessage()]);
        }
    }

    private function estimateCost(string $feature, string $modelId, int $promptTokens, int $completionTokens, int $totalTokens): float
    {
        $estimator = app(CostEstimator::class);

        if (str_starts_with($feature, 'image')) {
            if ($promptTokens === 0 && $completionTokens === 0 && $totalTokens > 0) {
                $promptTokens = (int) floor($totalTokens * 0.65);
                $completionTokens = $totalTokens - $promptTokens;
            }

            return round(
                $estimator->imageCost($modelId) + $estimator->textCost($modelId, $promptTokens, $completionTokens),
                6,
            );
        }

        if ($promptTokens === 0 && $completionTokens === 0 && $totalTokens > 0) {
            $promptTokens = (int) floor($totalTokens * 0.65);
            $completionTokens = $totalTokens - $promptTokens;
        }

        return $estimator->textCost($modelId, $promptTokens, $completionTokens);
    }

    private function guardImageBudget(?int $workspaceId, string $modelId): void
    {
        app(AIBudgetGuard::class)->ensureAllowed(
            $workspaceId,
            app(CostEstimator::class)->imageCost($modelId),
        );
    }

    private function aspectRatioFromSize(string $size): string
    {
        return match ($size) {
            '1536x1024' => '16:9',
            '1024x1536' => '9:16',
            '1024x1280' => '4:5',
            default => '1:1',
        };
    }

    private function localImagePathToDataUri(string $imagePath): string
    {
        $fullPath = Storage::disk('public')->path($imagePath);
        if (!file_exists($fullPath)) {
            throw new RuntimeException("Image file not found: {$imagePath}");
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath));
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
