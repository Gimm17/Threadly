<?php

namespace Tests\Feature;

use App\Jobs\SendPostReminderJob;
use App\Models\AnalyticsSnapshot;
use App\Models\ContentIdea;
use App\Models\ContentPillar;
use App\Models\GeneratedMedia;
use App\Models\GeneratedPost;
use App\Models\HookTemplate;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FullFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;
    private ContentPillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Gimora Digital',
            'threads_handle' => 'gimoradigital.id',
            'timezone' => 'Asia/Makassar',
            'plan' => 'pro',
            'settings' => ['post_reminder_minutes' => 30],
        ]);

        $this->user = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'role' => 'owner',
            'email' => 'admin@gimoradigital.id',
        ]);

        $this->pillar = ContentPillar::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Edukasi AI',
            'description' => 'Konten edukasi AI',
            'color_hex' => '#3B82F6',
            'icon' => 'sparkles',
            'frequency_unit' => 'week',
            'frequency_value' => 2,
        ]);
    }

    public function test_protected_pages_load_for_workspace_user(): void
    {
        $pages = [
            '/dashboard' => 'Dashboard/Index',
            '/kalender-konten' => 'ContentPlanner/Index',
            '/draft-jadwal' => 'Posts/Index',
            '/buat-konten' => 'Posts/Create',
            '/library' => 'Library/Index',
            '/analytics' => 'Analytics/Index',
            '/settings/general' => 'Settings/General',
            '/settings/ai-models' => 'Settings/AiModels',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }

        $redirects = [
            '/content-planner' => '/kalender-konten',
            '/posts' => '/draft-jadwal',
            '/posts/create' => '/buat-konten',
            '/hooks' => '/library?tab=hooks',
            '/image-studio' => '/library?tab=media',
            '/copywriting' => '/buat-konten',
        ];

        foreach ($redirects as $url => $target) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertRedirect($target);
        }
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_ai_usage_dashboard_counts_current_workspace_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 00:30:00', 'UTC'));

        try {
            DB::table('ai_usage_logs')->insert([
                [
                    'workspace_id' => $this->workspace->id,
                    'user_id' => $this->user->id,
                    'feature' => 'copywriting',
                    'model_id' => 'claude-sonnet-4.5',
                    'provider' => 'tokenrouter',
                    'prompt_tokens' => 100,
                    'completion_tokens' => 80,
                    'total_tokens' => 180,
                    'cost' => 0.0123,
                    'response_time_ms' => 1200,
                    'is_success' => true,
                    'created_at' => '2026-05-22 23:18:00',
                    'updated_at' => '2026-05-22 23:18:00',
                ],
                [
                    'workspace_id' => $this->workspace->id,
                    'user_id' => $this->user->id,
                    'feature' => 'image_generation',
                    'model_id' => 'gpt-5-image',
                    'provider' => 'tokenrouter',
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'cost' => 0.0400,
                    'response_time_ms' => 1800,
                    'is_success' => false,
                    'created_at' => '2026-05-22 23:20:00',
                    'updated_at' => '2026-05-22 23:20:00',
                ],
                [
                    'workspace_id' => $this->workspace->id,
                    'user_id' => $this->user->id,
                    'feature' => 'copywriting',
                    'model_id' => 'claude-sonnet-4.5',
                    'provider' => 'tokenrouter',
                    'prompt_tokens' => 50,
                    'completion_tokens' => 50,
                    'total_tokens' => 100,
                    'cost' => 0.0100,
                    'response_time_ms' => 900,
                    'is_success' => true,
                    'created_at' => '2026-05-22 12:00:00',
                    'updated_at' => '2026-05-22 12:00:00',
                ],
            ]);

            $this->actingAs($this->user)
                ->get('/settings/ai-models')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Settings/AiModels')
                    ->where('usageStats.total_requests_today', 2)
                    ->where('usageStats.total_tokens_today', 180)
                    ->where('usageStats.total_cost_today', 0.0523)
                    ->where('usageStats.success_rate_today', 50)
                    ->where('usageStats.timezone', 'Asia/Makassar')
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_profile_page_is_available(): void
    {
        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Profile/Edit'));
    }

    public function test_post_can_be_created_with_media_upload(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('threadly-post.jpg', 100, 'image/jpeg');

        $this->actingAs($this->user)
            ->post('/posts', [
                'body' => 'Konten edukasi AI untuk UMKM.',
                'hook' => 'AI bukan cuma tren.',
                'content_pillar_id' => $this->pillar->id,
                'status' => 'draft',
                'publish_mode' => 'manual',
                'media' => [$file],
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::withoutGlobalScopes()->firstOrFail();
        $media = PostMedia::firstOrFail();

        $this->assertSame($this->workspace->id, $post->workspace_id);
        $this->assertSame($post->id, $media->post_id);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_post_detail_page_shows_post_structure_and_media(): void
    {
        $post = Post::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'content_pillar_id' => $this->pillar->id,
            'body' => "Landing page membantu calon pelanggan paham layanan.\n\n#WebsiteUMKM #SEO",
            'hook' => 'Landing page bukan cuma soal tampilan.',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'publish_mode' => 'manual',
            'created_by' => $this->user->id,
        ]);

        PostMedia::create([
            'post_id' => $post->id,
            'file_path' => "workspaces/{$this->workspace->id}/posts/{$post->id}/poster.png",
            'file_name' => 'poster.png',
            'mime_type' => 'image/png',
            'file_size' => 2048,
            'type' => 'image',
            'sort_order' => 0,
            'is_ai_generated' => true,
            'ai_prompt' => 'Premium landing page poster background.',
        ]);

        $this->actingAs($this->user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Posts/Show')
                ->where('post.id', $post->id)
                ->where('post.hook', 'Landing page bukan cuma soal tampilan.')
                ->where('post.status', 'scheduled')
                ->where('post.pillar.name', 'Edukasi AI')
                ->where('post.media.0.file_name', 'poster.png')
                ->where('post.media.0.is_ai_generated', true)
            );
    }

    public function test_post_can_be_created_with_generated_ai_media(): void
    {
        Storage::fake('public');

        $generatedPath = "workspaces/{$this->workspace->id}/generated/ai-ready.png";
        Storage::disk('public')->put($generatedPath, 'fake image');

        $generated = GeneratedMedia::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'type' => 'image',
            'file_path' => $generatedPath,
            'file_name' => 'ai-ready.png',
            'mime_type' => 'image/png',
            'file_size' => Storage::disk('public')->size($generatedPath),
            'prompt' => 'Poster edukasi AI untuk UMKM',
            'enhanced_prompt' => 'Create a premium social media poster background.',
            'provider' => 'tokenrouter',
            'model_id' => 'google/gemini-3.1-flash-image-preview',
            'style' => 'photography',
            'aspect_ratio' => '1:1',
            'generation_status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->post('/posts', [
                'body' => 'Konten siap posting dengan poster AI.',
                'hook' => 'Poster AI siap pakai.',
                'content_pillar_id' => $this->pillar->id,
                'status' => 'draft',
                'publish_mode' => 'manual',
                'generated_media_ids' => [$generated->id],
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::withoutGlobalScopes()->firstOrFail();
        $media = PostMedia::firstOrFail();

        $this->assertSame($post->id, $media->post_id);
        $this->assertTrue($media->is_ai_generated);
        $this->assertStringContainsString("/posts/{$post->id}/", str_replace('\\', '/', $media->file_path));
        $this->assertSame($post->id, $generated->fresh()->post_id);
        Storage::disk('public')->assertExists($generatedPath);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_manual_scheduled_post_dispatches_reminder_job(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/posts', [
                'body' => 'Post manual yang perlu reminder.',
                'content_pillar_id' => $this->pillar->id,
                'status' => 'scheduled',
                'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
                'publish_mode' => 'manual',
            ])
            ->assertRedirect(route('posts.index'));

        Queue::assertPushed(SendPostReminderJob::class);
    }

    public function test_auto_scheduled_post_does_not_dispatch_manual_reminder(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/posts', [
                'body' => 'Post auto publish.',
                'content_pillar_id' => $this->pillar->id,
                'status' => 'scheduled',
                'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
                'publish_mode' => 'auto',
            ])
            ->assertRedirect(route('posts.index'));

        Queue::assertNotPushed(SendPostReminderJob::class);
    }

    public function test_content_idea_can_be_converted_to_post(): void
    {
        $idea = ContentIdea::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'content_pillar_id' => $this->pillar->id,
            'title' => 'Ide edukasi AI',
            'notes' => 'Bahas cara AI membantu UMKM.',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('content-ideas.to-post', $idea))
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'workspace_id' => $this->workspace->id,
            'content_idea_id' => $idea->id,
            'status' => 'draft',
            'publish_mode' => 'manual',
        ]);

        $this->assertSame('in_progress', $idea->fresh()->status);
    }

    public function test_ai_endpoints_fail_gracefully_without_api_key(): void
    {
        config(['services.tokenrouter.key' => null]);

        $this->actingAs($this->user)
            ->postJson('/ai/generate-hook', ['topic' => 'AI untuk UMKM'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_publish_to_threads_requires_access_token(): void
    {
        $post = Post::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'content_pillar_id' => $this->pillar->id,
            'body' => 'Post tanpa token.',
            'status' => 'draft',
            'publish_mode' => 'manual',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('posts.publish-threads', $post))
            ->assertSessionHas('error');
    }

    public function test_generated_post_can_be_saved_as_draft(): void
    {
        $generated = GeneratedPost::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'source' => 'ready_post',
            'prompt' => 'Buat post edukasi AI untuk UMKM',
            'hook' => 'AI bukan cuma tren.',
            'body' => 'Konten awal dari AI.',
            'hashtags' => ['#AI', '#UMKM'],
            'status' => 'generated',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('generated-posts.save-draft', $generated), [
                'hook' => 'Hook sudah diedit.',
                'body' => 'Body hasil edit di editor.',
                'content_pillar_id' => $this->pillar->id,
            ])
            ->assertOk()
            ->assertJsonPath('generated_post.status', 'drafted')
            ->assertJsonPath('post.status', 'draft');

        $this->assertDatabaseHas('posts', [
            'workspace_id' => $this->workspace->id,
            'content_pillar_id' => $this->pillar->id,
            'hook' => 'Hook sudah diedit.',
            'body' => 'Body hasil edit di editor.',
            'status' => 'draft',
            'publish_mode' => 'manual',
        ]);

        $this->assertSame('drafted', $generated->fresh()->status);
    }

    public function test_generated_post_can_be_scheduled(): void
    {
        Queue::fake();

        $generated = GeneratedPost::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'source' => 'ready_post',
            'prompt' => 'Buat post jadwal AI untuk UMKM',
            'hook' => 'Jadwal konten bisa rapi.',
            'body' => 'Konten scheduled dari AI.',
            'status' => 'generated',
        ]);

        $scheduledAt = now()->addHours(2)->format('Y-m-d H:i:s');

        $this->actingAs($this->user)
            ->postJson(route('generated-posts.schedule', $generated), [
                'scheduled_at' => $scheduledAt,
                'publish_mode' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('generated_post.status', 'scheduled')
            ->assertJsonPath('post.status', 'scheduled');

        $this->assertDatabaseHas('posts', [
            'workspace_id' => $this->workspace->id,
            'body' => 'Konten scheduled dari AI.',
            'status' => 'scheduled',
            'publish_mode' => 'manual',
        ]);

        Queue::assertPushed(SendPostReminderJob::class);
    }

    public function test_generated_post_publish_now_requires_threads_token(): void
    {
        $generated = GeneratedPost::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'source' => 'ready_post',
            'prompt' => 'Publish langsung ke Threads',
            'hook' => 'Publish cepat.',
            'body' => 'Konten publish now.',
            'status' => 'generated',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('generated-posts.publish-now', $generated))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Threads API belum dikonfigurasi. Simpan sebagai Draft atau atur token di Settings terlebih dahulu.']);

        $this->assertDatabaseMissing('posts', [
            'workspace_id' => $this->workspace->id,
            'body' => 'Konten publish now.',
        ]);
    }

    public function test_generated_post_from_another_workspace_cannot_be_accessed(): void
    {
        $otherWorkspace = Workspace::create([
            'name' => 'Workspace Lain',
            'timezone' => 'Asia/Makassar',
            'plan' => 'free',
            'settings' => [],
        ]);

        $generated = GeneratedPost::withoutGlobalScopes()->create([
            'workspace_id' => $otherWorkspace->id,
            'source' => 'ready_post',
            'prompt' => 'Konten workspace lain',
            'body' => 'Tidak boleh diakses.',
            'status' => 'generated',
        ]);

        $this->actingAs($this->user)
            ->postJson(route('generated-posts.save-draft', $generated))
            ->assertNotFound();
    }

    public function test_analytics_snapshot_store_supports_threads_metrics(): void
    {
        $date = now()->toDateString();

        $this->actingAs($this->user)
            ->post('/analytics/snapshot', [
                'snapshot_date' => $date,
                'followers_count' => 1000,
                'impressions' => 5000,
                'likes' => 120,
                'replies' => 20,
                'reposts' => 10,
                'quotes' => 5,
                'engagement_rate' => 15.5,
            ])
            ->assertRedirect();

        $snapshot = AnalyticsSnapshot::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(120, $snapshot->likes);
        $this->assertSame(20, $snapshot->replies);
        $this->assertSame('manual', $snapshot->source);

        $this->actingAs($this->user)
            ->post('/analytics/snapshot', [
                'snapshot_date' => $date,
                'followers_count' => 1100,
                'impressions' => 5500,
                'likes' => 140,
                'replies' => 25,
                'reposts' => 12,
                'quotes' => 6,
                'engagement_rate' => 16.1,
            ])
            ->assertRedirect();

        $this->assertSame(1, AnalyticsSnapshot::withoutGlobalScopes()->count());
        $this->assertSame(140, $snapshot->refresh()->likes);
    }

    public function test_analytics_sync_requires_threads_access_token(): void
    {
        $this->actingAs($this->user)
            ->post('/analytics/sync')
            ->assertSessionHas('error');
    }

    public function test_hook_template_json_actions_work(): void
    {
        $hook = HookTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'hook_text' => 'AI bisa bikin kerja UMKM lebih ringan.',
            'category' => 'AI',
            'score' => 70,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('hooks.save', $hook))
            ->assertOk()
            ->assertJsonPath('save_count', 1);

        $this->actingAs($this->user)
            ->postJson(route('hooks.use', $hook))
            ->assertOk()
            ->assertJsonPath('usage_count', 1);
    }

    public function test_notifications_can_be_marked_as_read(): void
    {
        $notification = Notification::notify(
            userId: $this->user->id,
            type: 'post_published',
            title: 'Post published',
            workspaceId: $this->workspace->id,
        );

        $this->actingAs($this->user)
            ->getJson('/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($this->user)
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'notifications');

        $this->actingAs($this->user)
            ->postJson(route('notifications.mark-read', $notification))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_workspace_threads_token_is_encrypted_and_legacy_plaintext_is_supported(): void
    {
        $this->workspace->threads_access_token = 'fresh-token';
        $this->workspace->save();

        $rawToken = Workspace::query()
            ->whereKey($this->workspace->id)
            ->first()
            ->getRawOriginal('threads_access_token');

        $this->assertNotSame('fresh-token', $rawToken);
        $this->assertSame('fresh-token', $this->workspace->fresh()->threads_access_token);

        DB::table('workspaces')
            ->where('id', $this->workspace->id)
            ->update(['threads_access_token' => 'legacy-token']);

        $this->assertSame('legacy-token', $this->workspace->fresh()->threads_access_token);
    }
}
