<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelConfig extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'feature',
        'provider',
        'model_id',
        'temperature',
        'max_tokens',
        'system_prompt',
        'is_active',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ───

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ─── Accessors ───

    public function getFeatureLabelAttribute(): string
    {
        return match ($this->feature) {
            'hook_generator' => 'Hook Generator',
            'copywriting' => 'Copywriting AI',
            'image_generation' => 'Image Generation',
            'insight' => 'Daily Insights',
            default => ucfirst(str_replace('_', ' ', $this->feature)),
        };
    }

    public function getFeatureIconAttribute(): string
    {
        return match ($this->feature) {
            'hook_generator' => 'sparkles',
            'copywriting' => 'file-text',
            'image_generation' => 'photo',
            'insight' => 'bulb',
            default => 'settings',
        };
    }
}
