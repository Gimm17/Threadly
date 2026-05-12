<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Post;
use App\Services\ThreadsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishToThreadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly Post $post,
    ) {}

    public function handle(ThreadsApiService $threadsApi): void
    {
        // Skip if already published or cancelled
        if (in_array($this->post->status, ['published', 'cancelled'])) {
            return;
        }

        try {
            $threadsApi->publish($this->post);

            Log::info('Post published to Threads', [
                'post_id' => $this->post->id,
                'threads_post_id' => $this->post->threads_post_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish post to Threads', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);

            // On final attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->post->update(['status' => 'failed']);

                Notification::notify(
                    userId: $this->post->created_by ?? 1,
                    type: 'post_failed',
                    title: 'Post gagal dipublish setelah 3x percobaan',
                    message: $e->getMessage(),
                    actionUrl: route('posts.edit', $this->post->id),
                    workspaceId: $this->post->workspace_id,
                );
            }

            throw $e; // Re-throw for retry
        }
    }
}
