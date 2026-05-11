<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HookTemplate extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'hook_text',
        'category',
        'score',
        'usage_count',
        'save_count',
        'view_count',
        'is_ai_generated',
    ];

    protected $casts = [
        'score' => 'integer',
        'usage_count' => 'integer',
        'save_count' => 'integer',
        'view_count' => 'integer',
        'is_ai_generated' => 'boolean',
    ];

    // ─── Scopes ───

    public function scopeTopPerforming(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByDesc('score')->limit($limit);
    }

    public function scopeBySaveRate(Builder $query): Builder
    {
        return $query->orderByDesc('save_count');
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    // ─── Relationships ───

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
