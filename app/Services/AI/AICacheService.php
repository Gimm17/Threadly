<?php

declare(strict_types=1);

namespace App\Services\AI;

use Closure;
use Illuminate\Support\Facades\Cache;

class AICacheService
{
    public function remember(
        string $feature,
        ?int $workspaceId,
        string $modelId,
        array|string $input,
        Closure $callback,
        bool $enabled = true,
    ): mixed {
        if (! $enabled) {
            return $callback(false);
        }

        $key = $this->key($feature, $workspaceId, $modelId, $input);
        $hit = Cache::has($key);

        return Cache::remember(
            $key,
            now()->addDays((int) env('AI_CACHE_TTL_DAYS', 14)),
            fn () => $callback($hit),
        );
    }

    public function key(string $feature, ?int $workspaceId, string $modelId, array|string $input): string
    {
        $payload = is_array($input)
            ? json_encode($this->ksortRecursive($input), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : trim(preg_replace('/\s+/', ' ', $input) ?? $input);

        $promptVersion = (string) config('ai-prompts.version', 'unversioned');

        return 'ai:' . sha1("{$promptVersion}|{$feature}|{$workspaceId}|{$modelId}|{$payload}");
    }

    private function ksortRecursive(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->ksortRecursive($item);
            }
        }

        return $value;
    }
}
