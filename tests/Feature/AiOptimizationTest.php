<?php

namespace Tests\Feature;

use App\Jobs\GeneratePosterImageJob;
use App\Models\AiModelConfig;
use App\Models\GeneratedMedia;
use App\Models\GeneratedPost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();

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

    public function test_ready_post_generates_copy_and_image_media(): void
    {
        Storage::fake('public');
        Bus::fake();

        $base64 = base64_encode('ready-post-image');
        $checkSymbol = "\u{2705}";
        Http::fake([
            'https://api.tokenrouter.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'hook' => 'Admin chat mulai kewalahan?',
                                'hook_variants' => [
                                    ['hook' => 'Admin chat mulai kewalahan?', 'angle' => 'pain point', 'score' => 86],
                                ],
                                'body' => str_repeat('Mulai dari satu alur sederhana agar admin UMKM lebih rapi. ', 10) . "\n\n{$checkSymbol} Katalog produk rapi\n{$checkSymbol} Chat pelanggan jelas",
                                'hashtags' => ['#WebsiteUMKM', '#CustomerService', '#DigitalisasiUMKM'],
                                'headline' => 'Chat Lebih Rapi',
                                'poster_brief' => 'Professional Indonesian small business admin desk, clean composition, no visible text.',
                                'quality_notes' => ['Siap dipakai'],
                            ]),
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 120, 'total_tokens' => 240],
                ])
                ->push([
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
                ])
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/ai/ready-post', [
                'topic' => 'Buat post edukasi tentang merapikan admin chat UMKM',
                'pillar' => 'Edukasi AI',
                'style' => 'photography',
                'aspect_ratio' => '1:1',
                'generate_image' => true,
            ])
            ->assertOk()
            ->assertJsonPath('post.hook', 'Admin chat mulai kewalahan?')
            ->assertJsonPath('generated_post.status', 'generated')
            ->assertJsonPath('generated_post.prompt', 'Buat post edukasi tentang merapikan admin chat UMKM')
            ->assertJsonPath('image_prompt.status', 'queued')
            ->assertJsonPath('image_prompt.style', 'photography')
            ->assertJsonPath('image_prompt.aspect_ratio', '1:1')
            ->assertJsonPath('media.generation_mode', 'ready-post')
            ->assertJsonPath('media.generation_status', 'queued');

        $body = (string) $response->json('post.body');

        $this->assertLessThanOrEqual(500, mb_strlen($body));
        $this->assertStringNotContainsString($checkSymbol, $body);
        $this->assertStringContainsString('#DigitalisasiUMKM', $body);

        $media = GeneratedMedia::withoutGlobalScopes()->firstOrFail();
        $generatedPost = GeneratedPost::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('Chat Lebih Rapi', $media->overlay_config['headline']);
        $this->assertStringContainsString('no visible text', $media->enhanced_prompt);
        $this->assertSame('queued', $media->generation_status);
        $this->assertSame($this->workspace->id, $generatedPost->workspace_id);
        $this->assertSame($media->id, $generatedPost->media_id);
        $this->assertSame('generated', $generatedPost->status);
        $this->assertSame('photography', $generatedPost->metadata['image_prompt']['style']);
        $this->assertStringContainsString('customer communication', $generatedPost->metadata['image_prompt']['visual_prompt']);
        Http::assertSentCount(1);
        Bus::assertDispatched(GeneratePosterImageJob::class);

        $job = new GeneratePosterImageJob($media->id, [
            'style' => 'photography',
            'aspect_ratio' => '1:1',
            'quality' => 'auto',
            'background' => 'auto',
        ]);
        $job->handle(app(\App\Services\AIService::class));

        $media->refresh();

        $this->assertSame('completed', $media->generation_status);
        Storage::disk('public')->assertExists($media->file_path);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => data_get($request->data(), 'size') === '1024x1024'
            && data_get($request->data(), 'modalities.1') === 'image');
    }

    public function test_ready_post_keeps_copy_when_image_generation_fails(): void
    {
        Bus::fake();

        Http::fake([
            'https://api.tokenrouter.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'hook' => 'Marketplace terasa makin berat?',
                                'hook_variants' => [],
                                'body' => 'Website sendiri membantu UMKM mengatur katalog, komunikasi, dan transaksi dengan lebih rapi.',
                                'hashtags' => ['#WebsiteUMKM'],
                                'headline' => 'Website Lebih Rapi',
                                'poster_brief' => 'Clean 3D ecommerce visual, no visible text.',
                                'quality_notes' => [],
                            ]),
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 80, 'total_tokens' => 160],
                ])
                ->push(['error' => ['message' => 'provider timeout']], 504)
                ->push(['error' => ['message' => 'provider timeout']], 504)
                ->push(['error' => ['message' => 'provider timeout']], 504)
        ]);

        $this->actingAs($this->user)
            ->postJson('/ai/ready-post', [
                'topic' => 'Migrasi dari marketplace ke website sendiri',
                'style' => '3d',
                'aspect_ratio' => '1:1',
                'generate_image' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('post.hook', 'Marketplace terasa makin berat?')
            ->assertJsonPath('generated_post.status', 'generated')
            ->assertJsonPath('image_prompt.status', 'queued')
            ->assertJsonPath('image_prompt.style', '3d')
            ->assertJsonPath('media.generation_status', 'queued')
            ->assertJsonPath('image_error', null);

        $this->assertDatabaseHas('generated_posts', [
            'workspace_id' => $this->workspace->id,
            'prompt' => 'Migrasi dari marketplace ke website sendiri',
            'status' => 'generated',
        ]);

        $generatedPost = GeneratedPost::withoutGlobalScopes()->firstOrFail();
        $media = GeneratedMedia::withoutGlobalScopes()->findOrFail($generatedPost->media_id);
        Bus::assertDispatched(GeneratePosterImageJob::class);

        $job = new GeneratePosterImageJob($media->id, [
            'style' => '3d',
            'aspect_ratio' => '1:1',
            'quality' => 'auto',
            'background' => 'auto',
        ]);

        try {
            $job->handle(app(\App\Services\AIService::class));
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $this->assertSame('failed', $media->fresh()->generation_status);
        $this->assertStringContainsString('AI Image API error [504]', $media->fresh()->error_message);
    }

    public function test_image_job_falls_back_when_selected_model_returns_404(): void
    {
        Storage::fake('public');

        AiModelConfig::withoutGlobalScopes()
            ->where('workspace_id', $this->workspace->id)
            ->where('feature', 'image_generation')
            ->update(['model_id' => 'openai/gpt-5-image']);

        $base64 = base64_encode('fallback-image');
        $media = GeneratedMedia::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'image',
            'file_path' => '',
            'file_name' => '',
            'mime_type' => 'image/png',
            'file_size' => 0,
            'prompt' => 'Poster edukasi AI untuk UMKM',
            'original_prompt' => 'Poster edukasi AI untuk UMKM',
            'enhanced_prompt' => 'Create a premium social media poster for Indonesian UMKM, no visible text.',
            'provider' => 'tokenrouter',
            'model_id' => 'openai/gpt-5-image',
            'style' => 'photography',
            'aspect_ratio' => '4:5',
            'generation_mode' => 'ready-post',
            'generation_status' => 'queued',
        ]);

        Http::fake([
            'https://api.tokenrouter.com/v1/images/generations' => Http::response(['error' => ['message' => 'openai_error']], 404),
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

        $job = new GeneratePosterImageJob($media->id, [
            'style' => 'photography',
            'aspect_ratio' => '4:5',
            'quality' => 'auto',
            'background' => 'auto',
        ]);
        $job->handle(app(\App\Services\AIService::class));

        $media->refresh();

        $this->assertSame('completed', $media->generation_status);
        $this->assertSame('google/gemini-3.1-flash-image-preview', $media->model_id);
        Storage::disk('public')->assertExists($media->file_path);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.tokenrouter.com/v1/images/generations'
            && data_get($request->data(), 'model') === 'openai/gpt-5-image');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.tokenrouter.com/v1/chat/completions'
            && data_get($request->data(), 'model') === 'google/gemini-3.1-flash-image-preview'
            && data_get($request->data(), 'size') === '1024x1280');
    }
}
