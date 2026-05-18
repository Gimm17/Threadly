<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiModelConfig;
use App\Services\AIService;

class AIWorkflowService
{
    public function __construct(
        private readonly AIService $ai,
        private readonly AICacheService $cache,
        private readonly JsonResponseParser $parser,
        private readonly PromptTemplateService $prompts,
    ) {}

    public function contentAssist(array $input, ?int $workspaceId = null): array
    {
        $workflow = $this->prompts->workflow('content_assist');
        $feature = $workflow['feature'] ?? 'hook_generator';
        $modelId = $this->modelId($feature, $workspaceId);

        return $this->cache->remember('content_assist', $workspaceId, $modelId, $input, function () use ($input, $workspaceId, $workflow, $feature) {
            $topic = trim((string) ($input['topic'] ?? $input['text'] ?? ''));
            $pillar = trim((string) ($input['pillar'] ?? ''));
            $system = $this->prompts->systemPrompt('copywriter dan content strategist Threads', $workspaceId);
            $prompt = <<<PROMPT
            Buat bantuan konten untuk post Threads berdasarkan brief berikut.

            Brief/topik:
            {$topic}

            Content pillar:
            {$pillar}

            Output JSON object valid dengan schema:
            {
              "hooks": [{"hook": "maks 150 karakter", "angle": "angle singkat", "score": 1-100, "reason": "maks 12 kata"}],
              "cta": "CTA natural maks 100 karakter",
              "hashtags": ["#tag1", "#tag2", "#tag3"],
              "quality_notes": ["catatan singkat"]
            }

            Aturan:
            - Buat tepat 5 hook.
            - Hook harus spesifik, tidak clickbait, dan cocok untuk audiens Indonesia.
            - Pakai detail konkret dari brief bila ada, misalnya invoice, stok, chat pelanggan, laporan harian.
            - Hindari awalan generik seperti "Bayangkan", "Rahasia", "POV", dan "Kamu wajib tahu".
            - Hindari skenario waktu/angka yang janggal atau tidak realistis.
            - CTA harus soft, tidak memaksa, dan relevan untuk bisnis.
            - Hashtag maksimal 5, pendek, relevan, tanpa spasi, tanpa #viral/#fyp/#trending.
            - Quality notes berisi risiko singkat seperti terlalu umum, kurang contoh, atau CTA belum jelas.
            - Jangan tulis markdown atau penjelasan di luar JSON.
            PROMPT;

            $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 900));

            return $this->normalizeContentAssist(is_array($result) ? $result : []);
        }, (bool) ($workflow['cache'] ?? true));
    }

    public function generateHook(string $topic, ?string $pillar = null, ?int $workspaceId = null): array
    {
        return $this->contentAssist([
            'topic' => $topic,
            'pillar' => $pillar,
        ], $workspaceId)['hooks'] ?? [];
    }

    public function generateHashtags(string $text, ?int $workspaceId = null): array
    {
        $workflow = $this->prompts->workflow('hashtag_generator');
        $feature = $workflow['feature'] ?? 'copywriting';
        $modelId = $this->modelId($feature, $workspaceId);
        $input = ['text' => $text];

        return $this->cache->remember('hashtag_generator', $workspaceId, $modelId, $input, function () use ($text, $workspaceId, $workflow, $feature) {
            $system = $this->prompts->systemPrompt('social media hashtag strategist', $workspaceId);
            $prompt = <<<PROMPT
            Buat hashtag untuk post Threads berikut:
            {$text}

            Output JSON object valid:
            {"hashtags":["#tag1","#tag2","#tag3"]}

            Aturan:
            - 3 sampai 5 hashtag.
            - Relevan, pendek, tidak generik berlebihan.
            - Sertakan kombinasi domain, audiens, dan manfaat bila cocok.
            - Hindari #viral, #fyp, #trending, dan hashtag yang terlalu luas seperti #bisnis saja.
            - Jangan ubah teks post.
            PROMPT;

            $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 350));
            $hashtags = is_array($result) && is_array($result['hashtags'] ?? null) ? $result['hashtags'] : [];

            return array_values(array_slice(array_filter(array_map([$this, 'normalizeHashtag'], $hashtags)), 0, 5));
        }, (bool) ($workflow['cache'] ?? true));
    }

    public function improveText(string $text, string $instruction, ?int $workspaceId = null): string
    {
        $workflow = $this->prompts->workflow('improve_text');
        $feature = $workflow['feature'] ?? 'copywriting';
        $system = $this->prompts->systemPrompt('editor konten Threads', $workspaceId);
        $prompt = <<<PROMPT
        Perbaiki teks Threads berikut sesuai instruksi.

        Teks:
        {$text}

        Instruksi:
        {$instruction}

        Output JSON object valid:
        {"text":"teks final maksimal 500 karakter","changes":["ringkasan perubahan"]}

        Aturan:
        - Pertahankan makna utama.
        - Bahasa Indonesia natural.
        - Buat kalimat lebih konkret dan mudah dipahami pemilik UMKM.
        - Jangan tambah klaim besar yang tidak ada di teks sumber.
        - Maksimal 500 karakter.
        - Jangan tulis markdown.
        PROMPT;

        $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 650));

            return mb_substr($this->cleanGeneratedText((string) (is_array($result) ? ($result['text'] ?? $text) : $text)), 0, 500);
    }

    public function generateIdeas(string $topic, int $count, ?int $workspaceId = null): array
    {
        $workflow = $this->prompts->workflow('ideas');
        $feature = $workflow['feature'] ?? 'copywriting';
        $modelId = $this->modelId($feature, $workspaceId);
        $input = ['topic' => $topic, 'count' => $count];

        return $this->cache->remember('content_ideas', $workspaceId, $modelId, $input, function () use ($topic, $count, $workspaceId, $workflow, $feature) {
            $system = $this->prompts->systemPrompt('content strategist Threads', $workspaceId);
            $prompt = <<<PROMPT
            Buat {$count} ide konten Threads tentang:
            {$topic}

            Output JSON array valid. Setiap item:
            {"title":"judul ide","description":"1 kalimat","pillar":"kategori","difficulty":"easy|medium|hard","cta_angle":"angle CTA"}

            Aturan:
            - Ide harus bisa dieksekusi sebagai post Threads singkat.
            - Prioritaskan contoh operasional nyata, bukan topik terlalu abstrak.
            - CTA angle harus soft dan praktis.
            PROMPT;

            $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 900));

            $ideas = is_array($result['ideas'] ?? null)
                ? $result['ideas']
                : (is_array($result['content_ideas'] ?? null)
                    ? $result['content_ideas']
                    : (is_array($result['data'] ?? null) ? $result['data'] : $result));

            return $this->normalizeIdeas(is_array($ideas) ? $ideas : [], $count);
        }, (bool) ($workflow['cache'] ?? true));
    }

    public function generatePost(array $input, ?int $workspaceId = null): string
    {
        $workflow = $this->prompts->workflow('copywriting_post');
        $feature = $workflow['feature'] ?? 'copywriting';
        $system = $this->prompts->systemPrompt('copywriter profesional Threads', $workspaceId);
        $maxLength = (int) ($input['max_length'] ?? 500);
        $hashtags = ($input['include_hashtags'] ?? false) ? 'sertakan 3 hashtag relevan' : 'tanpa hashtag';
        $cta = ($input['include_cta'] ?? true) ? 'sertakan CTA natural' : 'tanpa CTA eksplisit';
        $topic = trim((string) ($input['topic'] ?? ''));
        $tone = (string) ($input['tone'] ?? 'professional');
        $pillar = trim((string) ($input['pillar'] ?? ''));

        $prompt = <<<PROMPT
        Buat satu post Threads siap publish.

        Topik/brief: {$topic}
        Content pillar: {$pillar}
        Tone: {$tone}
        Panjang maksimal: {$maxLength} karakter
        Hashtag policy: {$hashtags}
        CTA policy: {$cta}

        Output JSON object valid:
        {"content":"post final","hook":"hook pembuka","char_count":123}

        Aturan:
        - Mulai dengan hook yang kuat.
        - Beri nilai praktis, bukan slogan kosong.
        - Gunakan contoh konkret yang relevan dengan brief.
        - Jangan pakai janji berlebihan seperti "otomatis sukses" atau "langsung naik omzet".
        - Jangan klaim hasil, data, trial, demo, atau adopsi pasar jika tidak ada di brief.
        - Hindari frasa generik seperti "Bayangkan" dan "jangan biarkan".
        - CTA harus terasa seperti ajakan berdiskusi atau menyimpan, bukan hard selling.
        - Jika memakai hashtag, letakkan di akhir dan maksimal 3.
        - Pastikan ejaan rapi dan tidak ada typo sebelum output.
        - Jangan melebihi batas karakter.
        PROMPT;

        $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 900));

        $content = is_array($result)
            ? ($result['content'] ?? $result['post'] ?? $result['text'] ?? $result['caption'] ?? $result['copy'] ?? '')
            : (string) $result;

        $content = $this->cleanGeneratedText((string) $content);

        if ($content === '') {
            $fallbackPrompt = <<<PROMPT
            Tulis satu post Threads final dari brief berikut. Output teks final saja, tanpa JSON.

            Brief: {$topic}
            Tone: {$tone}
            Panjang maksimal: {$maxLength} karakter
            Hashtag policy: {$hashtags}
            CTA policy: {$cta}

            Aturan:
            - Pakai contoh konkret dari brief.
            - Hindari clickbait, klaim tanpa data, "Bayangkan", dan hard selling.
            - Pastikan ejaan rapi.
            PROMPT;

            $content = $this->ai->complete($feature, $fallbackPrompt, $system, $workspaceId, min((int) ($workflow['max_tokens'] ?? 900), 700));
        }

        $content = $this->cleanGeneratedText((string) $content);

        if ($content === '') {
            throw new \RuntimeException('AI tidak mengembalikan konten post yang bisa dipakai.');
        }

        return mb_substr($content, 0, $maxLength);
    }

    public function generateReadyPostCopy(array $input, ?int $workspaceId = null): array
    {
        $workflow = $this->prompts->workflow('ready_post');
        $feature = $workflow['feature'] ?? 'copywriting';
        $modelId = $this->modelId($feature, $workspaceId);
        $cacheInput = [
            'topic' => trim((string) ($input['topic'] ?? '')),
            'pillar' => trim((string) ($input['pillar'] ?? '')),
            'tone' => (string) ($input['tone'] ?? 'professional-practical-warm'),
        ];

        return $this->cache->remember('ready_post_copy', $workspaceId, $modelId, $cacheInput, function () use ($cacheInput, $workspaceId, $workflow, $feature) {
            $topic = $cacheInput['topic'];
            $pillar = $cacheInput['pillar'];
            $tone = $cacheInput['tone'];
            $system = $this->prompts->systemPrompt('senior Threads copywriter dan art director', $workspaceId);
            $prompt = <<<PROMPT
            Buat satu paket konten Threads yang siap dipakai dari brief berikut.

            Brief/topik:
            {$topic}

            Content pillar:
            {$pillar}

            Tone:
            {$tone}

            Output JSON object valid dengan schema:
            {
              "hook": "hook utama maksimal 150 karakter",
              "hook_variants": [{"hook": "variasi hook maksimal 150 karakter", "angle": "angle singkat", "score": 1-100}],
              "body": "isi post final ideal 350-450 karakter dan maksimal 500 karakter, sudah termasuk CTA dan hashtag jika relevan",
              "hashtags": ["#tag1", "#tag2", "#tag3"],
              "headline": "headline poster 3-6 kata",
              "poster_brief": "English visual prompt brief for image generation, no text in image",
              "quality_notes": ["catatan singkat"]
            }

            Aturan copy:
            - Output hook, body, headline, dan quality_notes dalam Bahasa Indonesia.
            - Body harus langsung siap publish di Threads, maksimal 500 karakter.
            - Body idealnya 350-450 karakter supaya tidak perlu dipotong sistem.
            - Body tidak perlu mengulang hook secara persis, tapi harus nyambung dengan hook.
            - Beri nilai praktis dan contoh operasional, bukan slogan.
            - CTA harus natural dan soft, misalnya ajakan simpan, cek proses, atau diskusi.
            - Hashtag maksimal 3 di akhir body, relevan, tanpa #viral/#fyp/#trending.
            - Jangan mengarang data, hasil klien, angka, testimoni, atau klaim performa.
            - Dilarang menulis angka, persentase, rentang biaya, atau estimasi penghematan kecuali angka itu eksplisit ada di brief.
            - Jika brief hanya menyebut "mahal", tulis secara kualitatif seperti "biaya admin terasa berat", tanpa angka.
            - Jangan klaim "tanpa biaya", "tidak ada potongan", atau "gratis transaksi". Gunakan frasa aman seperti "biaya lebih terkendali" atau "lebih leluasa mengatur margin".
            - Jangan pakai emoji, simbol checklist, bullet dekoratif, atau karakter hias. Gunakan kalimat pendek yang rapi.
            - Hindari "Bayangkan", "Rahasia", "POV", "Kamu wajib tahu", dan hard selling.

            Aturan poster_brief:
            - Tulis dalam Bahasa Inggris.
            - Fokus pada visual/background berkualitas, bukan teks di gambar.
            - Sertakan subject, scene, composition, brand cue, palette, lighting, negative space, dan no visible text.
            - Cocok untuk poster square atau portrait di Threads/Instagram.
            - Jangan tulis markdown atau penjelasan di luar JSON.
            PROMPT;

            $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 950));

            return $this->normalizeReadyPostCopy(is_array($result) ? $result : []);
        }, (bool) ($workflow['cache'] ?? true));
    }

    public function generateVariations(string $text, int $count, ?int $workspaceId = null): array
    {
        $workflow = $this->prompts->workflow('copywriting_variations');
        $feature = $workflow['feature'] ?? 'copywriting';
        $modelId = $this->modelId($feature, $workspaceId);
        $input = ['text' => $text, 'count' => $count];

        return $this->cache->remember('copy_variations', $workspaceId, $modelId, $input, function () use ($text, $count, $workspaceId, $workflow, $feature) {
            $system = $this->prompts->systemPrompt('copywriter kreatif Threads', $workspaceId);
            $prompt = <<<PROMPT
            Buat {$count} variasi dari post Threads berikut:
            {$text}

            Output JSON array valid:
            [{"tone":"nama tone","text":"maks 500 karakter","angle":"angle singkat"}]

            Aturan:
            - Setiap variasi harus punya angle yang berbeda.
            - Pertahankan fakta utama, jangan tambah klaim baru.
            - Bahasa Indonesia natural untuk audiens UMKM.
            PROMPT;

            $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 1200));

            $variations = is_array($result['variations'] ?? null) ? $result['variations'] : $result;

            return $this->normalizeVariations(is_array($variations) ? $variations : [], $count);
        }, (bool) ($workflow['cache'] ?? true));
    }

    public function generateThread(array $input, ?int $workspaceId = null): array
    {
        $workflow = $this->prompts->workflow('copywriting_thread');
        $feature = $workflow['feature'] ?? 'copywriting';
        $system = $this->prompts->systemPrompt('content strategist Threads', $workspaceId);
        $parts = (int) ($input['num_posts'] ?? 5);
        $topic = trim((string) ($input['topic'] ?? ''));
        $tone = (string) ($input['tone'] ?? 'professional');

        $prompt = <<<PROMPT
        Buat thread Threads {$parts} bagian.

        Topik: {$topic}
        Tone: {$tone}

        Output JSON array valid:
        [{"part":1,"text":"maks 500 karakter","char_count":123}]

        Aturan:
        - Part 1 adalah hook.
        - Tiap part hanya satu ide utama.
        - CTA hanya di part terakhir.
        - Setiap text maksimal 500 karakter.
        - Pakai progresi: masalah operasional, cara praktis, lalu ajakan/next step.
        - Hindari mengulang kalimat yang sama antar part.
        - Jangan klaim trial, demo, hasil bisnis, atau layanan Gimora jika tidak diminta di topik.
        - Hindari frasa "Bayangkan", "jangan biarkan", dan hard selling.
        - CTA terakhir cukup soft: ajak simpan, pilih satu proses admin, atau diskusi ringan.
        PROMPT;

        $result = $this->completeJson($feature, $prompt, $system, $workspaceId, (int) ($workflow['max_tokens'] ?? 1800));

        $thread = is_array($result['posts'] ?? null)
            ? $result['posts']
            : (is_array($result['thread'] ?? null)
                ? $result['thread']
                : (is_array($result['data'] ?? null) ? $result['data'] : $result));

        return $this->normalizeThread(is_array($thread) ? $thread : [], $parts);
    }

    public function completeJson(string $feature, string $prompt, string $system, ?int $workspaceId, int $maxTokens): mixed
    {
        $response = $this->ai->completeJson($feature, $prompt, $system, $workspaceId, $maxTokens);
        $parsed = $this->parser->parse($response, null);

        if ($parsed !== null) {
            return $parsed;
        }

        $repairPrompt = <<<PROMPT
        Ubah respons berikut menjadi JSON valid saja. Jangan tambah penjelasan.

        Respons:
        {$response}
        PROMPT;

        $repaired = $this->ai->complete($feature, $repairPrompt, 'Output hanya JSON valid.', $workspaceId, min($maxTokens, 600));

        return $this->parser->parse($repaired, []);
    }

    private function normalizeContentAssist(array $result): array
    {
        $hooks = is_array($result['hooks'] ?? null) ? $result['hooks'] : [];
        $hooks = array_map(function ($hook) {
            $text = is_array($hook) ? (string) ($hook['hook'] ?? $hook['hook_text'] ?? '') : (string) $hook;

            return [
                'hook' => mb_substr(trim($text), 0, 150),
                'angle' => is_array($hook) ? (string) ($hook['angle'] ?? '') : '',
                'score' => max(1, min(100, (int) (is_array($hook) ? ($hook['score'] ?? 70) : 70))),
                'reason' => is_array($hook) ? (string) ($hook['reason'] ?? '') : '',
            ];
        }, array_slice($hooks, 0, 5));

        return [
            'hooks' => array_values(array_filter($hooks, fn ($hook) => filled($hook['hook']))),
            'cta' => mb_substr(trim((string) ($result['cta'] ?? '')), 0, 100),
            'hashtags' => array_values(array_slice(array_filter(array_map([$this, 'normalizeHashtag'], $result['hashtags'] ?? [])), 0, 5)),
            'quality_notes' => array_values(array_slice((array) ($result['quality_notes'] ?? []), 0, 4)),
        ];
    }

    private function normalizeReadyPostCopy(array $result): array
    {
        $hook = mb_substr(trim((string) ($result['hook'] ?? $result['opening'] ?? '')), 0, 150);
        $body = $this->stripDecorativeSymbols($this->cleanGeneratedText((string) ($result['body'] ?? $result['content'] ?? $result['post'] ?? '')));
        $hashtags = array_values(array_slice(array_filter(array_map([$this, 'normalizeHashtag'], $result['hashtags'] ?? [])), 0, 3));

        $body = $this->fitBodyWithHashtags($body, $hashtags, 500);

        $hookVariants = is_array($result['hook_variants'] ?? null)
            ? $result['hook_variants']
            : (is_array($result['hooks'] ?? null) ? $result['hooks'] : []);

        $hookVariants = array_values(array_filter(array_map(function ($item) {
            $variant = is_array($item) ? $item : ['hook' => (string) $item];
            $text = mb_substr(trim((string) ($variant['hook'] ?? $variant['text'] ?? '')), 0, 150);

            if ($text === '') {
                return null;
            }

            return [
                'hook' => $text,
                'angle' => mb_substr(trim((string) ($variant['angle'] ?? '')), 0, 60),
                'score' => max(1, min(100, (int) ($variant['score'] ?? 75))),
            ];
        }, array_slice($hookVariants, 0, 5))));

        if ($hook === '' && $hookVariants !== []) {
            $hook = $hookVariants[0]['hook'];
        }

        if ($body === '') {
            throw new \RuntimeException('AI tidak mengembalikan isi post yang bisa dipakai.');
        }

        return [
            'hook' => $hook,
            'hook_variants' => $hookVariants,
            'body' => $body,
            'hashtags' => $hashtags,
            'headline' => mb_substr(trim((string) ($result['headline'] ?? $hook)), 0, 80),
            'poster_brief' => mb_substr(trim((string) ($result['poster_brief'] ?? $result['image_prompt'] ?? $body)), 0, 1000),
            'quality_notes' => array_values(array_slice((array) ($result['quality_notes'] ?? []), 0, 4)),
        ];
    }

    private function fitBodyWithHashtags(string $body, array $hashtags, int $maxLength): string
    {
        $bodyWithoutTags = trim(preg_replace('/\n?\s*(#[\p{L}\p{N}_]+\s*)+$/u', '', $body) ?? $body);

        if ($hashtags === []) {
            return $this->trimAtBoundary($bodyWithoutTags, $maxLength);
        }

        $tagLine = implode(' ', $hashtags);
        $tagSuffix = "\n\n{$tagLine}";
        $availableForBody = $maxLength - mb_strlen($tagSuffix);

        if ($availableForBody < 120) {
            return $this->trimAtBoundary($bodyWithoutTags, $maxLength);
        }

        return trim($this->trimAtBoundary($bodyWithoutTags, $availableForBody) . $tagSuffix);
    }

    private function trimAtBoundary(string $text, int $maxLength): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $limit = max(1, $maxLength - 3);
        $trimmed = mb_substr($text, 0, $limit);
        $sentenceCut = max(
            mb_strrpos($trimmed, '. ') ?: 0,
            mb_strrpos($trimmed, '? ') ?: 0,
            mb_strrpos($trimmed, '! ') ?: 0,
        );

        if ($sentenceCut > (int) floor($limit * 0.60)) {
            return rtrim(mb_substr($trimmed, 0, $sentenceCut + 1));
        }

        $lastSpace = mb_strrpos($trimmed, ' ');

        if ($lastSpace !== false && $lastSpace > (int) floor($limit * 0.75)) {
            $trimmed = mb_substr($trimmed, 0, $lastSpace);
        }

        return rtrim($trimmed, " \t\n\r\0\x0B.,;:-#/") . '...';
    }

    private function stripDecorativeSymbols(string $text): string
    {
        $cleaned = preg_replace('/[\x{2705}\x{2713}\x{2714}\x{25AA}\x{25CF}\x{2022}\x{1F300}-\x{1FAFF}]/u', '', $text) ?? $text;

        return trim(preg_replace('/^[ \t]*[-*]+[ \t]*/m', '', $cleaned) ?? $cleaned);
    }

    private function normalizeIdeas(array $ideas, int $count): array
    {
        return array_values(array_filter(array_map(function ($idea) {
            $item = is_array($idea) ? $idea : ['title' => (string) $idea];
            $title = (string) ($item['title'] ?? $item['judul'] ?? $item['idea'] ?? $item['ide'] ?? '');
            $description = (string) ($item['description'] ?? $item['deskripsi'] ?? $item['summary'] ?? $item['ringkasan'] ?? '');
            $pillar = (string) ($item['pillar'] ?? $item['pilar'] ?? $item['category'] ?? $item['kategori'] ?? '');
            $difficulty = $this->normalizeDifficulty((string) ($item['difficulty'] ?? $item['kesulitan'] ?? 'easy'));
            $cta = (string) ($item['cta_angle'] ?? $item['angle_cta'] ?? $item['cta'] ?? $item['ajakan'] ?? '');

            if (trim($title . $description) === '') {
                return null;
            }

            return [
                'title' => mb_substr(trim($title), 0, 120),
                'description' => mb_substr(trim($description), 0, 180),
                'pillar' => mb_substr(trim($pillar), 0, 80),
                'difficulty' => $difficulty,
                'cta_angle' => mb_substr(trim($cta), 0, 120),
            ];
        }, array_slice(array_values($ideas), 0, $count))));
    }

    private function normalizeVariations(array $variations, int $count): array
    {
        return array_values(array_filter(array_map(function ($variation) {
            $item = is_array($variation) ? $variation : ['text' => (string) $variation];
            $text = mb_substr(trim((string) ($item['text'] ?? '')), 0, 500);

            if ($text === '') {
                return null;
            }

            return [
                'tone' => mb_substr(trim((string) ($item['tone'] ?? '')), 0, 40),
                'text' => $text,
                'angle' => mb_substr(trim((string) ($item['angle'] ?? '')), 0, 120),
            ];
        }, array_slice($variations, 0, $count))));
    }

    private function normalizeThread(array $thread, int $parts): array
    {
        $normalized = [];

        foreach (array_values(array_slice($thread, 0, $parts)) as $index => $item) {
            $post = is_array($item) ? $item : ['text' => (string) $item];
            $text = mb_substr(trim((string) ($post['text'] ?? $post['content'] ?? $post['post'] ?? $post['body'] ?? $post['copy'] ?? '')), 0, 500);

            if ($text === '') {
                continue;
            }

            $normalized[] = [
                'part' => (int) ($post['part'] ?? ($index + 1)),
                'text' => $text,
                'char_count' => mb_strlen($text),
            ];
        }

        return $normalized;
    }

    private function normalizeDifficulty(string $difficulty): string
    {
        return match (mb_strtolower(trim($difficulty))) {
            'medium', 'sedang', 'menengah' => 'medium',
            'hard', 'sulit', 'advanced', 'lanjutan' => 'hard',
            default => 'easy',
        };
    }

    private function normalizeHashtag(mixed $hashtag): ?string
    {
        $value = preg_replace('/\s+/', '', trim((string) $hashtag)) ?? '';
        if ($value === '') {
            return null;
        }

        return str_starts_with($value, '#') ? $value : '#' . $value;
    }

    private function cleanGeneratedText(string $text): string
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^\s*(respons|response|output|hasil)\s*:\s*/iu', '', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function modelId(string $feature, ?int $workspaceId): string
    {
        $wsId = $workspaceId ?? auth()->user()?->workspace_id;

        return (string) AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->where('feature', $feature)
            ->where('is_active', true)
            ->value('model_id');
    }
}
