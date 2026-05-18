<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    DashboardController,
    PostController,
    ContentPillarController,
    ContentIdeaController,
    ContentPlannerController,
    AiAssistController,
    AnalyticsController,
    HookTemplateController,
    NotificationController,
    ImageStudioController,
    CopywritingController,
};
use App\Http\Controllers\Settings\AiModelConfigController;
use App\Http\Controllers\Settings\GeneralController;
use App\Http\Middleware\EnsureWorkspaceAccess;

require __DIR__ . '/auth.php';

// Redirect root to dashboard
Route::redirect('/', '/dashboard');

// Protected application routes
Route::middleware(['auth', 'verified', EnsureWorkspaceAccess::class])
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        // Content Planner
        Route::get('/content-planner', [ContentPlannerController::class, 'index'])
            ->name('content-planner');
        Route::get('/content-planner/calendar-data', [ContentPlannerController::class, 'calendarData'])
            ->name('content-planner.calendar');

        // Content Pillars
        Route::resource('content-pillars', ContentPillarController::class)
            ->except(['index', 'show', 'create', 'edit']);

        // Content Ideas
        Route::resource('content-ideas', ContentIdeaController::class)
            ->except(['index', 'show', 'create', 'edit']);
        Route::post('content-ideas/{contentIdea}/to-post', [ContentIdeaController::class, 'convertToPost'])
            ->name('content-ideas.to-post');

        // Posts
        Route::resource('posts', PostController::class);
        Route::post('posts/{post}/publish', [PostController::class, 'markPublished'])
            ->name('posts.publish');
        Route::post('posts/{post}/cancel', [PostController::class, 'cancel'])
            ->name('posts.cancel');
        Route::post('posts/{post}/publish-threads', [PostController::class, 'publishToThreads'])
            ->name('posts.publish-threads');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::post('/analytics/snapshot', [AnalyticsController::class, 'storeSnapshot'])->name('analytics.snapshot');
        Route::post('/analytics/sync', [AnalyticsController::class, 'syncFromApi'])->name('analytics.sync');

        // Hook Templates
        Route::get('/hooks', [HookTemplateController::class, 'index'])->name('hooks.index');
        Route::post('/hooks', [HookTemplateController::class, 'store'])->name('hooks.store');
        Route::delete('/hooks/{hookTemplate}', [HookTemplateController::class, 'destroy'])->name('hooks.destroy');
        Route::post('/hooks/{hookTemplate}/save', [HookTemplateController::class, 'save'])->name('hooks.save');
        Route::post('/hooks/{hookTemplate}/use', [HookTemplateController::class, 'use'])->name('hooks.use');
        Route::post('/hooks/{hookTemplate}/score', [HookTemplateController::class, 'score'])->name('hooks.score');
        Route::post('/hooks/generate', [HookTemplateController::class, 'generate'])->name('hooks.generate');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

        // AI Assist
        Route::prefix('ai')->name('ai.')->group(function () {
            Route::post('content-assist', [AiAssistController::class, 'contentAssist'])->name('content-assist');
            Route::post('generate-hook', [AiAssistController::class, 'generateHook'])->name('generate-hook');
            Route::post('generate-hashtags', [AiAssistController::class, 'generateHashtags'])->name('generate-hashtags');
            Route::post('improve-text', [AiAssistController::class, 'improveText'])->name('improve-text');
            Route::post('generate-ideas', [AiAssistController::class, 'generateIdeas'])->name('generate-ideas');
        });

        // Image Studio
        Route::prefix('image-studio')->name('image-studio.')->group(function () {
            Route::get('/', [ImageStudioController::class, 'index'])->name('index');
            Route::post('/poster', [ImageStudioController::class, 'poster'])->name('poster');
            Route::get('/jobs/{media}', [ImageStudioController::class, 'jobStatus'])->name('jobs.show');
            Route::post('/generate', [ImageStudioController::class, 'generate'])->name('generate');
            Route::post('/edit', [ImageStudioController::class, 'edit'])->name('edit');
            Route::post('/generate-chat', [ImageStudioController::class, 'generateFromChat'])->name('generate-chat');
            Route::post('/generate-reference', [ImageStudioController::class, 'generateFromReference'])->name('generate-reference');
            Route::post('/{media}/favorite', [ImageStudioController::class, 'toggleFavorite'])->name('favorite');
            Route::delete('/{media}', [ImageStudioController::class, 'destroy'])->name('destroy');
        });

        // Copywriting AI
        Route::prefix('copywriting')->name('copywriting.')->group(function () {
            Route::get('/', [CopywritingController::class, 'index'])->name('index');
            Route::post('/generate-post', [CopywritingController::class, 'generatePost'])->name('generate-post');
            Route::post('/generate-thread', [CopywritingController::class, 'generateThread'])->name('generate-thread');
            Route::post('/generate-variations', [CopywritingController::class, 'generateVariations'])->name('generate-variations');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('general', [GeneralController::class, 'index'])->name('general');
            Route::put('general', [GeneralController::class, 'update'])->name('general.update');

            // AI Models — admin only
            Route::middleware('admin')->group(function () {
                Route::get('ai-models', [AiModelConfigController::class, 'index'])->name('ai-models');
                Route::put('ai-models/{config}', [AiModelConfigController::class, 'update'])->name('ai-models.update');
            });
        });
    });

// Meta Threads Webhook Dummy Callbacks
Route::any('/auth/threads/deauthorize', function () {
    return response()->json(['success' => true]);
});

Route::any('/auth/threads/delete-data', function () {
    return response()->json(['success' => true]);
});
