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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'live_model' => env('GEMINI_LIVE_MODEL', 'gemini-3.1-flash-live-preview'),
        'transcription_model' => env('GEMINI_TRANSCRIPTION_MODEL', 'gemini-3.5-transcribe-live'),
        'extraction_model' => env('GEMINI_EXTRACTION_MODEL', 'gemini-3.7-flash'),
        'extraction_fallback_model' => env('GEMINI_EXTRACTION_FALLBACK_MODEL', 'gemini-2.5-flash'),
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.7-flash'),
    ],

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

];
