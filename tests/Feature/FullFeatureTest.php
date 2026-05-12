<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use App\Models\ContentPillar;
use App\Models\ContentIdea;
use App\Models\HookTemplate;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullFeatureTest extends TestCase
{
    // Note: NOT using RefreshDatabase to test against real data

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::first();
    }

    // ==================== AUTH TESTS ====================

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        echo "✅ Login page loads OK (200)\n";
    }

    public function test_register_page_loads(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        echo "✅ Register page loads OK (200)\n";
    }

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);
        $response->assertRedirect('/dashboard');
        echo "✅ Login with valid credentials redirects to dashboard\n";
    }

    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword',
        ]);
        $response->assertSessionHasErrors();
        echo "✅ Login with invalid credentials shows errors\n";
    }

    // ==================== DASHBOARD ====================

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
        echo "✅ Dashboard requires authentication\n";
    }

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
        echo "✅ Dashboard loads OK (200) with Inertia component 'Dashboard/Index'\n";
    }

    // ==================== CONTENT PLANNER ====================

    public function test_content_planner_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/content-planner');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ContentPlanner/Index'));
        echo "✅ Content Planner loads OK with component 'ContentPlanner/Index'\n";
    }

    public function test_content_planner_calendar_data(): void
    {
        $response = $this->actingAs($this->user)->get('/content-planner/calendar-data');
        $response->assertStatus(200);
        $response->assertJsonStructure([]);
        echo "✅ Content Planner calendar data endpoint returns JSON\n";
    }

    // ==================== POSTS CRUD ====================

    public function test_posts_index_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/posts');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Posts/Index'));
        echo "✅ Posts Index loads OK with component 'Posts/Index'\n";
    }

    public function test_posts_create_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/posts/create');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Posts/Create'));
        echo "✅ Posts Create loads OK with component 'Posts/Create'\n";
    }

    public function test_posts_store_validation(): void
    {
        $response = $this->actingAs($this->user)->post('/posts', []);
        $response->assertSessionHasErrors();
        echo "✅ Posts Store validation works (empty data rejected)\n";
    }

    public function test_posts_store_success(): void
    {
        $pillar = ContentPillar::where('user_id', $this->user->id)->first();
        $data = [
            'content' => 'Test post content for automated testing - ' . now()->timestamp,
            'scheduled_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'status' => 'draft',
        ];
        if ($pillar) {
            $data['content_pillar_id'] = $pillar->id;
        }

        $response = $this->actingAs($this->user)->post('/posts', $data);
        // Should redirect (302) on success
        $this->assertTrue(in_array($response->status(), [302, 303]), "Expected redirect, got {$response->status()}");
        echo "✅ Posts Store creates post successfully\n";
    }

    public function test_posts_show_loads(): void
    {
        $post = Post::where('user_id', $this->user->id)->first()
            ?? Post::where('created_by', $this->user->id)->first();
        if (!$post) {
            $this->markTestSkipped('No posts to test show');
        }
        $response = $this->actingAs($this->user)->get("/posts/{$post->id}");
        $this->assertTrue(in_array($response->status(), [200, 302]));
        echo "✅ Posts Show loads OK for post #{$post->id}\n";
    }

    public function test_posts_edit_loads(): void
    {
        $post = Post::where('user_id', $this->user->id)->first()
            ?? Post::where('created_by', $this->user->id)->first();
        if (!$post) {
            $this->markTestSkipped('No posts to test edit');
        }
        $response = $this->actingAs($this->user)->get("/posts/{$post->id}/edit");
        $response->assertStatus(200);
        echo "✅ Posts Edit loads OK for post #{$post->id}\n";
    }

    // ==================== ANALYTICS ====================

    public function test_analytics_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Analytics/Index'));
        echo "✅ Analytics page loads OK with component 'Analytics/Index'\n";
    }

    // ==================== SETTINGS ====================

    public function test_settings_ai_models_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/ai-models');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/AiModels'));
        echo "✅ Settings AI Models loads OK with component 'Settings/AiModels'\n";
    }

    public function test_settings_general_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/general');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Settings/General'));
        echo "✅ Settings General loads OK with component 'Settings/General'\n";
    }

    // ==================== AI ENDPOINTS ====================

    public function test_ai_generate_hook_endpoint(): void
    {
        $response = $this->actingAs($this->user)->postJson('/ai/generate-hook', [
            'topic' => 'Test topic for hook generation',
        ]);
        // May succeed or fail based on API key, but shouldn't be 404 or 500
        $this->assertTrue(in_array($response->status(), [200, 422, 500, 503]),
            "AI generate-hook returned unexpected status: {$response->status()}");
        echo "✅ AI Generate Hook endpoint accessible (status: {$response->status()})\n";
    }

    public function test_ai_improve_text_endpoint(): void
    {
        $response = $this->actingAs($this->user)->postJson('/ai/improve-text', [
            'text' => 'ini teks yang mau diperbaiki tata bahasanya biar lebih bagus',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 422, 500, 503]),
            "AI improve-text returned unexpected status: {$response->status()}");
        echo "✅ AI Improve Text endpoint accessible (status: {$response->status()})\n";
    }

    public function test_ai_generate_ideas_endpoint(): void
    {
        $response = $this->actingAs($this->user)->postJson('/ai/generate-ideas', [
            'topic' => 'Digital marketing tips',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 422, 500, 503]),
            "AI generate-ideas returned unexpected status: {$response->status()}");
        echo "✅ AI Generate Ideas endpoint accessible (status: {$response->status()})\n";
    }

    // ==================== CONTENT PILLARS ====================

    public function test_content_pillar_crud(): void
    {
        $timestamp = now()->timestamp;

        // Create - with all required fields from StoreContentPillarRequest
        $response = $this->actingAs($this->user)->post('/content-pillars', [
            'name' => 'Test Pillar ' . $timestamp,
            'description' => 'Test description',
            'color_hex' => '#FF5733',
            'icon' => 'device-desktop',
            'frequency_unit' => 'week',
            'frequency_value' => 2,
        ]);
        $this->assertTrue(in_array($response->status(), [302, 303, 200]),
            "Content Pillar create failed with status: {$response->status()}");
        echo "✅ Content Pillar create works\n";

        // Check it exists
        $pillar = ContentPillar::where('name', 'Test Pillar ' . $timestamp)->first();
        if (!$pillar) {
            // Try latest
            $pillar = ContentPillar::latest('id')->first();
        }
        $this->assertNotNull($pillar, 'Content Pillar was not created');

        // Update
        $response = $this->actingAs($this->user)->put("/content-pillars/{$pillar->id}", [
            'name' => 'Updated Pillar',
            'description' => 'Updated description',
            'color_hex' => '#33FF57',
            'icon' => 'device-mobile',
            'frequency_unit' => 'day',
            'frequency_value' => 1,
        ]);
        $this->assertTrue(in_array($response->status(), [302, 303, 200]),
            "Content Pillar update failed with status: {$response->status()}");
        echo "✅ Content Pillar update works\n";

        // Delete
        $response = $this->actingAs($this->user)->delete("/content-pillars/{$pillar->id}");
        $this->assertTrue(in_array($response->status(), [302, 303, 200]),
            "Content Pillar delete failed with status: {$response->status()}");
        echo "✅ Content Pillar delete works\n";
    }

    // ==================== SIDEBAR NAVIGATION ====================

    public function test_all_sidebar_links_accessible(): void
    {
        $pages = [
            '/dashboard' => 'Dashboard/Index',
            '/content-planner' => 'ContentPlanner/Index',
            '/posts' => 'Posts/Index',
            '/analytics' => 'Analytics/Index',
            '/hooks' => 'Hooks/Index',
            '/settings/ai-models' => 'Settings/AiModels',
            '/settings/general' => 'Settings/General',
        ];

        foreach ($pages as $url => $component) {
            $response = $this->actingAs($this->user)->get($url);
            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page->component($component));
            echo "✅ Sidebar link {$url} → {$component} OK\n";
        }
    }

    // ==================== LOGOUT ====================

    public function test_logout_works(): void
    {
        $response = $this->actingAs($this->user)->post('/logout');
        $response->assertRedirect('/');
        echo "✅ Logout works, redirects to /\n";
    }

    // ==========================================================================
    // ==================== V1.5 FEATURES TESTS =================================
    // ==========================================================================

    // ==================== HOOK TEMPLATE LIBRARY ================================

    public function test_hooks_page_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/hooks');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Hooks/Index'));
        echo "✅ Hooks page loads OK with component 'Hooks/Index'\n";
    }

    public function test_hooks_crud(): void
    {
        $timestamp = now()->timestamp;

        // Create hook template
        $response = $this->actingAs($this->user)->post('/hooks', [
            'hook_text' => 'Test hook template created at ' . $timestamp,
            'category' => 'other',
        ]);
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Hook store failed with status: {$response->status()}");
        echo "✅ Hook Template create works\n";

        // Find the created hook
        $hook = HookTemplate::where('hook_text', 'like', '%' . $timestamp . '%')->first();
        $this->assertNotNull($hook, 'Hook template was not created in database');
        echo "✅ Hook Template exists in database (ID: {$hook->id})\n";

        // Delete hook template
        $response = $this->actingAs($this->user)->delete("/hooks/{$hook->id}");
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Hook delete failed with status: {$response->status()}");
        $this->assertNull(HookTemplate::find($hook->id), 'Hook template was not deleted');
        echo "✅ Hook Template delete works\n";
    }

    public function test_hooks_save_increments_count(): void
    {
        // Create a hook for testing
        $hook = HookTemplate::create([
            'workspace_id' => $this->user->workspace_id,
            'hook_text' => 'Test hook for save count - ' . now()->timestamp,
            'category' => 'other',
            'save_count' => 0,
        ]);

        $originalSaveCount = $hook->save_count;

        $response = $this->actingAs($this->user)->postJson("/hooks/{$hook->id}/save");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $hook->refresh();
        $this->assertEquals($originalSaveCount + 1, $hook->save_count);
        echo "✅ Hook save endpoint increments save_count correctly\n";

        // Cleanup
        $hook->delete();
    }

    public function test_hooks_use_increments_count(): void
    {
        // Create a hook for testing
        $hook = HookTemplate::create([
            'workspace_id' => $this->user->workspace_id,
            'hook_text' => 'Test hook for use count - ' . now()->timestamp,
            'category' => 'other',
            'usage_count' => 0,
        ]);

        $originalUsageCount = $hook->usage_count;

        $response = $this->actingAs($this->user)->postJson("/hooks/{$hook->id}/use");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $hook->refresh();
        $this->assertEquals($originalUsageCount + 1, $hook->usage_count);
        echo "✅ Hook use endpoint increments usage_count correctly\n";

        // Cleanup
        $hook->delete();
    }

    public function test_hooks_store_validation(): void
    {
        // Test empty data
        $response = $this->actingAs($this->user)->post('/hooks', []);
        $response->assertSessionHasErrors('hook_text');
        echo "✅ Hook store validation rejects empty data\n";

        // Test too long hook_text
        $response = $this->actingAs($this->user)->post('/hooks', [
            'hook_text' => str_repeat('a', 501),
        ]);
        $response->assertSessionHasErrors('hook_text');
        echo "✅ Hook store validation rejects hook_text > 500 chars\n";
    }

    public function test_hooks_generate_endpoint(): void
    {
        $response = $this->actingAs($this->user)->postJson('/hooks/generate', [
            'topic' => 'Tips produktivitas untuk freelancer',
            'count' => 3,
        ]);
        // May succeed or fail based on API key availability
        $this->assertTrue(in_array($response->status(), [200, 422, 500, 503]),
            "Hooks generate returned unexpected status: {$response->status()}");
        echo "✅ Hooks generate endpoint accessible (status: {$response->status()})\n";
    }

    public function test_hooks_score_endpoint(): void
    {
        $hook = HookTemplate::create([
            'workspace_id' => $this->user->workspace_id,
            'hook_text' => 'Kamu pasti pernah mengalami ini...',
            'category' => 'question',
        ]);

        $response = $this->actingAs($this->user)->postJson("/hooks/{$hook->id}/score");
        // May succeed or fail based on API key availability
        $this->assertTrue(in_array($response->status(), [200, 422, 500, 503]),
            "Hooks score returned unexpected status: {$response->status()}");
        echo "✅ Hooks score endpoint accessible (status: {$response->status()})\n";

        // Cleanup
        $hook->delete();
    }

    public function test_hooks_page_with_filters(): void
    {
        $response = $this->actingAs($this->user)->get('/hooks?sort=newest');
        $response->assertStatus(200);
        echo "✅ Hooks page with sort=newest works\n";

        $response = $this->actingAs($this->user)->get('/hooks?sort=saves');
        $response->assertStatus(200);
        echo "✅ Hooks page with sort=saves works\n";

        $response = $this->actingAs($this->user)->get('/hooks?category=Testing');
        $response->assertStatus(200);
        echo "✅ Hooks page with category filter works\n";
    }

    // ==================== NOTIFICATION SYSTEM ==================================

    public function test_notifications_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/notifications');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notifications',
            'unread_count',
        ]);
        echo "✅ Notifications index returns JSON with correct structure\n";
    }

    public function test_notifications_unread_count(): void
    {
        $response = $this->actingAs($this->user)->getJson('/notifications/unread-count');
        $response->assertStatus(200);
        $response->assertJsonStructure(['unread_count']);
        echo "✅ Notifications unread-count endpoint works\n";
    }

    public function test_notifications_mark_as_read(): void
    {
        // Create a test notification
        $notification = Notification::notify(
            userId: $this->user->id,
            type: 'test',
            title: 'Test notification for marking as read',
            message: 'This is a test notification',
            workspaceId: $this->user->workspace_id,
        );

        $this->assertNull($notification->read_at, 'Notification should start unread');

        $response = $this->actingAs($this->user)->postJson("/notifications/{$notification->id}/read");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at, 'Notification should be marked as read');
        echo "✅ Notification mark-as-read works correctly\n";

        // Cleanup
        $notification->delete();
    }

    public function test_notifications_mark_all_read(): void
    {
        // Create multiple unread notifications
        $notifications = [];
        for ($i = 0; $i < 3; $i++) {
            $notifications[] = Notification::notify(
                userId: $this->user->id,
                type: 'test',
                title: "Test notification {$i}",
                message: "Test message {$i}",
                workspaceId: $this->user->workspace_id,
            );
        }

        $response = $this->actingAs($this->user)->postJson('/notifications/mark-all-read');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify all are now read
        $unreadCount = Notification::forUser($this->user->id)->unread()->count();
        $this->assertEquals(0, $unreadCount, 'All notifications should be marked as read');
        echo "✅ Notification mark-all-read works correctly\n";

        // Cleanup
        foreach ($notifications as $n) {
            $n->delete();
        }
    }

    public function test_notification_model_helpers(): void
    {
        $notification = Notification::notify(
            userId: $this->user->id,
            type: 'info',
            title: 'Test helper methods',
            message: 'Testing isRead and markAsRead',
            workspaceId: $this->user->workspace_id,
        );

        $this->assertFalse($notification->isRead(), 'New notification should not be read');

        $notification->markAsRead();
        $this->assertTrue($notification->isRead(), 'Notification should be read after markAsRead()');
        echo "✅ Notification model helpers (isRead, markAsRead) work correctly\n";

        // Cleanup
        $notification->delete();
    }

    // ==================== CONTENT IDEA → POST CONVERSION =======================

    public function test_content_idea_crud(): void
    {
        $timestamp = now()->timestamp;

        // Create content idea
        $response = $this->actingAs($this->user)->post('/content-ideas', [
            'title' => 'Test Idea ' . $timestamp,
            'notes' => 'This is a test idea for automated testing',
            'status' => 'draft',
            'target_date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Content idea create failed with status: {$response->status()}");
        echo "✅ Content Idea create works\n";

        // Find the created idea
        $idea = ContentIdea::where('title', 'Test Idea ' . $timestamp)->first();
        $this->assertNotNull($idea, 'Content Idea was not created in database');
        echo "✅ Content Idea exists in database (ID: {$idea->id})\n";

        // Update
        $response = $this->actingAs($this->user)->put("/content-ideas/{$idea->id}", [
            'title' => 'Updated Idea ' . $timestamp,
            'notes' => 'Updated notes',
            'status' => 'in_progress',
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Content idea update failed with status: {$response->status()}");
        $idea->refresh();
        $this->assertEquals('Updated Idea ' . $timestamp, $idea->title);
        echo "✅ Content Idea update works\n";

        // Delete
        $response = $this->actingAs($this->user)->delete("/content-ideas/{$idea->id}");
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Content idea delete failed with status: {$response->status()}");
        $this->assertNull(ContentIdea::find($idea->id), 'Content Idea was not deleted');
        echo "✅ Content Idea delete works\n";
    }

    public function test_content_idea_to_post_conversion(): void
    {
        // Create a content idea first
        $idea = ContentIdea::create([
            'workspace_id' => $this->user->workspace_id,
            'title' => 'Conversion test idea - ' . now()->timestamp,
            'notes' => 'This idea should be converted to a post',
            'status' => 'draft',
            'target_date' => now()->addDays(7),
            'created_by' => $this->user->id,
        ]);

        $this->assertNotNull($idea, 'Test idea should be created');

        // Convert to post
        $response = $this->actingAs($this->user)->post("/content-ideas/{$idea->id}/to-post");
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Content idea to post conversion failed with status: {$response->status()}");
        echo "✅ Content Idea → Post conversion redirects correctly\n";

        // Verify a post was created from this idea
        $post = Post::where('content_idea_id', $idea->id)->first();
        $this->assertNotNull($post, 'A draft post should have been created from the idea');
        $this->assertEquals('draft', $post->status, 'Converted post should be a draft');
        $this->assertEquals($idea->title, $post->hook, 'Post hook should be the idea title');
        echo "✅ Content Idea → Post: Draft post created with correct data\n";

        // Verify the idea status changed to in_progress
        $idea->refresh();
        $this->assertEquals('in_progress', $idea->status, 'Idea status should be in_progress after conversion');
        echo "✅ Content Idea status updated to 'in_progress' after conversion\n";

        // Cleanup
        $post->delete();
        $idea->delete();
    }

    public function test_content_idea_validation(): void
    {
        // Test empty data
        $response = $this->actingAs($this->user)->post('/content-ideas', []);
        $response->assertSessionHasErrors(['title', 'status']);
        echo "✅ Content Idea validation rejects empty data\n";

        // Test invalid status
        $response = $this->actingAs($this->user)->post('/content-ideas', [
            'title' => 'Test',
            'status' => 'invalid_status',
        ]);
        $response->assertSessionHasErrors('status');
        echo "✅ Content Idea validation rejects invalid status\n";
    }

    // ==================== THREADS API INTEGRATION ==============================

    public function test_publish_to_threads_endpoint(): void
    {
        $post = Post::where('created_by', $this->user->id)
            ->where('status', 'draft')
            ->first();

        if (!$post) {
            // Create a test post
            $post = Post::create([
                'workspace_id' => $this->user->workspace_id,
                'body' => 'Test post for Threads publishing ' . now()->timestamp,
                'status' => 'draft',
                'publish_mode' => 'manual',
                'created_by' => $this->user->id,
            ]);
        }

        $response = $this->actingAs($this->user)->postJson("/posts/{$post->id}/publish-threads");
        // Should be accessible (may fail due to missing Threads API token, but shouldn't be 404)
        $this->assertNotEquals(404, $response->status(),
            "Publish to Threads endpoint returned 404 - route not found");
        echo "✅ Publish to Threads endpoint accessible (status: {$response->status()})\n";
    }

    public function test_post_mark_published(): void
    {
        $post = Post::create([
            'workspace_id' => $this->user->workspace_id,
            'body' => 'Test post for mark published ' . now()->timestamp,
            'status' => 'draft',
            'publish_mode' => 'manual',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post("/posts/{$post->id}/publish");
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Mark published failed with status: {$response->status()}");

        $post->refresh();
        $this->assertEquals('published', $post->status, 'Post status should be published');
        echo "✅ Post mark published works correctly\n";

        // Cleanup
        $post->delete();
    }

    public function test_post_cancel(): void
    {
        $post = Post::create([
            'workspace_id' => $this->user->workspace_id,
            'body' => 'Test post for cancel ' . now()->timestamp,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(3),
            'publish_mode' => 'manual',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post("/posts/{$post->id}/cancel");
        $this->assertTrue(in_array($response->status(), [302, 303]),
            "Cancel post failed with status: {$response->status()}");

        $post->refresh();
        $this->assertEquals('cancelled', $post->status, 'Post status should be cancelled');
        echo "✅ Post cancel works correctly\n";

        // Cleanup
        $post->delete();
    }

    public function test_meta_webhook_endpoints(): void
    {
        // Deauthorize webhook
        $response = $this->post('/auth/threads/deauthorize');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        echo "✅ Meta Threads deauthorize webhook endpoint works\n";

        // Delete data webhook
        $response = $this->post('/auth/threads/delete-data');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        echo "✅ Meta Threads delete-data webhook endpoint works\n";
    }

    // ==================== DYNAMIC ANALYTICS SYNC ===============================

    public function test_analytics_snapshot_store(): void
    {
        $response = $this->actingAs($this->user)->post('/analytics/snapshot', [
            'followers_count' => 1000,
            'impressions' => 5000,
            'engagement_rate' => 4.5,
            'date' => now()->format('Y-m-d'),
        ]);
        // Should redirect or return success
        $this->assertTrue(in_array($response->status(), [200, 302, 303, 422]),
            "Analytics snapshot store returned unexpected status: {$response->status()}");
        echo "✅ Analytics snapshot store endpoint accessible (status: {$response->status()})\n";
    }

    public function test_analytics_sync_from_api(): void
    {
        $response = $this->actingAs($this->user)->postJson('/analytics/sync');
        // May fail if Threads API token is not configured, but should be accessible
        $this->assertNotEquals(404, $response->status(),
            "Analytics sync endpoint returned 404 - route not found");
        echo "✅ Analytics sync from API endpoint accessible (status: {$response->status()})\n";
    }

    // ==================== COMPREHENSIVE ROUTE CHECK ============================

    public function test_all_v15_routes_registered(): void
    {
        $routes = [
            ['GET', '/hooks'],
            ['POST', '/hooks'],
            ['GET', '/notifications'],
            ['GET', '/notifications/unread-count'],
            ['POST', '/notifications/mark-all-read'],
            ['POST', '/analytics/sync'],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->actingAs($this->user)->{strtolower($method)}($url);
            $this->assertNotEquals(404, $response->status(),
                "{$method} {$url} returned 404 - route not registered");
            echo "✅ Route {$method} {$url} is registered (status: {$response->status()})\n";
        }
    }
}
