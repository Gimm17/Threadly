<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HookTemplate;
use App\Services\AI\AIWorkflowService;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HookTemplateController extends Controller
{
    public function __construct(
        private readonly AIService $aiService,
        private readonly AIWorkflowService $ai,
    ) {}

    public function index(Request $request): Response
    {
        $query = HookTemplate::query();

        if ($request->filled('category')) {
            $query->forCategory($request->category);
        }

        $sort = $request->get('sort', 'score');
        $hooks = match ($sort) {
            'saves' => $query->bySaveRate()->paginate(20),
            'newest' => $query->latest()->paginate(20),
            default => $query->topPerforming(100)->paginate(20),
        };

        $categories = HookTemplate::query()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return Inertia::render('Hooks/Index', [
            'hooks' => $hooks,
            'categories' => $categories,
            'filters' => [
                'category' => $request->get('category'),
                'sort' => $sort,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hook_text' => ['required', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        HookTemplate::create($validated);

        return redirect()->back()
            ->with('success', 'Hook template berhasil ditambahkan.');
    }

    public function destroy(HookTemplate $hookTemplate): RedirectResponse
    {
        $hookTemplate->delete();

        return redirect()->back()
            ->with('success', 'Hook template berhasil dihapus.');
    }

    /**
     * Increment save count (user "bookmarked" a hook).
     */
    public function save(HookTemplate $hookTemplate): JsonResponse
    {
        $hookTemplate->increment('save_count');

        return response()->json([
            'success' => true,
            'save_count' => $hookTemplate->fresh()->save_count,
        ]);
    }

    /**
     * Increment usage count (user used a hook in a post).
     */
    public function use(HookTemplate $hookTemplate): JsonResponse
    {
        $hookTemplate->increment('usage_count');

        return response()->json([
            'success' => true,
            'usage_count' => $hookTemplate->fresh()->usage_count,
        ]);
    }

    /**
     * AI-score a hook template for engagement potential.
     */
    public function score(HookTemplate $hookTemplate): JsonResponse
    {
        try {
            $prompt = "Beri skor 1-100 untuk hook/opening line berikut berdasarkan potensi engagement di Threads:\n\n\"{$hookTemplate->hook_text}\"\n\nKriteria penilaian:\n- Apakah membuat penasaran? (0-25)\n- Apakah relevan & relatable? (0-25)\n- Apakah singkat & powerful? (0-25)\n- Apakah ada call-to-engage? (0-25)\n\nOutput HANYA angka skor (integer), tanpa penjelasan.";

            $result = $this->aiService->complete(
                feature: 'hook_generator',
                userPrompt: $prompt,
                systemPrompt: 'Kamu adalah ahli copywriting media sosial. Berikan skor engagement untuk hook yang diberikan. Output HANYA angka integer 1-100.',
                maxTokens: 20,
            );

            $score = (int) trim($result);
            $score = max(1, min(100, $score));

            $hookTemplate->update(['score' => $score]);

            return response()->json([
                'success' => true,
                'score' => $score,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghitung skor: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate AI hooks and add to library.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $count = $request->get('count', 5);
            $hooks = array_slice($this->ai->generateHook(
                topic: (string) $request->topic,
                pillar: $request->get('category'),
                workspaceId: auth()->user()->workspace_id,
            ), 0, $count);
            $created = [];

            foreach ($hooks as $hook) {
                $hookText = $hook['hook'] ?? $hook['hook_text'] ?? null;
                if (!empty($hookText)) {
                    $created[] = HookTemplate::create([
                        'workspace_id' => auth()->user()->workspace_id,
                        'hook_text' => $hookText,
                        'category' => $request->get('category', 'AI Generated'),
                        'score' => $hook['score'] ?? 0,
                        'is_ai_generated' => true,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'hooks' => $created,
                'count' => count($created),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate hooks: ' . $e->getMessage(),
            ], 422);
        }
    }
}
