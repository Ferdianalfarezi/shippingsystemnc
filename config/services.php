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

    'auto_print' => [
        'token' => env('AUTO_PRINT_TOKEN'),

        // URL aplikasi yang dibuka Chrome headless (dari sisi PC yang menjalankan scheduler)
        'base_url' => env('AUTO_PRINT_BASE_URL', 'http://127.0.0.1:8000'),

        'chrome_path' => env('AUTO_PRINT_CHROME_PATH', 'C:\Program Files\Google\Chrome\Application\chrome.exe'),
        'sumatra_path' => env('AUTO_PRINT_SUMATRA_PATH', 'C:\SumatraPDF\SumatraPDF.exe'),

        // noscale | fit | shrink (bisa ditambah, misal "noscale,paper=A4")
        'sumatra_settings' => env('AUTO_PRINT_SUMATRA_SETTINGS', 'noscale'),

        // berapa hari file PDF hasil auto print disimpan
        'keep_pdf_days' => (int) env('AUTO_PRINT_KEEP_PDF_DAYS', 7),
    ],

];