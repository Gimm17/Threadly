<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\PostReminderMail;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPostReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly Post $post,
    ) {}

    public function handle(): void
    {
        // Reload to ensure latest state
        $post = $this->post->fresh();

        // Skip if post is no longer scheduled or reminder already sent
        if (! $post || $post->status !== 'scheduled' || $post->reminder_sent_at) {
            return;
        }

        // Get the workspace owner's email
        $creator = $post->creator;
        if (! $creator) {
            return;
        }

        Mail::to($creator->email)->send(new PostReminderMail($post));

        $post->update(['reminder_sent_at' => now()]);
    }
}
