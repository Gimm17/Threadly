<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContentPillar;
use App\Services\AI\AIWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CopywritingController extends Controller
{
    public function __construct(
        private readonly AIWorkflowService $ai,
    ) {}

    /**
     * Display the Copywriting AI page.
     */
    public function index(): Response
    {
        return Inertia::render('Copywriting/Index', [
            'contentPillars' => ContentPillar::active()->ordered()->get(['id', 'name']),
        ]);
    }

    /**
     * Generate full post copy from a topic/brief.
     */
    public function generatePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'in:casual,professional,humorous,inspirational,educational'],
            'pillar_id' => ['nullable', 'integer', 'exists:content_pillars,id'],
            'max_length' => ['nullable', 'integer', 'min:100', 'max:500'],
            'include_hashtags' => ['nullable', 'boolean'],
            'include_cta' => ['nullable', 'boolean'],
        ]);

        try {
            $pillar = filled($validated['pillar_id'] ?? null)
                ? ContentPillar::find($validated['pillar_id'])?->name
                : null;

            $result = $this->ai->generatePost([
                ...$validated,
                'pillar' => $pillar,
            ], auth()->user()->workspace_id);

            return response()->json([
                'success' => true,
                'content' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate post.',
            ], 422);
        }
    }

    /**
     * Generate multiple caption variations.
     */
    public function generateVariations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_text' => ['required', 'string', 'max:2000'],
            'num_variations' => ['nullable', 'integer', 'min:2', 'max:5'],
        ]);

        try {
            $count = $validated['num_variations'] ?? 3;
            $variations = $this->ai->generateVariations($validated['original_text'], (int) $count, auth()->user()->workspace_id);

            return response()->json([
                'success' => true,
                'variations' => $variations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate variasi.',
            ], 422);
        }
    }

    /**
     * Generate a thread (multi-post sequence).
     */
    public function generateThread(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'num_posts' => ['nullable', 'integer', 'min:2', 'max:10'],
            'tone' => ['nullable', 'string', 'in:casual,professional,humorous,inspirational,educational'],
            'pillar_id' => ['nullable', 'integer', 'exists:content_pillars,id'],
        ]);

        try {
            $thread = $this->ai->generateThread($validated, auth()->user()->workspace_id);
            $posts = array_map(fn ($item) => is_array($item) ? ($item['text'] ?? '') : (string) $item, $thread);

            return response()->json([
                'success' => true,
                'posts' => $posts,
                'thread' => $thread,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate thread.',
            ], 422);
        }
    }
}
