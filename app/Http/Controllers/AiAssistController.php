<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AI\AIWorkflowService;
use App\Services\AI\ReadyPostWorkflowService;
use App\Models\GeneratedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistController extends Controller
{
    public function __construct(
        private readonly AIWorkflowService $ai,
        private readonly ReadyPostWorkflowService $readyPost,
    ) {}

    public function contentAssist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:1000'],
            'pillar' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'assist' => $this->ai->contentAssist($validated, auth()->user()->workspace_id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI belum bisa membuat bantuan konten saat ini.',
            ], 422);
        }
    }

    /**
     * Generate a hook/opening line for a Threads post.
     */
    public function generateHook(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'pillar' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $hooks = $this->ai->generateHook(
                topic: (string) $request->topic,
                pillar: $request->pillar,
                workspaceId: auth()->user()->workspace_id,
            );
            $firstHook = $hooks[0]['hook'] ?? '';

            return response()->json([
                'success' => true,
                'hook' => $firstHook,
                'hooks' => $hooks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate hook.',
            ], 422);
        }
    }

    public function generateHashtags(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $hashtags = $this->ai->generateHashtags((string) $request->text, auth()->user()->workspace_id);

            return response()->json([
                'success' => true,
                'hashtags' => $hashtags,
            ]);
        } catch (\Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate hashtag.',
            ], 422);
        }
    }

    /**
     * Improve/rewrite post text for better engagement.
     */
    public function improveText(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'instruction' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $instruction = $request->instruction ?? 'Perbaiki tata bahasa dan buat lebih engaging';
            $result = $this->ai->improveText((string) $request->text, (string) $instruction, auth()->user()->workspace_id);

            return response()->json([
                'success' => true,
                'text' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbaiki teks.',
            ], 422);
        }
    }

    /**
     * Generate content ideas based on a pillar/topic.
     */
    public function generateIdeas(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $count = $request->count ?? 5;
            $ideas = $this->ai->generateIdeas((string) $request->topic, (int) $count, auth()->user()->workspace_id);

            return response()->json([
                'success' => true,
                'ideas' => $ideas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate ide.',
            ], 422);
        }
    }

    public function readyPost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:1000'],
            'pillar' => ['nullable', 'string', 'max:100'],
            'content_pillar_id' => ['nullable', 'exists:content_pillars,id'],
            'tone' => ['nullable', 'string', 'max:80'],
            'headline' => ['nullable', 'string', 'max:80'],
            'style' => ['nullable', 'string', 'in:realistic,illustration,cartoon,minimalist,3d,photography'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,4:5,16:9,9:16'],
            'quality' => ['nullable', 'string', 'in:auto,low,medium,high'],
            'background' => ['nullable', 'string', 'in:auto,transparent,opaque'],
            'generate_image' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->readyPost->generate(
                input: $validated,
                workspaceId: (int) auth()->user()->workspace_id,
                userId: (int) auth()->id(),
            );
            $post = $result['post'] ?? [];
            $media = $result['media'] ?? null;
            $imagePrompt = $result['image_prompt'] ?? null;
            $generatedPost = GeneratedPost::create([
                'workspace_id' => auth()->user()->workspace_id,
                'created_by' => auth()->id(),
                'source' => 'ready_post',
                'prompt' => $validated['topic'],
                'hook' => $post['hook'] ?? null,
                'body' => $post['body'] ?? '',
                'hashtags' => $post['hashtags'] ?? [],
                'media_id' => $media['id'] ?? null,
                'status' => 'generated',
                'metadata' => [
                    'input' => $validated,
                    'pillar' => $validated['pillar'] ?? null,
                    'content_pillar_id' => $validated['content_pillar_id'] ?? null,
                    'tone' => $validated['tone'] ?? null,
                    'headline' => $post['headline'] ?? null,
                    'poster_brief' => $post['poster_brief'] ?? null,
                    'image_prompt' => $imagePrompt,
                    'style' => $validated['style'] ?? 'photography',
                    'aspect_ratio' => $validated['aspect_ratio'] ?? '1:1',
                    'quality_notes' => $post['quality_notes'] ?? [],
                    'image_error' => $result['image_error'] ?? null,
                ],
            ]);

            return response()->json([
                'success' => true,
                ...$result,
                'generated_post' => $this->generatedPostResponse($generatedPost->load(['media', 'post'])),
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => app()->environment('local')
                    ? 'AI error: ' . $e->getMessage()
                    : 'AI belum bisa membuat paket post siap posting saat ini.',
            ], 422);
        }
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
