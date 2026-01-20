<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Настройки приложения
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'CRM Система'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'ru'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ru'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ru_RU'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_url' => env('APP_PREVIOUS_URL'),

    /*
    |--------------------------------------------------------------------------
    | Автозагрузка сервисов
    |--------------------------------------------------------------------------
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\Filament\AdminPanelProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Алиасы классов
    |--------------------------------------------------------------------------
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
    ])->toArray(),

];
