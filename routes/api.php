<?php

use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Маршруты для API. Эти маршруты загружаются RouteServiceProvider
| и находятся в группе middleware "api".
|
*/

// Meta Webhooks (исключены из CSRF)
Route::prefix('webhooks')->group(function () {
    // Верификация webhook (GET запрос от Meta)
    Route::get('/meta', [MetaWebhookController::class, 'verify'])
        ->name('webhooks.meta.verify');

    // Обработка событий (POST запрос от Meta)
    Route::post('/meta', [MetaWebhookController::class, 'handle'])
        ->name('webhooks.meta.handle');

    // Telegram Bot Webhook
    Route::post('/telegram', [TelegramController::class, 'webhook'])
        ->name('webhooks.telegram');
});
