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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        'api_url'       => env('WHATSAPP_API_URL', 'https://watsybackend.teccube.teccube.ly/api'),
        'api_token'     => env('WHATSAPP_API_TOKEN'),
        'main_session_uuid' => env('WHATSAPP_MAIN_SESSION_ID'),
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),
    ],

    'cart_quotes' => [
        'base_url' => env('CART_API_URL', env('CARTQUOTES_BASE_URL')),
        'token' => env('CART_API_KEY', env('CARTQUOTES_API_KEY')),
        'webhook_secret' => env('CART_WEBHOOK_SECRET', env('CARTQUOTES_WEBHOOK_SECRET')),
        'webhook_secret_previous' => env('CART_WEBHOOK_SECRET_PREVIOUS'),
        'whatsapp_test_number' => env('CARTQUOTES_WHATSAPP_TEST_NUMBER', ''),
        'whatsapp_test_sender_id' => env('CARTQUOTES_WHATSAPP_TEST_SENDER_ID', ''),
        'shein_hosts' => explode(',', env('CARTQUOTES_SHEIN_HOSTS',
            'shein.com,shein.top,shein.co.uk,shein.in,shein.tw,shein.se,shein.com.hk,shein.com.vn,shein.com.mx')),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-pro'),
    ],
];
