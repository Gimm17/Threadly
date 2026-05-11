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

        // Posts
        Route::resource('posts', PostController::class);
        Route::post('posts/{post}/publish', [PostController::class, 'markPublished'])
            ->name('posts.publish');
        Route::post('posts/{post}/cancel', [PostController::class, 'cancel'])
            ->name('posts.cancel');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::post('/analytics/snapshot', [AnalyticsController::class, 'storeSnapshot'])->name('analytics.snapshot');

        // AI Assist
        Route::prefix('ai')->name('ai.')->group(function () {
            Route::post('generate-hook', [AiAssistController::class, 'generateHook'])->name('generate-hook');
            Route::post('improve-text', [AiAssistController::class, 'improveText'])->name('improve-text');
            Route::post('generate-ideas', [AiAssistController::class, 'generateIdeas'])->name('generate-ideas');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('general', [GeneralController::class, 'index'])->name('general');
            Route::put('general', [GeneralController::class, 'update'])->name('general.update');
            Route::get('ai-models', [AiModelConfigController::class, 'index'])->name('ai-models');
            Route::put('ai-models/{config}', [AiModelConfigController::class, 'update'])->name('ai-models.update');
        });
    });

// Meta Threads Webhook Dummy Callbacks
Route::any('/auth/threads/deauthorize', function () {
    return response()->json(['success' => true]);
});

Route::any('/auth/threads/delete-data', function () {
    return response()->json(['success' => true]);
});
