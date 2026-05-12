<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CopywritingController extends Controller
{
    public function __construct(
        private readonly AIService $aiService,
    ) {}

    /**
     * Display the Copywriting AI page.
     */
    public function index(): Response
    {
        return Inertia::render('Copywriting/Index');
    }

    /**
     * Generate full post copy from a topic/brief.
     */
    public function generatePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'in:casual,professional,humorous,inspirational,educational'],
            'max_length' => ['nullable', 'integer', 'min:100', 'max:500'],
            'include_hashtags' => ['nullable', 'boolean'],
            'include_cta' => ['nullable', 'boolean'],
        ]);

        try {
            $tone = $validated['tone'] ?? 'casual';
            $maxLength = $validated['max_length'] ?? 500;
            $lengthGuide = "maksimal {$maxLength} karakter";

            $extras = [];
            if ($validated['include_hashtags'] ?? false) {
                $extras[] = 'Sertakan 3-5 hashtag relevan di akhir';
            }
            if ($validated['include_cta'] ?? true) {
                $extras[] = 'Sertakan Call-to-Action yang natural';
            }

            $extrasText = !empty($extras) ? "\n- " . implode("\n- ", $extras) : '';

            $prompt = "Buatkan post Threads tentang: {$validated['topic']}\n\n"
                . "Tone: {$tone}\n"
                . "Panjang: {$lengthGuide}\n"
                . "Kriteria:\n"
                . "- Mulai dengan hook yang menarik\n"
                . "- Isi konten yang bernilai\n"
                . "- Bahasa Indonesia natural{$extrasText}\n\n"
                . "HANYA output teks post-nya saja, tanpa penjelasan.";

            $result = $this->aiService->complete(
                feature: 'copywriting',
                userPrompt: $prompt,
                systemPrompt: "Kamu adalah copywriter profesional untuk Threads. Tulis dengan tone {$tone} dalam bahasa Indonesia.",
            );

            return response()->json([
                'success' => true,
                'content' => trim($result, " \t\n\r\0\x0B\"'"),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate post: ' . $e->getMessage(),
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

            $prompt = "Buatkan {$count} variasi dari teks post Threads ini:\n\n\"{$validated['original_text']}\"\n\n"
                . "Kriteria setiap variasi:\n"
                . "- Tone/style berbeda (misal: santai, profesional, humoris)\n"
                . "- Esensi pesan tetap sama\n"
                . "- Bahasa Indonesia natural\n"
                . "- Maksimal 500 karakter per variasi\n\n"
                . "Format output JSON array:\n"
                . "[{\"tone\": \"nama tone\", \"text\": \"teks variasi\"}]\n\n"
                . "HANYA output JSON array, tanpa teks lain.";

            $result = $this->aiService->complete(
                feature: 'copywriting',
                userPrompt: $prompt,
                systemPrompt: 'Kamu adalah copywriter kreatif. Buat variasi copy yang beragam. Selalu output dalam format JSON array yang valid.',
            );

            // Parse JSON response
            $cleaned = trim($result);
            if (str_starts_with($cleaned, '```')) {
                $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned);
                $cleaned = preg_replace('/\s*```$/', '', $cleaned);
            }

            $variations = json_decode($cleaned, true) ?? [];

            return response()->json([
                'success' => true,
                'variations' => $variations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate variasi: ' . $e->getMessage(),
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
        ]);

        try {
            $parts = $validated['num_posts'] ?? 5;
            $tone = $validated['tone'] ?? 'professional';

            $prompt = "Buatkan thread Threads ({$parts} bagian) tentang: {$validated['topic']}\n\n"
                . "Kriteria:\n"
                . "- Post pertama: hook yang memancing rasa penasaran\n"
                . "- Post tengah: isi konten bernilai, 1 poin per post\n"
                . "- Post terakhir: rangkuman + CTA\n"
                . "- Setiap post maksimal 500 karakter\n"
                . "- Bahasa Indonesia natural dan engaging\n\n"
                . "Format output JSON array:\n"
                . "[{\"part\": 1, \"text\": \"teks post\"}]\n\n"
                . "HANYA output JSON array, tanpa teks lain.";

            $result = $this->aiService->complete(
                feature: 'copywriting',
                userPrompt: $prompt,
                systemPrompt: 'Kamu adalah content strategist untuk Threads. Buat thread yang informatif dan engaging. Selalu output dalam format JSON array yang valid.',
            );

            $cleaned = trim($result);
            if (str_starts_with($cleaned, '```')) {
                $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned);
                $cleaned = preg_replace('/\s*```$/', '', $cleaned);
            }

            $thread = json_decode($cleaned, true) ?? [];

            // Vue expects 'posts' as array of strings, extract text from each part
            $posts = array_map(function ($item) {
                return is_array($item) ? ($item['text'] ?? '') : (string) $item;
            }, $thread);

            return response()->json([
                'success' => true,
                'posts' => $posts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate thread: ' . $e->getMessage(),
            ], 422);
        }
    }
}
