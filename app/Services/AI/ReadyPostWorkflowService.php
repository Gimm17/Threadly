<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiModelConfig;
use App\Models\GeneratedMedia;
use App\Services\AIService;

class ReadyPostWorkflowService
{
    public function __construct(
        private readonly AIWorkflowService $workflow,
        private readonly AIService $aiService,
        private readonly PosterPromptBuilder $posterPromptBuilder,
        private readonly CostEstimator $costEstimator,
    ) {}

    public function generate(array $input, int $workspaceId, int $userId): array
    {
        $copy = $this->workflow->generateReadyPostCopy($input, $workspaceId);
        $media = null;
        $imageError = null;

        if ((bool) ($input['generate_image'] ?? true)) {
            $posterInput = [
                'prompt' => $copy['poster_brief'] ?: ($input['topic'] ?? ''),
                'headline' => $input['headline'] ?? $copy['headline'] ?? $copy['hook'],
                'style' => $input['style'] ?? 'photography',
                'aspect_ratio' => $input['aspect_ratio'] ?? '1:1',
                'quality' => $input['quality'] ?? 'auto',
                'background' => $input['background'] ?? 'auto',
                'allow_ai_text' => false,
                'objective' => 'create a ready-to-post social media visual that supports the caption',
                'subject' => $input['subject'] ?? 'Indonesian small business owner or admin team using technology in a tidy workspace',
            ];

            $promptData = $this->posterPromptBuilder->build($posterInput, $workspaceId);
            $modelUsed = $this->imageModelId($workspaceId);

            try {
                $fileInfo = $this->aiService->generateImage(
                    prompt: $promptData['enhanced_prompt'],
                    style: (string) $posterInput['style'],
                    aspectRatio: (string) $posterInput['aspect_ratio'],
                    quality: (string) $posterInput['quality'],
                    background: (string) $posterInput['background'],
                    workspaceId: $workspaceId,
                );

                $media = $this->saveGeneratedMedia(
                    workspaceId: $workspaceId,
                    userId: $userId,
                    input: $posterInput,
                    fileInfo: $fileInfo,
                    modelId: $modelUsed,
                    metadata: $promptData,
                );
            } catch (\Throwable $e) {
                report($e);

                $imageError = app()->environment('local')
                    ? 'Copy berhasil dibuat, tetapi gambar gagal: ' . mb_substr($e->getMessage(), 0, 220)
                    : 'Copy berhasil dibuat, tetapi gambar AI belum selesai dibuat. Coba generate gambar ulang di Image Studio.';
            }
        }

        return [
            'post' => $copy,
            'media' => $media ? $this->mediaResponse($media) : null,
            'image_error' => $imageError,
        ];
    }

    private function saveGeneratedMedia(
        int $workspaceId,
        int $userId,
        array $input,
        array $fileInfo,
        string $modelId,
        array $metadata,
    ): GeneratedMedia {
        return GeneratedMedia::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceId,
            'created_by' => $userId,
            'type' => 'image',
            'file_path' => $fileInfo['path'],
            'file_name' => $fileInfo['name'],
            'mime_type' => $fileInfo['mime'],
            'file_size' => $fileInfo['size'],
            'prompt' => $input['prompt'],
            'original_prompt' => $metadata['original_prompt'] ?? $input['prompt'],
            'enhanced_prompt' => $metadata['enhanced_prompt'] ?? null,
            'negative_prompt' => $metadata['negative_prompt'] ?? null,
            'provider' => 'tokenrouter',
            'model_id' => $modelId,
            'style' => $input['style'] ?? null,
            'aspect_ratio' => $input['aspect_ratio'] ?? '1:1',
            'generation_mode' => 'ready-post',
            'model_params' => $metadata['model_params'] ?? null,
            'overlay_config' => $metadata['overlay_config'] ?? null,
            'prompt_template_version' => $metadata['prompt_template_version'] ?? null,
            'generation_status' => 'completed',
            'estimated_cost' => $this->costEstimator->imageCost($modelId),
        ]);
    }

    private function imageModelId(int $workspaceId): string
    {
        return (string) AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('feature', 'image_generation')
            ->where('is_active', true)
            ->value('model_id');
    }

    private function mediaResponse(GeneratedMedia $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'file_size' => $media->file_size,
            'prompt' => $media->prompt,
            'style' => $media->style,
            'aspect_ratio' => $media->aspect_ratio,
            'generation_mode' => $media->generation_mode,
            'generation_status' => $media->generation_status,
            'enhanced_prompt' => $media->enhanced_prompt,
            'overlay_config' => $media->overlay_config,
            'estimated_cost' => $media->estimated_cost,
        ];
    }
}
