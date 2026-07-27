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

    'whatsapp_cloud' => [
        'token'                   => env('WHATSAPP_CLOUD_TOKEN', ''),
        'phone_number_id'         => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID', ''),
        'waba_id'                 => env('WHATSAPP_CLOUD_WABA_ID', ''),
        'api_version'             => env('WHATSAPP_CLOUD_API_VERSION', 'v22.0'),
        'management_number'       => env('WHATSAPP_MANAGEMENT_NUMBER', ''),
        'booking_template_name'   => env('WHATSAPP_BOOKING_TEMPLATE_NAME', ''),
        'booking_template_lang'   => env('WHATSAPP_BOOKING_TEMPLATE_LANG', 'en'),
        'booking_alert_number'    => env('WHATSAPP_BOOKING_ALERT_NUMBER', '96878622990'),
        'admin_numbers'           => array_filter(array_map('trim', explode(',', env('WHATSAPP_ADMIN_NUMBERS', '')))),
        'relay_secret'            => env('WHATSAPP_RELAY_SECRET', ''),
    ],

    'firebase' => [
        'credentials_path'   => env('FIREBASE_CREDENTIALS_PATH', ''),
        'storage_bucket'     => env('FIREBASE_STORAGE_BUCKET', ''),
        'project_id'         => env('FIREBASE_PROJECT_ID', ''),
        'whatsapp_chat_slug' => env('FIREBASE_WHATSAPP_CHAT_SLUG', 'alseef'),
    ],

];
