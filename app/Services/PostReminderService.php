<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendPostReminderJob;
use App\Models\Post;

class PostReminderService
{
    /**
     * Schedule a reminder for a post if it has a scheduled_at time.
     */
    public function scheduleReminder(Post $post): void
    {
        if (! $post->scheduled_at || $post->status !== 'scheduled' || $post->publish_mode !== 'manual') {
            return;
        }

        $offset = $this->getOffsetMinutes();
        $reminderAt = $post->scheduled_at->copy()->subMinutes($offset);

        // Only schedule if the reminder time is in the future
        if ($reminderAt->isFuture()) {
            SendPostReminderJob::dispatch($post)->delay($reminderAt);
        }
    }

    /**
     * Get the reminder offset in minutes from config.
     */
    public function getOffsetMinutes(): int
    {
        return (int) config('threadly.reminder_offset_minutes', 30);
    }
}
