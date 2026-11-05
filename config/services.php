<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'messenger' => [
        // Token strony i odbiorca są w panelu administratora (tabela settings).
        'graph_version' => env('MESSENGER_GRAPH_VERSION', 'v23.0'),
        'ca_bundle' => env('MESSENGER_CA_BUNDLE', env('FONT_AWESOME_CA_BUNDLE')),
    ],

    'font_awesome' => [
        // Token API z konta fontawesome.com. Bez niego dostępne są tylko darmowe ikony.
        'api_token' => env('FONT_AWESOME_API_TOKEN'),
        // Pakiet certyfikatów CA, gdy PHP nie ma ustawionego curl.cainfo.
        'ca_bundle' => env('FONT_AWESOME_CA_BUNDLE'),
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
