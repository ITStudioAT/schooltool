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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'healthcheck' => [
        'scheduler_ping_url' => env('HEALTHCHECK_SCHEDULER_PING_URL'),
        'worker_ping_url' => env('HEALTHCHECK_WORKER_PING_URL'),
    ],

    'cloudways' => [
        'deployment' => [
            'base_url' => env('CLOUDWAYS_API_BASE_URL', 'https://api.cloudways.com/api/v2'),
            'access_token' => env('CLOUDWAYS_API_ACCESS_TOKEN'),
            'server_id' => env('CLOUDWAYS_SERVER_ID'),
            'app_id' => env('CLOUDWAYS_APP_ID'),
            'branch' => env('CLOUDWAYS_DEPLOY_BRANCH', 'main'),
            'deploy_path' => env('CLOUDWAYS_DEPLOY_PATH'),
            'connect_timeout' => (int) env('CLOUDWAYS_API_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('CLOUDWAYS_API_TIMEOUT', 20),
            'operation_timeout' => (int) env('CLOUDWAYS_DEPLOY_OPERATION_TIMEOUT', 600),
            'poll_interval' => (int) env('CLOUDWAYS_DEPLOY_POLL_INTERVAL', 3),
        ],
    ],

];
