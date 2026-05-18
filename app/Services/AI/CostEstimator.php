<?php

declare(strict_types=1);

namespace App\Services\AI;

class CostEstimator
{
    public function __construct(
        private readonly ModelCatalogService $catalog,
    ) {}

    public function estimateTokens(string|array $content): int
    {
        $text = is_array($content) ? json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $content;

        return max(1, (int) ceil(mb_strlen((string) $text) / 4));
    }

    public function textCost(string $modelId, int $inputTokens, int $outputTokens): float
    {
        $model = $this->catalog->find($modelId);
        $inputRate = (float) ($model['input_price_per_million'] ?? 0.25);
        $outputRate = (float) ($model['output_price_per_million'] ?? 1.00);

        return round(($inputTokens / 1_000_000 * $inputRate) + ($outputTokens / 1_000_000 * $outputRate), 6);
    }

    public function imageCost(string $modelId, int $count = 1): float
    {
        $model = $this->catalog->find($modelId);

        return round((float) ($model['image_price_per_call'] ?? 0.030) * max(1, $count), 6);
    }
}
