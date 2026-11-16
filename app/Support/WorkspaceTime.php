<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeZone;
use Illuminate\Support\Carbon;

class WorkspaceTime
{
    public static function timezone(?string $timezone): string
    {
        $fallback = config('app.timezone', 'UTC') ?: 'UTC';
        $candidate = $timezone ?: $fallback;

        if (in_array($candidate, DateTimeZone::listIdentifiers(), true)) {
            return $candidate;
        }

        return in_array($fallback, DateTimeZone::listIdentifiers(), true)
            ? $fallback
            : 'UTC';
    }

    /**
     * Returns the current workspace-local day as UTC bounds for timestamp queries.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function todayUtcBounds(?string $timezone): array
    {
        $resolvedTimezone = self::timezone($timezone);
        $start = Carbon::now($resolvedTimezone)->startOfDay();
        $end = $start->copy()->endOfDay();

        return [
            $start->copy()->utc(),
            $end->copy()->utc(),
        ];
    }

    public static function daysAgoUtc(?string $timezone, int $days): Carbon
    {
        return Carbon::now(self::timezone($timezone))
            ->subDays($days)
            ->utc();
    }
}
