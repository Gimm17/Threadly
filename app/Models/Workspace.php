<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'threads_handle',
        'threads_access_token',
        'timezone',
        'plan',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $hidden = [
        'threads_access_token',
    ];

    public function getThreadsAccessTokenAttribute(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function setThreadsAccessTokenAttribute(?string $value): void
    {
        $this->attributes['threads_access_token'] = blank($value)
            ? null
            : Crypt::encryptString($value);
    }

    // ─── Relationships ───

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'owner');
    }

    public function contentPillars(): HasMany
    {
        return $this->hasMany(ContentPillar::class);
    }

    public function contentIdeas(): HasMany
    {
        return $this->hasMany(ContentIdea::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function hookTemplates(): HasMany
    {
        return $this->hasMany(HookTemplate::class);
    }

    public function aiModelConfigs(): HasMany
    {
        return $this->hasMany(AiModelConfig::class);
    }

    public function analyticsSnapshots(): HasMany
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
