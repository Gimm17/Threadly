<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ThreadsApiService
{
    private const BASE_URL = 'https://graph.threads.net/v1.0';

    private function verifySsl(): bool
    {
        return filter_var(config('services.threads.verify_ssl', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * Publish a post to Threads API.
     * Two-step process: 1) Create media container, 2) Publish.
     */
    public function publish(Post $post): array
    {
        $workspace = $post->workspace ?? Workspace::findOrFail($post->workspace_id);
        $accessToken = $workspace->threads_access_token;

        if (empty($accessToken)) {
            throw new RuntimeException('Threads access token belum dikonfigurasi untuk workspace ini.');
        }

        $userId = $this->getThreadsUserId($accessToken);

        // Step 1: Create media container
        $containerId = $this->createMediaContainer($userId, $accessToken, $post);

        // Step 2: Wait and publish
        sleep(2); // Give Threads API time to process
        $threadId = $this->publishContainer($userId, $accessToken, $containerId);

        // Update post
        $post->update([
            'threads_post_id' => $threadId,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Create notification
        Notification::notify(
            userId: $post->created_by ?? $workspace->owner?->id ?? 1,
            type: 'post_published',
            title: 'Post berhasil dipublish ke Threads!',
            message: mb_substr($post->body, 0, 100),
            actionUrl: route('posts.edit', $post->id),
            workspaceId: $workspace->id,
        );

        return [
            'success' => true,
            'threads_post_id' => $threadId,
        ];
    }

    /**
     * Get Threads user ID from access token.
     */
    public function getThreadsUserId(string $accessToken): string
    {
        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->get(self::BASE_URL . '/me', [
                'fields' => 'id,username,name,threads_profile_picture_url',
                'access_token' => $accessToken,
            ]);

        if ($response->failed()) {
            Log::error('Threads API: Failed to get user ID', ['response' => $response->body()]);
            throw new RuntimeException('Gagal mengambil user ID dari Threads API: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Get user profile from Threads API.
     */
    public function getProfile(string $accessToken): array
    {
        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->get(self::BASE_URL . '/me', [
                'fields' => 'id,username,name,threads_profile_picture_url,threads_biography',
                'access_token' => $accessToken,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil profil dari Threads API.');
        }

        return $response->json();
    }

    /**
     * Get user's threads/posts from API.
     */
    public function getUserThreads(string $accessToken, int $limit = 25): array
    {
        $userId = $this->getThreadsUserId($accessToken);

        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->get(self::BASE_URL . "/{$userId}/threads", [
                'fields' => 'id,text,timestamp,media_type,media_url,permalink,is_quote_post',
                'limit' => $limit,
                'access_token' => $accessToken,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil threads dari API.');
        }

        return $response->json('data', []);
    }

    /**
     * Get insights for a specific thread post.
     */
    public function getThreadInsights(string $accessToken, string $threadId): array
    {
        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->get(self::BASE_URL . "/{$threadId}/insights", [
                'metric' => 'views,likes,replies,reposts,quotes',
                'access_token' => $accessToken,
            ]);

        if ($response->failed()) {
            return [];
        }

        $insights = [];
        foreach ($response->json('data', []) as $metric) {
            $insights[$metric['name']] = $metric['values'][0]['value'] ?? 0;
        }

        return $insights;
    }

    /**
     * Get user-level insights (follower count, etc).
     */
    public function getUserInsights(string $accessToken): array
    {
        $userId = $this->getThreadsUserId($accessToken);

        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->get(self::BASE_URL . "/{$userId}/threads_insights", [
                'metric' => 'views,likes,replies,reposts,quotes,followers_count',
                'access_token' => $accessToken,
            ]);

        if ($response->failed()) {
            return [];
        }

        $insights = [];
        foreach ($response->json('data', []) as $metric) {
            $insights[$metric['name']] = $metric['values'][0]['value'] ?? $metric['total_value']['value'] ?? 0;
        }

        return $insights;
    }

    /**
     * Create a media container (Step 1 of publishing).
     */
    private function createMediaContainer(string $userId, string $accessToken, Post $post): string
    {
        $params = [
            'media_type' => 'TEXT',
            'text' => $post->body,
            'access_token' => $accessToken,
        ];

        // If post has media, switch to IMAGE type
        $media = $post->media()->first();
        if ($media && $media->type === 'image') {
            $params['media_type'] = 'IMAGE';
            $params['image_url'] = url($media->url);
        }

        // If post has a link
        if (!empty($post->link_url)) {
            $params['text'] .= "\n\n" . $post->link_url;
        }

        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->post(self::BASE_URL . "/{$userId}/threads", $params);

        if ($response->failed()) {
            Log::error('Threads API: Failed to create container', [
                'response' => $response->body(),
                'post_id' => $post->id,
            ]);

            // Mark post as failed and notify
            $post->update(['status' => 'failed']);
            Notification::notify(
                userId: $post->created_by ?? 1,
                type: 'post_failed',
                title: 'Post gagal dipublish ke Threads',
                message: 'Error: ' . mb_substr($response->body(), 0, 200),
                actionUrl: route('posts.edit', $post->id),
                workspaceId: $post->workspace_id,
            );

            throw new RuntimeException('Gagal membuat media container: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Publish the container (Step 2 of publishing).
     */
    private function publishContainer(string $userId, string $accessToken, string $containerId): string
    {
        $response = Http::withOptions(['verify' => $this->verifySsl()])
            ->post(self::BASE_URL . "/{$userId}/threads_publish", [
                'creation_id' => $containerId,
                'access_token' => $accessToken,
            ]);

        if ($response->failed()) {
            Log::error('Threads API: Failed to publish', [
                'response' => $response->body(),
                'container_id' => $containerId,
            ]);
            throw new RuntimeException('Gagal publish ke Threads: ' . $response->body());
        }

        return $response->json('id');
    }
}
