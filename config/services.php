<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TokenRouter AI (OpenAI-compatible)
    |--------------------------------------------------------------------------
    */
    'tokenrouter' => [
        'key' => env('TOKENROUTER_API_KEY'),
        'base_url' => env('TOKENROUTER_BASE_URL', 'https://api.tokenrouter.com/v1'),
        'verify_ssl' => env('TOKENROUTER_VERIFY_SSL', true),
        'text_timeout' => env('TOKENROUTER_TEXT_TIMEOUT', 60),
        'image_timeout' => env('TOKENROUTER_IMAGE_TIMEOUT', 180),
        'fallback_image_model' => env('TOKENROUTER_FALLBACK_IMAGE_MODEL', 'google/gemini-3.1-flash-image-preview'),
        'ready_post_image_prompt_ai' => env('TOKENROUTER_READY_POST_IMAGE_PROMPT_AI', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Threads API
    |--------------------------------------------------------------------------
    */
    'threads' => [
        'app_id' => env('THREADS_APP_ID'),
        'app_secret' => env('THREADS_APP_SECRET'),
        'redirect_uri' => env('THREADS_REDIRECT_URI'),
        'verify_ssl' => env('THREADS_VERIFY_SSL', true),
    ],

];
