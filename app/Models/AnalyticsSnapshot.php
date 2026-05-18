<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSnapshot extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'snapshot_date',
        'followers_count',
        'following_count',
        'impressions',
        'likes',
        'replies',
        'reposts',
        'quotes',
        'reach',
        'engagement',
        'engagement_rate',
        'posts_published',
        'saves',
        'shares',
        'source',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'followers_count' => 'integer',
        'following_count' => 'integer',
        'impressions' => 'integer',
        'likes' => 'integer',
        'replies' => 'integer',
        'reposts' => 'integer',
        'quotes' => 'integer',
        'reach' => 'integer',
        'engagement' => 'integer',
        'engagement_rate' => 'float',
        'posts_published' => 'integer',
        'saves' => 'integer',
        'shares' => 'integer',
    ];

    // ─── Scopes ───

    public function scopeLastDays(Builder $query, int $days = 30): Builder
    {
        return $query->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
                     ->orderBy('snapshot_date');
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('snapshot_date', [$from, $to])
                     ->orderBy('snapshot_date');
    }

    // ─── Relationships ───

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
