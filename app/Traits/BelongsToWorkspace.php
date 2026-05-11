<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        // Auto-scope all queries to the authenticated user's workspace
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where(
                    (new static)->getTable() . '.workspace_id',
                    auth()->user()->workspace_id
                );
            }
        });

        // Auto-fill workspace_id on create
        static::creating(function ($model) {
            if (auth()->check() && empty($model->workspace_id)) {
                $model->workspace_id = auth()->user()->workspace_id;
            }
        });
    }
}
