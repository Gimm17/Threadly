<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GeneratedMedia;
use App\Services\AIService;
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

        $fileInfo = $aiService->generateImage(
            prompt: (string) $media->enhanced_prompt,
            style: (string) ($this->options['style'] ?? $media->style ?? 'realistic'),
            aspectRatio: (string) ($this->options['aspect_ratio'] ?? $media->aspect_ratio ?? '1:1'),
            quality: (string) ($this->options['quality'] ?? 'auto'),
            background: (string) ($this->options['background'] ?? 'auto'),
            workspaceId: $media->workspace_id,
        );

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
}
