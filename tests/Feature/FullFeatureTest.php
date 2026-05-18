<?php

namespace Tests\Feature;

use App\Jobs\SendPostReminderJob;
use App\Models\AnalyticsSnapshot;
use App\Models\ContentIdea;
use App\Models\ContentPillar;
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
            '/content-planner' => 'ContentPlanner/Index',
            '/posts' => 'Posts/Index',
            '/posts/create' => 'Posts/Create',
            '/analytics' => 'Analytics/Index',
            '/hooks' => 'Hooks/Index',
            '/settings/general' => 'Settings/General',
            '/settings/ai-models' => 'Settings/AiModels',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
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
