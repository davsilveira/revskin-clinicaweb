<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more.
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

    /*
    |--------------------------------------------------------------------------
    | Infosimples API
    |--------------------------------------------------------------------------
    |
    | Configuration for Infosimples CPF/CNPJ/CRO validation API.
    | Get your token at: https://infosimples.com
    |
    | To disable this integration completely, set INFOSIMPLES_ENABLED=false
    | in your .env file. This will hide the integration from the UI.
    |
    */
    'infosimples' => [
        'enabled' => env('INFOSIMPLES_ENABLED', true),
        'base_url' => env('INFOSIMPLES_BASE_URL', 'https://api.infosimples.com/api/v2/consultas'),
    ],

];

