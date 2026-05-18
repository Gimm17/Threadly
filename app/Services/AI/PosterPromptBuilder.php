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

        $textInstruction = $allowAiText && $headline !== ''
            ? "Include only this short, readable headline text: \"{$this->shortHeadline($headline)}\". No other text."
            : 'Do not render any text, letters, numbers, UI labels, chart labels, logos, or document writing in the image. Leave clean space for a separate text overlay.';

        $enhancedPrompt = trim(<<<PROMPT
        Create a premium social media poster background for Threads/Instagram.
        Objective: {$objective}.
        Brand cue: {$brand['brand_name']} (@{$brand['threads_handle']}), modern Indonesian technology partner, trustworthy and practical.
        Audience: {$brand['audience']}.
        Core brief: {$brief}.
        Foreground subject: {$subject}.
        Scene and props: realistic Indonesian business/admin context, tidy desk or work setup, subtle visual hints of invoice, stock checklist, customer chat, or daily report when relevant, but all screens and papers must be blank or use abstract non-text shapes only.
        Composition: editorial poster, clear focal point, clean hierarchy, strong depth, balanced negative space, no crowded objects.
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
}
