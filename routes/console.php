<?php

use App\Jobs\GenerateDailyInsightsJob;
use App\Jobs\SendPostReminderJob;
use App\Models\Post;
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

// Prune failed jobs older than 7 days
Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->name('prune-failed-jobs');
