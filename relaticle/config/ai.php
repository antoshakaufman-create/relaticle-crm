<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option determines the default AI provider that is utilized for
    | lead generation and validation. Supported: "hybrid", "gigachat", "yandex"
    |
    */

    'default' => env('AI_PROVIDER', 'yandex'),

    /*
    |--------------------------------------------------------------------------
    | GigaChat Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Sber GigaChat API integration.
    |
    */

    'gigachat' => [
        'api_key' => env('GIGACHAT_API_KEY'),
        'base_url' => env('GIGACHAT_BASE_URL', 'https://gigachat.devices.sberbank.ru/api/v1'),
        'model' => env('GIGACHAT_MODEL', 'GigaChat-Pro'),
        'web_search_enabled' => env('GIGACHAT_WEB_SEARCH', true),
        'timeout' => env('GIGACHAT_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | YandexGPT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for YandexGPT API integration.
    |
    */

    'yandex' => [
        'api_key' => env('YANDEX_GPT_API_KEY'),
        'folder_id' => env('YANDEX_FOLDER_ID'),
        'base_url' => env('YANDEX_GPT_BASE_URL', 'https://llm.api.cloud.yandex.net/foundationModels/v1'),
        'model' => env('YANDEX_GPT_MODEL', 'yandexgpt/latest'),
        'timeout' => env('YANDEX_GPT_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for lead validation scoring and thresholds.
    |
    */

    'validation' => [
        'min_scores' => [
            'verified' => 80,
            'suspicious' => 50,
            'invalid' => 0,
        ],
        'weights' => [
            'email' => 0.3,
            'phone' => 0.2,
            'company' => 0.25,
            'vk' => 0.1,
            'telegram' => 0.05,
            'ai' => 0.1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Russian Sources Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Russian data sources integration.
    |
    */

    'kontur_compass' => [
        'api_key' => env('KONTUR_COMPASS_API_KEY'),
        'enabled' => env('KONTUR_COMPASS_ENABLED', false),
    ],

    'yandex_directory' => [
        'api_key' => env('YANDEX_DIRECTORY_API_KEY'),
        'enabled' => env('YANDEX_DIRECTORY_ENABLED', false),
    ],

    'two_gis' => [
        'api_key' => env('TWO_GIS_API_KEY'),
        'enabled' => env('TWO_GIS_ENABLED', false),
    ],
];

