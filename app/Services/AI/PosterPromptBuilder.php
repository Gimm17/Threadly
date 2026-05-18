<?php

declare(strict_types=1);

namespace App\Services\AI;

class PosterPromptBuilder
{
    public function __construct(
        private readonly PromptTemplateService $prompts,
    ) {}

    public function build(array $input, ?int $workspaceId = null): array
    {
        $brand = $this->prompts->brandProfile($workspaceId);
        $brief = trim((string) ($input['prompt'] ?? $input['brief'] ?? ''));
        $headline = trim((string) ($input['headline'] ?? ''));
        $objective = trim((string) ($input['objective'] ?? 'educate and build trust'));
        $subject = trim((string) ($input['subject'] ?? 'Indonesian UMKM admin or small business owner working calmly at a tidy desk'));
        $style = (string) ($input['style'] ?? config('ai-prompts.poster.default_style'));
        $aspectRatio = (string) ($input['aspect_ratio'] ?? '1:1');
        $allowAiText = (bool) ($input['allow_ai_text'] ?? false);
        $negative = (string) config('ai-prompts.poster.negative_prompt');
        $safeArea = (string) config('ai-prompts.poster.safe_area');
        $palette = (string) ($input['palette'] ?? config('ai-prompts.poster.default_palette'));
        $styleInstruction = $this->styleInstruction($style);

        $textInstruction = $allowAiText && $headline !== ''
            ? "Include only this short, readable headline text: \"{$this->shortHeadline($headline)}\". No other text."
            : 'Do not render any text, letters, numbers, UI labels, chart labels, logos, or document writing in the image. Leave clean space for a separate text overlay.';

        $enhancedPrompt = trim(<<<PROMPT
        Create a premium social media poster background for Threads/Instagram.
        Objective: {$objective}.
        Brand cue: {$brand['brand_name']} (@{$brand['threads_handle']}), modern Indonesian technology partner, trustworthy and practical.
        Audience: {$brand['audience']}.
        Core brief: {$brief}.
        Visual style: {$styleInstruction}
        Foreground subject: {$subject}.
        Scene and props: realistic Indonesian business/admin context, tidy desk or work setup, subtle visual hints of invoice, stock checklist, customer chat, or daily report when relevant, but all screens and papers must be blank or use abstract non-text shapes only.
        Composition: one coherent single-scene editorial poster, clear focal point, clean hierarchy, strong depth, balanced negative space, no crowded objects, no split-screen, no collage, no vertical side panel.
        Palette: {$palette}.
        Lighting: crisp soft light, polished commercial finish, high contrast without looking harsh.
        Aspect ratio: {$aspectRatio}.
        Safe area: {$safeArea}
        Text policy: {$textInstruction}
        Quality bar: high detail, production-ready poster, premium but grounded, no generic stock-photo feeling, no fake brand marks on props.
        Negative constraints: {$negative}
        PROMPT);

        return [
            'original_prompt' => $brief,
            'enhanced_prompt' => $enhancedPrompt,
            'negative_prompt' => $negative,
            'overlay_config' => [
                'headline' => $headline,
                'brand_handle' => $brand['threads_handle'] ?? 'gimoradigital.id',
                'placement' => $aspectRatio === '9:16' ? 'bottom' : 'lower_third',
                'enabled' => ! $allowAiText,
            ],
            'model_params' => [
                'style' => $style,
                'aspect_ratio' => $aspectRatio,
                'allow_ai_text' => $allowAiText,
                'prompt_template_version' => $this->prompts->version(),
            ],
            'prompt_template_version' => $this->prompts->version(),
        ];
    }

    private function shortHeadline(string $headline): string
    {
        $words = preg_split('/\s+/', trim($headline)) ?: [];

        return implode(' ', array_slice($words, 0, 6));
    }

    private function styleInstruction(string $style): string
    {
        return match ($style) {
            '3d' => 'premium 3D render with polished soft materials, subtle isometric depth, ecommerce website dashboard elements as abstract non-text shapes, product cards, shopping bag, receipt and admin-fee cues represented by simple coins or sliders; not a real photograph.',
            'illustration' => 'clean editorial illustration, modern vector-like shapes, warm Indonesian business context, premium but practical.',
            'minimalist' => 'minimalist premium poster, generous negative space, few refined objects, calm composition.',
            'cartoon' => 'friendly polished cartoon style, professional and not childish, simple shapes and expressive business props.',
            'photography' => 'premium realistic photography, natural Indonesian workspace, polished commercial lighting.',
            default => 'high-quality realistic social media poster, polished and production-ready.',
        };
    }
}
