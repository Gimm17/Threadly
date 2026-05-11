<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPillar extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'color_hex',
        'icon',
        'frequency_unit',
        'frequency_value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'frequency_value' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ─── Scopes ───

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ─── Relationships ───

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contentIdeas(): HasMany
    {
        return $this->hasMany(ContentIdea::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    // ─── Accessors ───

    public function getFrequencyLabelAttribute(): string
    {
        $unitLabel = match ($this->frequency_unit) {
            'day' => 'Hari',
            'week' => 'Minggu',
            'month' => 'Bulan',
            default => $this->frequency_unit,
        };

        return "{$this->frequency_value}x/{$unitLabel}";
    }
}
