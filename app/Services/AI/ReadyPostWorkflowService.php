<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Jobs\GeneratePosterImageJob;
use App\Models\AiModelConfig;
use App\Models\GeneratedMedia;

class ReadyPostWorkflowService
{
    public function __construct(
        private readonly AIWorkflowService $workflow,
        private readonly PosterPromptBuilder $posterPromptBuilder,
        private readonly CostEstimator $costEstimator,
    ) {}

    public function generate(array $input, int $workspaceId, int $userId): array
    {
        $copy = $this->workflow->generateReadyPostCopy($input, $workspaceId);
        $media = null;
        $imageError = null;
        $imagePrompt = null;

        if ((bool) ($input['generate_image'] ?? true)) {
            $imagePrompt = $this->fallbackImagePrompt($input, $copy);

            if ($this->shouldUseAiImagePrompt()) {
                try {
                    $imagePrompt = $this->workflow->generateReadyPostImagePrompt($input, $copy, $workspaceId);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $posterInput = [
                'prompt' => $imagePrompt['visual_prompt'] ?: ($copy['poster_brief'] ?: ($input['topic'] ?? '')),
                'headline' => $input['headline'] ?? $copy['headline'] ?? $copy['hook'],
                'style' => $input['style'] ?? 'photography',
                'aspect_ratio' => $input['aspect_ratio'] ?? '1:1',
                'quality' => $input['quality'] ?? 'auto',
                'background' => $input['background'] ?? 'auto',
                'allow_ai_text' => false,
                'objective' => $imagePrompt['objective'] ?? 'create a ready-to-post social media visual that supports the caption',
                'subject' => $input['subject'] ?? $imagePrompt['subject'] ?? $this->defaultSubjectForStyle((string) ($input['style'] ?? 'photography')),
                'palette' => $imagePrompt['palette'] ?? null,
            ];

            $promptData = $this->posterPromptBuilder->build($posterInput, $workspaceId);
            $promptData['structured_prompt'] = $imagePrompt;
            $modelUsed = $this->imageModelId($workspaceId);
            $media = $this->queueGeneratedMedia(
                workspaceId: $workspaceId,
                userId: $userId,
                input: $posterInput,
                modelId: $modelUsed,
                metadata: $promptData,
            );

            GeneratePosterImageJob::dispatch($media->id, [
                'style' => $posterInput['style'],
                'aspect_ratio' => $posterInput['aspect_ratio'],
                'quality' => $posterInput['quality'],
                'background' => $posterInput['background'],
            ])->onQueue('ai');
        }

        return [
            'post' => $copy,
            'media' => $media ? $this->mediaResponse($media) : null,
            'image_error' => $imageError,
            'image_prompt' => $imagePrompt ? $this->imagePromptResponse($imagePrompt, $media, $imageError) : null,
        ];
    }

    private function queueGeneratedMedia(
        int $workspaceId,
        int $userId,
        array $input,
        string $modelId,
        array $metadata,
    ): GeneratedMedia {
        return GeneratedMedia::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceId,
            'created_by' => $userId,
            'type' => 'image',
            'file_path' => '',
            'file_name' => '',
            'mime_type' => 'image/png',
            'file_size' => 0,
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
            'generation_status' => 'queued',
            'estimated_cost' => $this->costEstimator->imageCost($modelId),
        ]);
    }

    private function imageModelId(int $workspaceId): string
    {
        return (string) (AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('feature', 'image_generation')
            ->where('is_active', true)
            ->value('model_id') ?: config('services.tokenrouter.fallback_image_model', 'google/gemini-3.1-flash-image-preview'));
    }

    private function defaultSubjectForStyle(string $style): string
    {
        if ($style === '3d') {
            return 'stylized 3D ecommerce migration scene: a private online store dashboard on a laptop, abstract marketplace app tiles moving toward a branded website storefront, product cards, shopping bag, receipt, admin-fee coins, all as clean non-text UI shapes';
        }

        return 'Indonesian small business owner or admin team using technology in a tidy workspace';
    }

    private function fallbackImagePrompt(array $input, array $copy): array
    {
        $style = (string) ($input['style'] ?? 'photography');
        $aspectRatio = (string) ($input['aspect_ratio'] ?? '1:1');
        $subject = $this->defaultSubjectForStyle($style);
        $topic = trim((string) ($input['topic'] ?? ''));
        $brief = trim((string) ($copy['poster_brief'] ?? ''));
        $contentBrief = $brief !== '' ? $brief : $topic;
        $styleCue = $this->styleCue($style);
        $businessContext = $this->businessContext($topic, $copy);

        return [
            'status' => 'local',
            'style' => $style,
            'aspect_ratio' => $aspectRatio,
            'objective' => 'create a ready-to-post social media visual that supports the caption',
            'subject' => $businessContext['subject'] ?? $subject,
            'scene' => $businessContext['scene'],
            'props' => $businessContext['props'],
            'composition' => 'single coherent editorial poster, clear focal point, balanced negative space, polished finish',
            'palette' => 'deep navy, clean white, fresh green, warm amber accent',
            'lighting' => 'crisp soft commercial lighting',
            'visual_prompt' => trim(<<<PROMPT
            Premium {$styleCue} social media poster background for this content brief: {$contentBrief}.
            Show {$businessContext['scene']}. Foreground subject: {$businessContext['subject']}.
            Include relevant non-text props: {$businessContext['props_text']}.
            Composition: single polished scene, clear focal point, clean hierarchy, generous negative space for separate overlay text, aspect ratio {$aspectRatio}.
            Palette: deep navy, clean white, fresh green, warm amber accent. Lighting: crisp soft commercial lighting.
            Avoid visible text, letters, numbers, logos, watermarks, screenshots, readable UI labels, chart labels, or fake brand marks.
            PROMPT),
            'negative_notes' => ['no visible text', 'no labels', 'no logos', 'no watermark', 'no screenshots'],
        ];
    }

    private function shouldUseAiImagePrompt(): bool
    {
        return filter_var(config('services.tokenrouter.ready_post_image_prompt_ai', false), FILTER_VALIDATE_BOOL);
    }

    private function styleCue(string $style): string
    {
        return match ($style) {
            '3d' => '3D render with polished soft materials and subtle isometric depth',
            'illustration' => 'editorial illustration with modern refined shapes',
            'minimalist' => 'minimalist premium',
            'cartoon' => 'friendly professional cartoon illustration, polished and not childish',
            'photography' => 'realistic photography',
            default => 'high-quality realistic',
        };
    }

    private function businessContext(string $topic, array $copy): array
    {
        $text = mb_strtolower($topic . ' ' . ($copy['body'] ?? '') . ' ' . ($copy['poster_brief'] ?? ''));

        if (str_contains($text, 'landing page') || str_contains($text, 'seo') || str_contains($text, 'meta')) {
            return [
                'subject' => 'Indonesian business owner reviewing a clean landing page strategy board with a web designer in a tidy workspace',
                'scene' => 'a modern landing page planning workspace with blank website wireframe cards, search visibility cues, social ads funnel shapes, analytics blocks, and growth arrows as abstract non-text elements',
                'props' => ['blank website wireframe cards', 'search visibility icon shapes', 'social ads funnel shapes', 'analytics blocks', 'growth arrow shapes'],
                'props_text' => 'blank website wireframe cards, search visibility icon shapes, social ads funnel shapes, analytics blocks, growth arrow shapes',
            ];
        }

        if (str_contains($text, 'marketplace') || str_contains($text, 'ecommerce') || str_contains($text, 'toko online')) {
            return [
                'subject' => $this->defaultSubjectForStyle('3d'),
                'scene' => 'owned ecommerce website workflow with abstract marketplace tiles moving toward a private storefront dashboard',
                'props' => ['product cards', 'shopping bag', 'receipt shapes', 'admin-fee coin shapes', 'blank dashboard blocks'],
                'props_text' => 'product cards, shopping bag, receipt shapes, admin-fee coin shapes, blank dashboard blocks',
            ];
        }

        if (str_contains($text, 'chat') || str_contains($text, 'customer') || str_contains($text, 'pelanggan')) {
            return [
                'subject' => 'Indonesian small business admin organizing customer communication on a laptop in a tidy workspace',
                'scene' => 'clean customer-service admin desk with abstract chat bubbles, product cards, order workflow blocks, and blank laptop dashboard',
                'props' => ['abstract chat bubbles', 'product cards', 'order workflow blocks', 'blank laptop dashboard'],
                'props_text' => 'abstract chat bubbles, product cards, order workflow blocks, blank laptop dashboard',
            ];
        }

        return [
            'subject' => $this->defaultSubjectForStyle((string) ($copy['style'] ?? 'photography')),
            'scene' => 'modern Indonesian small business workspace with clear technology and admin workflow cues',
            'props' => ['blank dashboard shapes', 'product cards', 'customer chat shapes', 'admin workflow objects'],
            'props_text' => 'blank dashboard shapes, product cards, customer chat shapes, admin workflow objects',
        ];
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
            'error_message' => $media->error_message,
        ];
    }

    private function imagePromptResponse(array $prompt, ?GeneratedMedia $media, ?string $imageError): array
    {
        return [
            'status' => $media
                ? ($media->generation_status === 'completed' ? 'generated' : $media->generation_status)
                : ($imageError ? 'failed' : 'ready'),
            'style' => $prompt['style'] ?? null,
            'aspect_ratio' => $prompt['aspect_ratio'] ?? null,
            'objective' => $prompt['objective'] ?? null,
            'subject' => $prompt['subject'] ?? null,
            'scene' => $prompt['scene'] ?? null,
            'props' => $prompt['props'] ?? [],
            'composition' => $prompt['composition'] ?? null,
            'palette' => $prompt['palette'] ?? null,
            'lighting' => $prompt['lighting'] ?? null,
            'visual_prompt' => $prompt['visual_prompt'] ?? null,
            'negative_notes' => $prompt['negative_notes'] ?? [],
            'error' => $imageError,
        ];
    }
}
