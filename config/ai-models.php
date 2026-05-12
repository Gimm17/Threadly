<?php

/**
 * Katalog model AI yang tersedia dari TokenRouter.
 * Digunakan sebagai dropdown di halaman Settings → AI Model Config.
 * Admin tetap bisa mengetik model ID custom yang tidak ada di daftar.
 *
 * capabilities: ['text'] = text only, ['text', 'image'] = juga bisa generate image
 *
 * Source: https://www.tokenrouter.com (May 2026)
 * Base URL: https://api.tokenrouter.com/v1
 */
return [

    // ══════════════════════════════════════════
    // OpenAI
    // ══════════════════════════════════════════
    ['id' => 'openai/gpt-5.5', 'name' => 'GPT-5.5', 'vendor' => 'OpenAI', 'desc' => 'Flagship OpenAI, 1M context', 'capabilities' => ['text']],
    ['id' => 'openai/gpt-5.4-pro', 'name' => 'GPT-5.4 Pro', 'vendor' => 'OpenAI', 'desc' => 'Top tier, 1M context', 'capabilities' => ['text']],
    ['id' => 'openai/gpt-5.4', 'name' => 'GPT-5.4', 'vendor' => 'OpenAI', 'desc' => 'Premium, 1M context', 'capabilities' => ['text']],
    ['id' => 'openai/gpt-5.2', 'name' => 'GPT-5.2', 'vendor' => 'OpenAI', 'desc' => 'Balanced, 400K context', 'capabilities' => ['text']],
    ['id' => 'openai/gpt-5-mini', 'name' => 'GPT-5 Mini', 'vendor' => 'OpenAI', 'desc' => 'Efficient, cepat', 'capabilities' => ['text']],
    ['id' => 'openai/gpt-5.4-image-2', 'name' => 'GPT-5.4 Image 2', 'vendor' => 'OpenAI', 'desc' => 'Latest image generation', 'capabilities' => ['text', 'image']],
    ['id' => 'openai/gpt-5-image', 'name' => 'GPT-5 Image', 'vendor' => 'OpenAI', 'desc' => 'Multimodal image generation', 'capabilities' => ['text', 'image']],
    ['id' => 'openai/gpt-5-image-mini', 'name' => 'GPT-5 Image Mini', 'vendor' => 'OpenAI', 'desc' => 'Compact multimodal', 'capabilities' => ['text', 'image']],
    ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini', 'vendor' => 'OpenAI', 'desc' => 'Fast, efisien', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // Anthropic (Claude)
    // ══════════════════════════════════════════
    ['id' => 'anthropic/claude-opus-4.7', 'name' => 'Claude Opus 4.7', 'vendor' => 'Anthropic', 'desc' => 'Terkuat Anthropic, 1M context', 'capabilities' => ['text']],
    ['id' => 'anthropic/claude-sonnet-4.5', 'name' => 'Claude Sonnet 4.5', 'vendor' => 'Anthropic', 'desc' => 'Balanced, 1M context', 'capabilities' => ['text']],
    ['id' => 'claude-haiku-4-5', 'name' => 'Claude Haiku 4.5', 'vendor' => 'Anthropic', 'desc' => 'Cepat, murah', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // Google (Gemini)
    // ══════════════════════════════════════════
    ['id' => 'google/gemini-3.1-pro-preview', 'name' => 'Gemini 3.1 Pro Preview', 'vendor' => 'Google', 'desc' => 'Latest flagship, 1M context', 'capabilities' => ['text']],
    ['id' => 'google/gemini-3-pro-image-preview', 'name' => 'Gemini 3 Pro Image', 'vendor' => 'Google', 'desc' => 'Pro multimodal + image gen', 'capabilities' => ['text', 'image']],
    ['id' => 'google/gemini-3-flash-preview', 'name' => 'Gemini 3 Flash Preview', 'vendor' => 'Google', 'desc' => 'Fast preview', 'capabilities' => ['text']],
    ['id' => 'google/gemini-3.1-flash-image-preview', 'name' => 'Gemini 3.1 Flash Image', 'vendor' => 'Google', 'desc' => 'Flash multimodal', 'capabilities' => ['text', 'image']],
    ['id' => 'google/gemini-2.5-flash-image', 'name' => 'Gemini 2.5 Flash Image', 'vendor' => 'Google', 'desc' => 'Flash image generation', 'capabilities' => ['text', 'image']],

    // ══════════════════════════════════════════
    // DeepSeek
    // ══════════════════════════════════════════
    ['id' => 'deepseek/deepseek-v4-pro', 'name' => 'DeepSeek V4 Pro', 'vendor' => 'DeepSeek', 'desc' => 'Terkuat DeepSeek, reasoning', 'capabilities' => ['text']],
    ['id' => 'deepseek/deepseek-v4-flash', 'name' => 'DeepSeek V4 Flash', 'vendor' => 'DeepSeek', 'desc' => 'Cepat, efisien', 'capabilities' => ['text']],
    ['id' => 'deepseek/deepseek-v3.2', 'name' => 'DeepSeek V3.2', 'vendor' => 'DeepSeek', 'desc' => 'General purpose', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // Qwen
    // ══════════════════════════════════════════
    ['id' => 'qwen/qwen3.6-plus', 'name' => 'Qwen 3.6 Plus', 'vendor' => 'Qwen', 'desc' => 'Latest flagship', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3.5-397b-a17b', 'name' => 'Qwen 3.5 397B', 'vendor' => 'Qwen', 'desc' => 'Terbesar Qwen, MoE', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3.5-122b-a10b', 'name' => 'Qwen 3.5 122B', 'vendor' => 'Qwen', 'desc' => 'Large MoE', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3.5-plus-02-15', 'name' => 'Qwen 3.5 Plus', 'vendor' => 'Qwen', 'desc' => 'Balanced plus', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3.5-flash', 'name' => 'Qwen 3.5 Flash', 'vendor' => 'Qwen', 'desc' => 'Fast inference', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3.5-35b-a3b', 'name' => 'Qwen 3.5 35B', 'vendor' => 'Qwen', 'desc' => 'Compact MoE', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3.5-9b', 'name' => 'Qwen 3.5 9B', 'vendor' => 'Qwen', 'desc' => 'Ultra compact', 'capabilities' => ['text']],
    ['id' => 'qwen/qwen3-coder-next', 'name' => 'Qwen 3 Coder Next', 'vendor' => 'Qwen', 'desc' => 'Code specialist', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // xAI (Grok)
    // ══════════════════════════════════════════
    ['id' => 'x-ai/grok-4.3', 'name' => 'Grok 4.3', 'vendor' => 'xAI', 'desc' => 'Latest, 1M context', 'capabilities' => ['text']],
    ['id' => 'x-ai/grok-4.20-beta', 'name' => 'Grok 4.20 Beta', 'vendor' => 'xAI', 'desc' => 'Extended, 2M context', 'capabilities' => ['text']],
    ['id' => 'x-ai/grok-4.1-fast', 'name' => 'Grok 4.1 Fast', 'vendor' => 'xAI', 'desc' => 'Low latency', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // MiniMax
    // ══════════════════════════════════════════
    ['id' => 'minimax/minimax-m2.7', 'name' => 'MiniMax M2.7', 'vendor' => 'MiniMax', 'desc' => 'Latest flagship', 'capabilities' => ['text']],
    ['id' => 'minimax/minimax-m2.7-highspeed', 'name' => 'MiniMax M2.7 HS', 'vendor' => 'MiniMax', 'desc' => 'Fast variant', 'capabilities' => ['text']],
    ['id' => 'minimax/minimax-m2.5', 'name' => 'MiniMax M2.5', 'vendor' => 'MiniMax', 'desc' => 'Balanced', 'capabilities' => ['text']],
    ['id' => 'minimax/minimax-m2.1', 'name' => 'MiniMax M2.1', 'vendor' => 'MiniMax', 'desc' => 'Stable release', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // Xiaomi (MiMo)
    // ══════════════════════════════════════════
    ['id' => 'xiaomi/mimo-v2.5-pro', 'name' => 'MiMo V2.5 Pro', 'vendor' => 'Xiaomi', 'desc' => 'Xiaomi flagship', 'capabilities' => ['text']],
    ['id' => 'xiaomi/mimo-v2.5', 'name' => 'MiMo V2.5', 'vendor' => 'Xiaomi', 'desc' => 'Balanced Xiaomi', 'capabilities' => ['text']],
    ['id' => 'xiaomi/mimo-v2-flash', 'name' => 'MiMo V2 Flash', 'vendor' => 'Xiaomi', 'desc' => 'Ultra fast', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // NVIDIA
    // ══════════════════════════════════════════
    ['id' => 'nvidia/nemotron-3-super-120b-a12b', 'name' => 'Nemotron 3 Super 120B', 'vendor' => 'NVIDIA', 'desc' => 'NVIDIA flagship, MoE', 'capabilities' => ['text']],
    ['id' => 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free', 'name' => 'Nemotron 3 Nano (Free)', 'vendor' => 'NVIDIA', 'desc' => 'Free reasoning model', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // Mistral
    // ══════════════════════════════════════════
    ['id' => 'mistralai/devstral-2512', 'name' => 'Devstral 2512', 'vendor' => 'Mistral', 'desc' => 'Code-focused Mistral', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // Z.AI (GLM)
    // ══════════════════════════════════════════
    ['id' => 'z-ai/glm-5.1', 'name' => 'GLM 5.1', 'vendor' => 'ZhiPu', 'desc' => 'Latest ZhiPu', 'capabilities' => ['text']],
    ['id' => 'z-ai/glm-5', 'name' => 'GLM 5', 'vendor' => 'ZhiPu', 'desc' => 'ZhiPu flagship', 'capabilities' => ['text']],
    ['id' => 'z-ai/glm-5-turbo', 'name' => 'GLM 5 Turbo', 'vendor' => 'ZhiPu', 'desc' => 'Fast ZhiPu', 'capabilities' => ['text']],
    ['id' => 'z-ai/glm-4.7', 'name' => 'GLM 4.7', 'vendor' => 'ZhiPu', 'desc' => 'Stable release', 'capabilities' => ['text']],
    ['id' => 'z-ai/glm-4.6', 'name' => 'GLM 4.6', 'vendor' => 'ZhiPu', 'desc' => 'Balanced', 'capabilities' => ['text']],

    // ══════════════════════════════════════════
    // ByteDance
    // ══════════════════════════════════════════
    ['id' => 'bytedance-seed/seedream-4.5', 'name' => 'Seedream 4.5', 'vendor' => 'ByteDance', 'desc' => 'ByteDance image generation', 'capabilities' => ['image']],

    // ══════════════════════════════════════════
    // Others
    // ══════════════════════════════════════════
    ['id' => 'moonshotai/kimi-k2.6', 'name' => 'Kimi K2.6', 'vendor' => 'Moonshot', 'desc' => 'Moonshot AI latest', 'capabilities' => ['text']],
    ['id' => 'moonshotai/kimi-k2.5', 'name' => 'Kimi K2.5', 'vendor' => 'Moonshot', 'desc' => 'Moonshot AI stable', 'capabilities' => ['text']],
    ['id' => 'stepfun/step-3.5-flash', 'name' => 'Step 3.5 Flash', 'vendor' => 'StepFun', 'desc' => 'StepFun fast model', 'capabilities' => ['text']],
];
