<?php

use App\Jobs\GenerateDailyInsightsJob;
use App\Jobs\PublishToThreadsJob;
use App\Jobs\SendPostReminderJob;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Check for upcoming posts needing reminders every 5 minutes
// This catches any posts that may not have had a reminder dispatched at creation time
Schedule::call(function () {
    $offsetMinutes = (int) config('threadly.reminder_offset_minutes', 30);

    $posts = Post::withoutGlobalScopes()
        ->where('status', 'scheduled')
        ->where('publish_mode', 'manual')
        ->whereNull('reminder_sent_at')
        ->whereBetween('scheduled_at', [
            now()->addMinutes($offsetMinutes - 3),
            now()->addMinutes($offsetMinutes + 3),
        ])
        ->get();

    foreach ($posts as $post) {
        SendPostReminderJob::dispatch($post);
    }
})
    ->everyFiveMinutes()
    ->name('check-post-reminders')
    ->withoutOverlapping();

// Generate daily AI insights at 7 AM (workspace timezone handled in job)
Schedule::job(new GenerateDailyInsightsJob)
    ->dailyAt('07:00')
    ->name('generate-daily-insights')
    ->withoutOverlapping();

// Auto-publish scheduled posts via Threads API when scheduled_at arrives
Schedule::call(function () {
    $posts = Post::withoutGlobalScopes()
        ->where('status', 'scheduled')
        ->where('publish_mode', 'auto')
        ->where('scheduled_at', '<=', now())
        ->get();

    foreach ($posts as $post) {
        // Verify workspace has access token before dispatching
        $workspace = Workspace::find($post->workspace_id);
        if ($workspace && !empty($workspace->threads_access_token)) {
            PublishToThreadsJob::dispatch($post);
        }
    }
})
    ->everyMinute()
    ->name('auto-publish-threads')
    ->withoutOverlapping();

// Prune failed jobs older than 7 days
Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->name('prune-failed-jobs');

if (filter_var(env('AI_IMAGE_QUEUE_ENABLED', false), FILTER_VALIDATE_BOOL)) {
    Schedule::command('queue:work database --queue=ai --stop-when-empty --tries=2 --timeout=210')
        ->everyMinute()
        ->name('process-ai-image-queue')
        ->withoutOverlapping();
}
