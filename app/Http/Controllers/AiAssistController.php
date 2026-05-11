<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistController extends Controller
{
    public function __construct(
        private readonly AIService $aiService,
    ) {}

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
            $prompt = "Buatkan 1 hook/opening line yang menarik untuk post Threads tentang: {$request->topic}";
            if ($request->pillar) {
                $prompt .= "\nContent pillar: {$request->pillar}";
            }
            $prompt .= "\n\nKriteria hook yang baik:\n- Singkat (maksimal 2 kalimat)\n- Membuat penasaran\n- Relevan dengan audiens Indonesia\n- Tidak clickbait\n\nHANYA output hook-nya saja, tanpa penjelasan atau tanda kutip.";

            $result = $this->aiService->complete(
                feature: 'hook_gen',
                userPrompt: $prompt,
                systemPrompt: 'Kamu adalah copywriter ahli untuk platform Threads (Meta). Buatkan hook yang engaging dalam bahasa Indonesia.',
            );

            return response()->json([
                'success' => true,
                'hook' => trim($result, " \t\n\r\0\x0B\"'"),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate hook: ' . $e->getMessage(),
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

            $prompt = "Perbaiki teks berikut untuk post Threads:\n\n\"{$request->text}\"\n\nInstruksi: {$instruction}\n\nKriteria:\n- Tetap natural dan conversational\n- Gunakan bahasa Indonesia yang baik\n- Maksimal 500 karakter\n- Jaga esensi pesan asli\n\nHANYA output teks yang sudah diperbaiki, tanpa penjelasan.";

            $result = $this->aiService->complete(
                feature: 'copywriting',
                userPrompt: $prompt,
                systemPrompt: 'Kamu adalah editor konten media sosial. Perbaiki teks agar lebih menarik di Threads.',
            );

            return response()->json([
                'success' => true,
                'text' => trim($result, " \t\n\r\0\x0B\"'"),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbaiki teks: ' . $e->getMessage(),
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

            $prompt = "Buatkan {$count} ide konten Threads tentang: {$request->topic}\n\nFormat output JSON array:\n[{\"title\": \"Judul ide\", \"description\": \"Deskripsi singkat 1 kalimat\"}]\n\nHANYA output JSON array, tanpa teks lain.";

            $result = $this->aiService->complete(
                feature: 'copywriting',
                userPrompt: $prompt,
                systemPrompt: 'Kamu adalah content strategist untuk media sosial Threads. Berikan ide kreatif dalam bahasa Indonesia. Selalu output dalam format JSON array yang valid.',
            );

            // Parse JSON response
            $cleaned = trim($result);
            if (str_starts_with($cleaned, '```')) {
                $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned);
                $cleaned = preg_replace('/\s*```$/', '', $cleaned);
            }

            $ideas = json_decode($cleaned, true) ?? [];

            return response()->json([
                'success' => true,
                'ideas' => $ideas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate ide: ' . $e->getMessage(),
            ], 422);
        }
    }
}
