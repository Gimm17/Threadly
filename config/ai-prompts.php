<?php

return [
    'version' => '2026-05-18-v3',

    'brand_profile' => [
        'brand_name' => 'Gimora Digital',
        'threads_handle' => 'gimoradigital.id',
        'language' => 'Bahasa Indonesia',
        'audience' => 'UMKM, instansi pemerintah, dan pelaku bisnis di Indonesia Timur',
        'tone' => 'profesional, praktis, hangat, jelas, dan tidak berlebihan',
        'positioning' => 'partner teknologi yang membantu bisnis bekerja lebih rapi, cepat, dan terukur',
        'content_examples' => [
            'invoice',
            'stok',
            'chat pelanggan',
            'laporan harian',
            'jadwal follow-up',
        ],
        'avoid' => [
            'clickbait',
            'klaim berlebihan',
            'jargon teknis tanpa penjelasan',
            'emoji berlebihan',
            'hashtag terlalu banyak',
        ],
    ],

    'workflows' => [
        'content_assist' => [
            'feature' => 'hook_generator',
            'cache' => true,
            'max_tokens' => 900,
            'temperature' => 0.55,
        ],
        'hook_generator' => [
            'feature' => 'hook_generator',
            'cache' => true,
            'max_tokens' => 650,
            'temperature' => 0.60,
        ],
        'hashtag_generator' => [
            'feature' => 'hook_generator',
            'cache' => true,
            'max_tokens' => 350,
            'temperature' => 0.30,
        ],
        'improve_text' => [
            'feature' => 'copywriting',
            'cache' => false,
            'max_tokens' => 650,
            'temperature' => 0.35,
        ],
        'ideas' => [
            'feature' => 'hook_generator',
            'cache' => true,
            'max_tokens' => 900,
            'temperature' => 0.60,
        ],
        'copywriting_post' => [
            'feature' => 'copywriting',
            'cache' => false,
            'max_tokens' => 900,
            'temperature' => 0.50,
        ],
        'ready_post' => [
            'feature' => 'copywriting',
            'cache' => true,
            'max_tokens' => 950,
            'temperature' => 0.50,
        ],
        'copywriting_thread' => [
            'feature' => 'copywriting',
            'cache' => false,
            'max_tokens' => 1800,
            'temperature' => 0.50,
        ],
        'copywriting_variations' => [
            'feature' => 'copywriting',
            'cache' => true,
            'max_tokens' => 1200,
            'temperature' => 0.65,
        ],
        'insight' => [
            'feature' => 'insight',
            'cache' => true,
            'max_tokens' => 900,
            'temperature' => 0.40,
        ],
        'poster_prompt' => [
            'feature' => 'copywriting',
            'cache' => true,
            'max_tokens' => 700,
            'temperature' => 0.45,
        ],
    ],

    'poster' => [
        'negative_prompt' => 'No watermark, no logo on objects, no random logo, no visible text unless explicitly requested, no letters, no numbers, no labels, no garbled words, no app UI text, no chart labels, no document text, no cramped layout, no distorted faces or hands, no low-resolution artifacts, no screenshots.',
        'safe_area' => 'Leave a clean negative-space zone for deterministic text overlay in the upper or lower third; keep the main subject outside that zone.',
        'default_palette' => 'deep navy, clean white, fresh green, warm amber accent',
        'default_style' => 'premium social media poster, clean editorial composition, modern Indonesian tech brand',
    ],
];
