<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedPost extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'source',
        'prompt',
        'hook',
        'body',
        'hashtags',
        'media_id',
        'post_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'metadata' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(GeneratedMedia::class, 'media_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
