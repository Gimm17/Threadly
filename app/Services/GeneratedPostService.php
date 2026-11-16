<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PublishToThreadsJob;
use App\Models\GeneratedPost;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use RuntimeException;

class GeneratedPostService
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly PostReminderService $postReminderService,
    ) {}

    public function createDraft(GeneratedPost $generatedPost, array $overrides = []): Post
    {
        $this->applyOverrides($generatedPost, $overrides);

        $post = $this->postFor($generatedPost);

        if (! $post) {
            $post = $this->createPost($generatedPost, [
                'status' => 'draft',
                'scheduled_at' => null,
                'publish_mode' => 'manual',
            ]);
        } else {
            $post->update([
                'status' => 'draft',
                'scheduled_at' => null,
                'publish_mode' => 'manual',
                ...$this->postPayload($generatedPost),
            ]);
        }

        $generatedPost->update([
            'post_id' => $post->id,
            'status' => 'drafted',
        ]);

        return $post;
    }

    public function schedule(GeneratedPost $generatedPost, string $scheduledAt, string $publishMode, array $overrides = []): Post
    {
        $this->applyOverrides($generatedPost, $overrides);

        $post = $this->postFor($generatedPost);
        $values = [
            ...$this->postPayload($generatedPost),
            'status' => 'scheduled',
            'scheduled_at' => Carbon::parse($scheduledAt),
            'publish_mode' => $publishMode,
        ];

        if (! $post) {
            $post = $this->createPost($generatedPost, $values);
        } else {
            $post->update($values);
        }

        $generatedPost->update([
            'post_id' => $post->id,
            'status' => 'scheduled',
        ]);

        $this->postReminderService->scheduleReminder($post);

        return $post;
    }

    public function publishNow(GeneratedPost $generatedPost, array $overrides = []): Post
    {
        $this->applyOverrides($generatedPost, $overrides);

        $workspace = Workspace::withoutGlobalScopes()->find($generatedPost->workspace_id);

        if (! $workspace || blank($workspace->threads_access_token)) {
            throw new RuntimeException('Threads API belum dikonfigurasi. Simpan sebagai Draft atau atur token di Settings terlebih dahulu.');
        }

        $post = $this->postFor($generatedPost);

        if (! $post) {
            $post = $this->createPost($generatedPost, [
                'status' => 'draft',
                'scheduled_at' => null,
                'publish_mode' => 'auto',
            ]);
        } else {
            $post->update([
                ...$this->postPayload($generatedPost),
                'status' => 'draft',
                'scheduled_at' => null,
                'publish_mode' => 'auto',
            ]);
        }

        $generatedPost->update([
            'post_id' => $post->id,
            'status' => 'publishing',
        ]);

        PublishToThreadsJob::dispatch($post);

        return $post;
    }

    private function createPost(GeneratedPost $generatedPost, array $values): Post
    {
        $post = Post::withoutGlobalScopes()->create([
            'workspace_id' => $generatedPost->workspace_id,
            'created_by' => $generatedPost->created_by,
            ...$this->postPayload($generatedPost),
            ...$values,
        ]);

        if ($generatedPost->media) {
            $this->mediaUploadService->attachGeneratedMedia($generatedPost->media, $post);
        }

        return $post;
    }

    private function postFor(GeneratedPost $generatedPost): ?Post
    {
        if (! $generatedPost->post_id) {
            return null;
        }

        return Post::withoutGlobalScopes()
            ->where('workspace_id', $generatedPost->workspace_id)
            ->find($generatedPost->post_id);
    }

    private function applyOverrides(GeneratedPost $generatedPost, array $overrides): void
    {
        $metadata = $generatedPost->metadata ?? [];

        foreach (['content_pillar_id', 'link_url', 'notes'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $metadata[$key] = $overrides[$key];
            }
        }

        $generatedPost->fill(array_filter([
            'hook' => $overrides['hook'] ?? null,
            'body' => $overrides['body'] ?? null,
            'metadata' => $metadata,
        ], fn ($value) => $value !== null));

        if ($generatedPost->isDirty()) {
            $generatedPost->save();
        }
    }

    private function postPayload(GeneratedPost $generatedPost): array
    {
        return [
            'content_pillar_id' => $generatedPost->metadata['content_pillar_id'] ?? null,
            'body' => $generatedPost->body,
            'hook' => $generatedPost->hook,
            'notes' => $generatedPost->metadata['notes'] ?? null,
            'ai_model_used' => $generatedPost->metadata['model_id'] ?? null,
            'link_url' => $generatedPost->metadata['link_url'] ?? null,
        ];
    }
}
