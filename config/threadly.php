<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Post Reminder Offset (minutes)
    |--------------------------------------------------------------------------
    |
    | How many minutes before a scheduled post to send the email reminder.
    |
    */
    'reminder_offset_minutes' => (int) env('POST_REMINDER_OFFSET_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Daily Insights Generation Time
    |--------------------------------------------------------------------------
    |
    | The time at which daily AI insights are generated (24h format).
    |
    */
    'insights_time' => env('DAILY_INSIGHTS_TIME', '06:00'),
];
