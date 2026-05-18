<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use BelongsToWorkspace;

    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'feature',
        'model_id',
        'provider',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_input_tokens',
        'estimated_output_tokens',
        'cache_hit',
        'cost',
        'cost_source',
        'response_time_ms',
        'is_success',
        'error_message',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_input_tokens' => 'integer',
        'estimated_output_tokens' => 'integer',
        'cache_hit' => 'boolean',
        'cost' => 'decimal:6',
        'response_time_ms' => 'integer',
        'is_success' => 'boolean',
    ];

    // ─── Relationships ───

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
