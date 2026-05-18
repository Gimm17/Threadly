<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Workspace;

class PromptTemplateService
{
    public function version(): string
    {
        return (string) config('ai-prompts.version', 'unversioned');
    }

    public function brandProfile(?int $workspaceId): array
    {
        $defaults = config('ai-prompts.brand_profile', []);
        $workspace = $workspaceId ? Workspace::withoutGlobalScopes()->find($workspaceId) : null;
        $custom = $workspace?->settings['brand_profile'] ?? [];

        return array_replace($defaults, is_array($custom) ? $custom : []);
    }

    public function systemPrompt(string $role, ?int $workspaceId): string
    {
        $brand = $this->brandProfile($workspaceId);
        $avoid = implode(', ', $brand['avoid'] ?? []);
        $examples = implode(', ', $brand['content_examples'] ?? []);

        return trim(<<<PROMPT
        Kamu adalah {$role} untuk brand {$brand['brand_name']}.
        Bahasa output: {$brand['language']}.
        Audiens utama: {$brand['audience']}.
        Tone: {$brand['tone']}.
        Positioning brand: {$brand['positioning']}.
        Contoh konteks bisnis yang disukai: {$examples}.
        Hindari: {$avoid}.
        Semua output harus ringkas, spesifik, bebas typo, dan siap dipakai di Threads.
        PROMPT);
    }

    public function workflow(string $name): array
    {
        return config("ai-prompts.workflows.{$name}", []);
    }
}
