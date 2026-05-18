<?php

/**
 * TokenRouter model catalog used by Settings and AI services.
 *
 * Pricing values are intentionally conservative estimates. Keep them editable
 * here because TokenRouter/provider pricing can change independently of the app.
 */

$models = [
    ['id' => 'openai/gpt-5.5', 'name' => 'GPT-5.5', 'vendor' => 'OpenAI', 'desc' => 'Flagship OpenAI, high quality', 'capabilities' => ['text'], 'tier' => 'kualitas', 'input_price_per_million' => 5.00, 'output_price_per_million' => 15.00],
    ['id' => 'openai/gpt-5.4-pro', 'name' => 'GPT-5.4 Pro', 'vendor' => 'OpenAI', 'desc' => 'Premium long-form reasoning', 'capabilities' => ['text'], 'tier' => 'kualitas', 'input_price_per_million' => 4.00, 'output_price_per_million' => 12.00],
    ['id' => 'openai/gpt-5.4', 'name' => 'GPT-5.4', 'vendor' => 'OpenAI', 'desc' => 'Balanced premium text model', 'capabilities' => ['text'], 'tier' => 'default', 'input_price_per_million' => 2.50, 'output_price_per_million' => 10.00],
    ['id' => 'openai/gpt-5.2', 'name' => 'GPT-5.2', 'vendor' => 'OpenAI', 'desc' => 'Balanced general model', 'capabilities' => ['text'], 'tier' => 'default', 'input_price_per_million' => 2.00, 'output_price_per_million' => 8.00],
    ['id' => 'openai/gpt-5-mini', 'name' => 'GPT-5 Mini', 'vendor' => 'OpenAI', 'desc' => 'Fast and efficient text helper', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.25, 'output_price_per_million' => 1.00],
    ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini', 'vendor' => 'OpenAI', 'desc' => 'Efficient JSON-capable helper', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.15, 'output_price_per_million' => 0.60],
    ['id' => 'openai/gpt-5.4-image-2', 'name' => 'GPT-5.4 Image 2', 'vendor' => 'OpenAI', 'desc' => 'High quality image generation', 'capabilities' => ['text', 'image'], 'tier' => 'kualitas', 'endpoint_type' => 'images', 'text_in_image' => true, 'image_price_per_call' => 0.060],
    ['id' => 'openai/gpt-5-image', 'name' => 'GPT-5 Image', 'vendor' => 'OpenAI', 'desc' => 'Multimodal image generation', 'capabilities' => ['text', 'image'], 'tier' => 'default', 'endpoint_type' => 'images', 'text_in_image' => true, 'image_price_per_call' => 0.040],
    ['id' => 'openai/gpt-5-image-mini', 'name' => 'GPT-5 Image Mini', 'vendor' => 'OpenAI', 'desc' => 'Compact image generation', 'capabilities' => ['text', 'image'], 'tier' => 'hemat', 'endpoint_type' => 'images', 'text_in_image' => false, 'image_price_per_call' => 0.020],

    ['id' => 'anthropic/claude-opus-4.7', 'name' => 'Claude Opus 4.7', 'vendor' => 'Anthropic', 'desc' => 'Premium reasoning and writing', 'capabilities' => ['text'], 'tier' => 'kualitas', 'supports_json_mode' => false, 'input_price_per_million' => 5.00, 'output_price_per_million' => 15.00],
    ['id' => 'anthropic/claude-sonnet-4.5', 'name' => 'Claude Sonnet 4.5', 'vendor' => 'Anthropic', 'desc' => 'Balanced writing model', 'capabilities' => ['text'], 'tier' => 'default', 'supports_json_mode' => false, 'input_price_per_million' => 3.00, 'output_price_per_million' => 12.00],
    ['id' => 'claude-haiku-4-5', 'name' => 'Claude Haiku 4.5', 'vendor' => 'Anthropic', 'desc' => 'Fast text helper', 'capabilities' => ['text'], 'tier' => 'hemat', 'supports_json_mode' => false, 'input_price_per_million' => 0.25, 'output_price_per_million' => 1.25],

    ['id' => 'google/gemini-3.1-pro-preview', 'name' => 'Gemini 3.1 Pro Preview', 'vendor' => 'Google', 'desc' => 'Google flagship text model', 'capabilities' => ['text'], 'tier' => 'kualitas', 'supports_json_mode' => false, 'input_price_per_million' => 2.50, 'output_price_per_million' => 10.00],
    ['id' => 'google/gemini-3-flash-preview', 'name' => 'Gemini 3 Flash Preview', 'vendor' => 'Google', 'desc' => 'Fast Google text model', 'capabilities' => ['text'], 'tier' => 'hemat', 'supports_json_mode' => false, 'input_price_per_million' => 0.20, 'output_price_per_million' => 0.80],
    ['id' => 'google/gemini-3-pro-image-preview', 'name' => 'Gemini 3 Pro Image', 'vendor' => 'Google', 'desc' => 'Pro multimodal image generation', 'capabilities' => ['text', 'image'], 'tier' => 'kualitas', 'endpoint_type' => 'chat', 'text_in_image' => true, 'image_price_per_call' => 0.050],
    ['id' => 'google/gemini-3.1-flash-image-preview', 'name' => 'Gemini 3.1 Flash Image', 'vendor' => 'Google', 'desc' => 'Fast multimodal image generation', 'capabilities' => ['text', 'image'], 'tier' => 'default', 'endpoint_type' => 'chat', 'text_in_image' => true, 'image_price_per_call' => 0.030],
    ['id' => 'google/gemini-2.5-flash-image', 'name' => 'Gemini 2.5 Flash Image', 'vendor' => 'Google', 'desc' => 'Efficient image generation', 'capabilities' => ['text', 'image'], 'tier' => 'hemat', 'endpoint_type' => 'chat', 'text_in_image' => false, 'image_price_per_call' => 0.020],

    ['id' => 'deepseek/deepseek-v4-pro', 'name' => 'DeepSeek V4 Pro', 'vendor' => 'DeepSeek', 'desc' => 'Strong reasoning text model', 'capabilities' => ['text'], 'tier' => 'default', 'input_price_per_million' => 0.80, 'output_price_per_million' => 2.40],
    ['id' => 'deepseek/deepseek-v4-flash', 'name' => 'DeepSeek V4 Flash', 'vendor' => 'DeepSeek', 'desc' => 'Fast low-cost helper', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.15, 'output_price_per_million' => 0.50],
    ['id' => 'deepseek/deepseek-v3.2', 'name' => 'DeepSeek V3.2', 'vendor' => 'DeepSeek', 'desc' => 'General purpose text model', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.20, 'output_price_per_million' => 0.80],

    ['id' => 'qwen/qwen3.5-flash', 'name' => 'Qwen 3.5 Flash', 'vendor' => 'Qwen', 'desc' => 'Fast inference helper', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.15, 'output_price_per_million' => 0.50],
    ['id' => 'qwen/qwen3.6-plus', 'name' => 'Qwen 3.6 Plus', 'vendor' => 'Qwen', 'desc' => 'Balanced Qwen model', 'capabilities' => ['text'], 'tier' => 'default', 'input_price_per_million' => 0.50, 'output_price_per_million' => 1.50],
    ['id' => 'qwen/qwen3.5-9b', 'name' => 'Qwen 3.5 9B', 'vendor' => 'Qwen', 'desc' => 'Ultra compact helper', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.08, 'output_price_per_million' => 0.25],

    ['id' => 'x-ai/grok-4.1-fast', 'name' => 'Grok 4.1 Fast', 'vendor' => 'xAI', 'desc' => 'Low latency text model', 'capabilities' => ['text'], 'tier' => 'default', 'supports_json_mode' => false, 'input_price_per_million' => 1.00, 'output_price_per_million' => 4.00],
    ['id' => 'minimax/minimax-m2.7-highspeed', 'name' => 'MiniMax M2.7 HS', 'vendor' => 'MiniMax', 'desc' => 'Fast MiniMax text model', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.20, 'output_price_per_million' => 0.80],
    ['id' => 'z-ai/glm-5-turbo', 'name' => 'GLM 5 Turbo', 'vendor' => 'ZhiPu', 'desc' => 'Fast ZhiPu text model', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.20, 'output_price_per_million' => 0.80],
    ['id' => 'moonshotai/kimi-k2.6', 'name' => 'Kimi K2.6', 'vendor' => 'Moonshot', 'desc' => 'Moonshot general text model', 'capabilities' => ['text'], 'tier' => 'default', 'input_price_per_million' => 0.50, 'output_price_per_million' => 1.50],
    ['id' => 'stepfun/step-3.5-flash', 'name' => 'Step 3.5 Flash', 'vendor' => 'StepFun', 'desc' => 'Fast helper model', 'capabilities' => ['text'], 'tier' => 'hemat', 'input_price_per_million' => 0.15, 'output_price_per_million' => 0.50],

    ['id' => 'bytedance-seed/seedream-4.5', 'name' => 'Seedream 4.5', 'vendor' => 'ByteDance', 'desc' => 'ByteDance image generation', 'capabilities' => ['image'], 'tier' => 'default', 'endpoint_type' => 'images', 'text_in_image' => false, 'image_price_per_call' => 0.030],
];

return array_map(static function (array $model): array {
    $capabilities = $model['capabilities'] ?? ['text'];
    $isImage = in_array('image', $capabilities, true);
    $vendor = strtolower((string) ($model['vendor'] ?? ''));

    $defaults = [
        'endpoint_type' => $isImage ? 'images' : 'chat',
        'supports_json_mode' => in_array($vendor, ['openai', 'deepseek'], true),
        'supports_image' => $isImage,
        'supports_text' => in_array('text', $capabilities, true),
        'text_in_image' => false,
        'input_price_per_million' => 0.25,
        'output_price_per_million' => 1.00,
        'image_price_per_call' => $isImage ? 0.030 : 0.000,
        'recommended_cost_mode' => $model['tier'] ?? 'default',
    ];

    return $model + $defaults;
}, $models);
