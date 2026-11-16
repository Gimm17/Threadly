<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeneratedPost;
use App\Models\Post;
use App\Services\GeneratedPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneratedPostController extends Controller
{
    public function __construct(
        private readonly GeneratedPostService $generatedPosts,
    ) {}

    public function saveDraft(Request $request, GeneratedPost $generatedPost): JsonResponse
    {
        $this->authorizeWorkspace($generatedPost);

        $post = $this->generatedPosts->createDraft(
            $generatedPost,
            $this->validatedEditorPayload($request),
        );

        return $this->postActionResponse($generatedPost->fresh(), $post, 'Draft berhasil disimpan.');
    }

    public function schedule(Request $request, GeneratedPost $generatedPost): JsonResponse
    {
        $this->authorizeWorkspace($generatedPost);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'publish_mode' => ['nullable', 'in:manual,auto'],
        ]);

        $post = $this->generatedPosts->schedule(
            generatedPost: $generatedPost,
            scheduledAt: $validated['scheduled_at'],
            publishMode: $validated['publish_mode'] ?? 'manual',
            overrides: $this->validatedEditorPayload($request),
        );

        return $this->postActionResponse($generatedPost->fresh(), $post, 'Post berhasil dijadwalkan.');
    }

    public function publishNow(Request $request, GeneratedPost $generatedPost): JsonResponse
    {
        $this->authorizeWorkspace($generatedPost);

        try {
            $post = $this->generatedPosts->publishNow(
                $generatedPost,
                $this->validatedEditorPayload($request),
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return $this->postActionResponse($generatedPost->fresh(), $post, 'Post sedang dipublish ke Threads.');
    }

    private function authorizeWorkspace(GeneratedPost $generatedPost): void
    {
        abort_unless($generatedPost->workspace_id === auth()->user()?->workspace_id, 404);
    }

    private function validatedEditorPayload(Request $request): array
    {
        return $request->validate([
            'hook' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:500'],
            'content_pillar_id' => ['nullable', 'exists:content_pillars,id'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function postActionResponse(GeneratedPost $generatedPost, Post $post, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'generated_post' => $this->generatedPostResponse($generatedPost->loadMissing(['media', 'post'])),
            'post' => [
                'id' => $post->id,
                'status' => $post->status,
                'edit_url' => route('posts.edit', $post),
                'index_url' => route('posts.index'),
            ],
        ]);
    }

    private function generatedPostResponse(GeneratedPost $generatedPost): array
    {
        return [
            'id' => $generatedPost->id,
            'prompt' => $generatedPost->prompt,
            'hook' => $generatedPost->hook,
            'body' => $generatedPost->body,
            'hashtags' => $generatedPost->hashtags ?? [],
            'status' => $generatedPost->status,
            'created_at' => $generatedPost->created_at?->diffForHumans(),
            'post_id' => $generatedPost->post_id,
            'post_url' => $generatedPost->post_id ? route('posts.edit', $generatedPost->post_id) : null,
            'image_prompt' => $generatedPost->metadata['image_prompt'] ?? null,
            'image_error' => $generatedPost->metadata['image_error'] ?? null,
            'media' => $generatedPost->media ? [
                'id' => $generatedPost->media->id,
                'url' => $generatedPost->media->url,
                'file_name' => $generatedPost->media->file_name,
                'mime_type' => $generatedPost->media->mime_type,
                'file_size' => $generatedPost->media->file_size,
            ] : null,
        ];
    }
}
