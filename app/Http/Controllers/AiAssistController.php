<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AI\AIWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistController extends Controller
{
    public function __construct(
        private readonly AIWorkflowService $ai,
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
}
