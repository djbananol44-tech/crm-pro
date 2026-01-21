<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\LogManagerActivity::class,
        ]);

        // Регистрируем алиас для Meta webhook signature verification
        $middleware->alias([
            'meta.signature' => \App\Http\Middleware\VerifyMetaWebhookSignature::class,
        ]);

        // Исключаем webhook и export из CSRF-проверки
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
            'export/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
