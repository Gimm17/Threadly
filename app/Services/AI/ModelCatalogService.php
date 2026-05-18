<?php

declare(strict_types=1);

namespace App\Services\AI;

class ModelCatalogService
{
    public function all(): array
    {
        return array_values(array_filter(
            config('ai-models', []),
            static fn ($model) => is_array($model) && filled($model['id'] ?? null),
        ));
    }

    public function find(string $modelId): array
    {
        foreach ($this->all() as $model) {
            if (($model['id'] ?? null) === $modelId) {
                return $model;
            }
        }

        return $this->infer($modelId);
    }

    public function supportsJsonMode(string $modelId): bool
    {
        return (bool) ($this->find($modelId)['supports_json_mode'] ?? false);
    }

    public function endpointType(string $modelId): string
    {
        $model = $this->find($modelId);

        if (! ($model['supports_image'] ?? false)) {
            return 'chat';
        }

        return (string) ($model['endpoint_type'] ?? 'images');
    }

    public function supportsTextInImage(string $modelId): bool
    {
        return (bool) ($this->find($modelId)['text_in_image'] ?? false);
    }

    public function sizeForAspectRatio(string $aspectRatio): string
    {
        return match ($aspectRatio) {
            '16:9' => '1536x1024',
            '9:16' => '1024x1536',
            '4:5' => '1024x1280',
            default => '1024x1024',
        };
    }

    private function infer(string $modelId): array
    {
        $vendor = str($modelId)->before('/')->lower()->toString();
        $isImage = str_contains($modelId, 'image') || str_contains($modelId, 'seedream');

        return [
            'id' => $modelId,
            'name' => $modelId,
            'vendor' => $vendor ?: 'custom',
            'desc' => 'Custom model',
            'capabilities' => $isImage ? ['image'] : ['text'],
            'endpoint_type' => str_starts_with($modelId, 'google/') ? 'chat' : ($isImage ? 'images' : 'chat'),
            'supports_json_mode' => str_starts_with($modelId, 'openai/') || str_starts_with($modelId, 'deepseek/'),
            'supports_image' => $isImage,
            'supports_text' => ! $isImage || str_contains($modelId, 'gpt') || str_contains($modelId, 'gemini'),
            'text_in_image' => str_starts_with($modelId, 'openai/') || str_starts_with($modelId, 'google/'),
            'input_price_per_million' => 0.25,
            'output_price_per_million' => 1.00,
            'image_price_per_call' => $isImage ? 0.030 : 0.000,
            'recommended_cost_mode' => 'default',
        ];
    }
}
