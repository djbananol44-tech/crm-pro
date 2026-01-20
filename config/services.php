<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Сторонние сервисы
    |--------------------------------------------------------------------------
    |
    | Этот файл предназначен для хранения учетных данных сторонних сервисов,
    | таких как Mailgun, Postmark, AWS и т.д. Это обеспечивает единое место
    | для хранения информации о сторонних интеграциях.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Business Suite / Facebook API
    |--------------------------------------------------------------------------
    |
    | Настройки для интеграции с Meta Graph API.
    | PAGE_ID - идентификатор страницы Facebook
    | ACCESS_TOKEN - долгосрочный токен доступа страницы
    |
    */

    'meta' => [
        'page_id' => env('META_PAGE_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    ],

];
