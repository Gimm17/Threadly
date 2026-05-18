<?php

declare(strict_types=1);

namespace App\Services\AI;

class JsonResponseParser
{
    public function parse(string $response, mixed $fallback = []): mixed
    {
        $cleaned = $this->clean($response);
        $decoded = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (preg_match('/(\{.*\}|\[.*\])/s', $cleaned, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $fallback;
    }

    public function clean(string $response): string
    {
        $cleaned = trim($response);

        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
        }

        return trim($cleaned);
    }
}
