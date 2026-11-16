<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GeneratedMedia;
use App\Services\AIService;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePosterImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 210;
    public int $tries = 2;
    public int $backoff = 15;

    public function __construct(
        private readonly int $mediaId,
        private readonly array $options = [],
    ) {}

    public function handle(AIService $aiService): void
    {
        $media = GeneratedMedia::withoutGlobalScopes()->findOrFail($this->mediaId);

        $media->update([
            'generation_status' => 'processing',
            'error_message' => null,
        ]);

        $fileInfo = $this->generateWithFallback($aiService, $media);

        $media->update([
            'file_path' => $fileInfo['path'],
            'file_name' => $fileInfo['name'],
            'mime_type' => $fileInfo['mime'],
            'file_size' => $fileInfo['size'],
            'generation_status' => 'completed',
            'error_message' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        GeneratedMedia::withoutGlobalScopes()
            ->whereKey($this->mediaId)
            ->update([
                'generation_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
    }

    /**
     * TokenRouter can return 404 when the selected image model is no longer
     * available for the account. Keep the post flow usable by retrying once
     * with the app's default image model, then surface a clear failure if that
     * provider also rejects the request.
     */
    private function generateWithFallback(AIService $aiService, GeneratedMedia $media): array
    {
        $style = (string) ($this->options['style'] ?? $media->style ?? 'realistic');
        $aspectRatio = (string) ($this->options['aspect_ratio'] ?? $media->aspect_ratio ?? '1:1');
        $quality = (string) ($this->options['quality'] ?? 'auto');
        $background = (string) ($this->options['background'] ?? 'auto');
        $modelId = filled($media->model_id) ? (string) $media->model_id : null;

        try {
            return $aiService->generateImage(
                prompt: (string) $media->enhanced_prompt,
                style: $style,
                aspectRatio: $aspectRatio,
                quality: $quality,
                background: $background,
                modelOverride: $modelId,
                workspaceId: $media->workspace_id,
            );
        } catch (\RuntimeException $e) {
            $fallbackModel = config('services.tokenrouter.fallback_image_model', 'google/gemini-3.1-flash-image-preview');

            if (! Str::contains($e->getMessage(), '[404]') || blank($fallbackModel) || $fallbackModel === $modelId) {
                throw $e;
            }

            $media->update([
                'model_id' => $fallbackModel,
                'error_message' => 'Model image utama tidak tersedia, mencoba fallback model.',
            ]);

            return $aiService->generateImage(
                prompt: (string) $media->enhanced_prompt,
                style: $style,
                aspectRatio: $aspectRatio,
                quality: $quality,
                background: $background,
                modelOverride: (string) $fallbackModel,
                workspaceId: $media->workspace_id,
            );
        }
    }
}
