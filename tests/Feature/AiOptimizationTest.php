<?php

namespace Tests\Feature;

use App\Models\AiModelConfig;
use App\Models\GeneratedMedia;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Gimora Digital',
            'threads_handle' => 'gimoradigital.id',
            'timezone' => 'Asia/Makassar',
            'plan' => 'pro',
            'settings' => [],
        ]);

        $this->user = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'role' => 'owner',
        ]);

        foreach ([
            ['feature' => 'hook_generator', 'model_id' => 'deepseek/deepseek-v4-flash', 'max_tokens' => 700],
            ['feature' => 'copywriting', 'model_id' => 'deepseek/deepseek-v4-flash', 'max_tokens' => 1200],
            ['feature' => 'image_generation', 'model_id' => 'google/gemini-3.1-flash-image-preview', 'max_tokens' => 4096],
            ['feature' => 'insight', 'model_id' => 'deepseek/deepseek-v4-flash', 'max_tokens' => 900],
        ] as $config) {
            AiModelConfig::withoutGlobalScopes()->create([
                ...$config,
                'workspace_id' => $this->workspace->id,
                'provider' => 'tokenrouter',
                'temperature' => 0.4,
                'is_active' => true,
            ]);
        }

        config([
            'services.tokenrouter.key' => 'test-token',
            'services.tokenrouter.base_url' => 'https://api.tokenrouter.com/v1',
        ]);
    }

    public function test_content_assist_uses_json_mode_and_cache(): void
    {
        Http::fake([
            'https://api.tokenrouter.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'hooks' => [
                                ['hook' => 'AI bukan cuma tren untuk UMKM.', 'angle' => 'education', 'score' => 84, 'reason' => 'spesifik'],
                            ],
                            'cta' => 'Simpan untuk referensi.',
                            'hashtags' => ['#AI', '#UMKM'],
                            'quality_notes' => ['Sudah ringkas'],
                        ]),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 80, 'total_tokens' => 180],
            ]),
        ]);

        $payload = ['topic' => 'AI untuk UMKM', 'pillar' => 'Edukasi AI'];

        $this->actingAs($this->user)
            ->postJson('/ai/content-assist', $payload)
            ->assertOk()
            ->assertJsonPath('assist.hooks.0.hook', 'AI bukan cuma tren untuk UMKM.');

        $this->actingAs($this->user)
            ->postJson('/ai/content-assist', $payload)
            ->assertOk()
            ->assertJsonPath('assist.hashtags.0', '#AI');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => data_get($request->data(), 'response_format.type') === 'json_object'
            && str_contains(data_get($request->data(), 'messages.1.content'), 'invoice, stok, chat pelanggan, laporan harian')
            && str_contains(data_get($request->data(), 'messages.1.content'), 'tanpa #viral/#fyp/#trending'));
    }

    public function test_generate_hashtags_has_dedicated_endpoint(): void
    {
        Http::fake([
            'https://api.tokenrouter.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"hashtags":["#AI","#UMKM","#Produktivitas"]}'],
                ]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20, 'total_tokens' => 70],
            ]),
        ]);

        $this->actingAs($this->user)
            ->postJson('/ai/generate-hashtags', ['text' => 'AI membantu admin UMKM lebih cepat.'])
            ->assertOk()
            ->assertJsonPath('hashtags.0', '#AI');
    }

    public function test_poster_workflow_stores_enhanced_prompt_and_metadata(): void
    {
        Storage::fake('public');

        $base64 = base64_encode('fake-image');
        Http::fake([
            'https://api.tokenrouter.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => "data:image/png;base64,{$base64}"],
                            ],
                        ],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 0, 'total_tokens' => 20],
            ]),
        ]);

        $this->actingAs($this->user)
            ->postJson('/image-studio/poster', [
                'prompt' => 'Poster edukasi AI untuk UMKM',
                'headline' => 'AI Bantu UMKM',
                'style' => 'minimalist',
                'aspect_ratio' => '4:5',
            ])
            ->assertOk()
            ->assertJsonPath('media.generation_mode', 'poster')
            ->assertJsonPath('media.generation_status', 'completed');

        $media = GeneratedMedia::withoutGlobalScopes()->firstOrFail();

        $this->assertStringContainsString('Gimora Digital', $media->enhanced_prompt);
        $this->assertStringContainsString('Foreground subject', $media->enhanced_prompt);
        $this->assertSame('AI Bantu UMKM', $media->overlay_config['headline']);
        Storage::disk('public')->assertExists($media->file_path);
    }
}
