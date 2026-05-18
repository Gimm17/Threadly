<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedMedia extends Model
{
    use BelongsToWorkspace;

    protected $table = 'generated_media';

    protected $fillable = [
        'workspace_id',
        'created_by',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'prompt',
        'original_prompt',
        'enhanced_prompt',
        'negative_prompt',
        'provider',
        'model_id',
        'style',
        'aspect_ratio',
        'generation_mode',
        'model_params',
        'overlay_config',
        'prompt_template_version',
        'generation_status',
        'estimated_cost',
        'error_message',
        'post_id',
        'is_favorite',
    ];

    protected $appends = ['url'];

    protected $casts = [
        'file_size' => 'integer',
        'is_favorite' => 'boolean',
        'model_params' => 'array',
        'overlay_config' => 'array',
        'estimated_cost' => 'decimal:6',
    ];

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    // Accessors

    public function getUrlAttribute(): string
    {
        if (blank($this->file_path)) {
            return '';
        }

        return asset('storage/' . $this->file_path);
    }
}
