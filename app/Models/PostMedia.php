<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    protected $fillable = [
        'post_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'type',
        'sort_order',
        'is_ai_generated',
        'ai_prompt',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_ai_generated' => 'boolean',
    ];

    // ─── Relationships ───

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    // ─── Accessors ───

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
