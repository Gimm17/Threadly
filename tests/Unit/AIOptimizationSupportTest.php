<?php

namespace Tests\Unit;

use App\Services\AI\CostEstimator;
use App\Services\AI\AICacheService;
use App\Services\AI\JsonResponseParser;
use App\Services\AI\ModelCatalogService;
use App\Services\AI\PosterPromptBuilder;
use Tests\TestCase;

class AIOptimizationSupportTest extends TestCase
{
    public function test_json_parser_extracts_fenced_json(): void
    {
        $parsed = app(JsonResponseParser::class)->parse("```json\n{\"ok\":true}\n```");

        $this->assertSame(['ok' => true], $parsed);
    }

    public function test_model_catalog_resolves_json_and_image_capabilities(): void
    {
        $catalog = app(ModelCatalogService::class);

        $this->assertTrue($catalog->supportsJsonMode('deepseek/deepseek-v4-flash'));
        $this->assertSame('chat', $catalog->endpointType('google/gemini-3.1-flash-image-preview'));
        $this->assertSame('images', $catalog->endpointType('bytedance-seed/seedream-4.5'));
    }

    public function test_cost_estimator_uses_model_catalog_rates(): void
    {
        $cost = app(CostEstimator::class)->textCost('deepseek/deepseek-v4-flash', 1000, 1000);

        $this->assertGreaterThan(0, $cost);
        $this->assertLessThan(0.01, $cost);
    }

    public function test_poster_prompt_builder_adds_brand_and_negative_constraints(): void
    {
        $prompt = app(PosterPromptBuilder::class)->build([
            'prompt' => 'Poster edukasi AI untuk UMKM',
            'headline' => 'AI Bantu UMKM',
            'aspect_ratio' => '4:5',
        ]);

        $this->assertStringContainsString('Gimora Digital', $prompt['enhanced_prompt']);
        $this->assertStringContainsString('Foreground subject', $prompt['enhanced_prompt']);
        $this->assertStringContainsString('Do not render any text', $prompt['enhanced_prompt']);
        $this->assertStringContainsString('No watermark', $prompt['negative_prompt']);
        $this->assertSame('AI Bantu UMKM', $prompt['overlay_config']['headline']);
    }

    public function test_poster_prompt_builder_respects_3d_style(): void
    {
        $prompt = app(PosterPromptBuilder::class)->build([
            'prompt' => 'Migrasi dari marketplace ke website sendiri',
            'style' => '3d',
            'aspect_ratio' => '1:1',
        ]);

        $this->assertStringContainsString('premium 3D render', $prompt['enhanced_prompt']);
        $this->assertStringContainsString('not a real photograph', $prompt['enhanced_prompt']);
        $this->assertStringContainsString('no split-screen', $prompt['enhanced_prompt']);
        $this->assertStringContainsString('no vertical side panel', $prompt['negative_prompt']);
    }

    public function test_ai_cache_key_includes_prompt_version(): void
    {
        $cache = app(AICacheService::class);

        config(['ai-prompts.version' => 'test-v1']);
        $first = $cache->key('content_assist', 1, 'deepseek/deepseek-v4-flash', ['topic' => 'AI UMKM']);

        config(['ai-prompts.version' => 'test-v2']);
        $second = $cache->key('content_assist', 1, 'deepseek/deepseek-v4-flash', ['topic' => 'AI UMKM']);

        $this->assertNotSame($first, $second);
    }
}
