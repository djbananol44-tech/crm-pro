<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\SystemLog;
use App\Observers\ContactObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация любых сервисов приложения.
     */
    public function register(): void
    {
        //
    }

    /**
     * Загрузка любых сервисов приложения.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Observers
        Contact::observe(ContactObserver::class);
    }

    /**
     * Настройка Rate Limiting для API.
     *
     * Webhooks: высокий лимит для Meta bursts (300/min)
     * API: стандартный лимит (60/min)
     */
    protected function configureRateLimiting(): void
    {
        // ─────────────────────────────────────────────────────────────
        // 🔗 Webhook Rate Limiter (Meta, Telegram)
        // ─────────────────────────────────────────────────────────────
        // Высокий лимит: Meta может слать bursts при активных диалогах
        // 300 запросов в минуту на IP — достаточно для активного бизнеса
        //
        RateLimiter::for('webhook', function (Request $request) {
            $ip = $request->ip();
            $key = 'webhook:'.$ip;

            return Limit::perMinute(300)
                ->by($key)
                ->response(function (Request $request, array $headers) use ($ip) {
                    // Логируем ОДИН раз в минуту (избегаем flood логов)
                    $this->logRateLimitExceeded('webhook', $ip, $request);

                    return response()->json([
                        'error' => 'Too Many Requests',
                        'message' => 'Rate limit exceeded. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // ─────────────────────────────────────────────────────────────
        // 🌐 API Rate Limiter (стандартный)
        // ─────────────────────────────────────────────────────────────
        // 60 запросов в минуту — стандарт для REST API
        //
        RateLimiter::for('api', function (Request $request) {
            $ip = $request->ip();
            $userId = $request->user()?->id;

            // Для авторизованных пользователей — по user_id
            // Для гостей — по IP
            $key = $userId ? 'api:user:'.$userId : 'api:ip:'.$ip;

            return Limit::perMinute(60)
                ->by($key)
                ->response(function (Request $request, array $headers) use ($ip, $userId) {
                    $this->logRateLimitExceeded('api', $ip, $request, $userId);

                    return response()->json([
                        'error' => 'Too Many Requests',
                        'message' => 'Rate limit exceeded.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // ─────────────────────────────────────────────────────────────
        // 🧪 Test Endpoints Rate Limiter
        // ─────────────────────────────────────────────────────────────
        // Защита тестовых эндпоинтов от злоупотреблений
        //
        RateLimiter::for('test', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }

    /**
     * Логирование превышения лимита с дедупликацией.
     *
     * Используем cache чтобы логировать не чаще 1 раза в минуту на IP.
     */
    protected function logRateLimitExceeded(
        string $limiter,
        string $ip,
        Request $request,
        ?int $userId = null
    ): void {
        $cacheKey = "rate_limit_logged:{$limiter}:{$ip}";

        // Логируем только если не логировали в последнюю минуту
        if (!cache()->has($cacheKey)) {
            cache()->put($cacheKey, true, now()->addMinute());

            $context = [
                'limiter' => $limiter,
                'ip' => $ip,
                'path' => $request->path(),
                'user_id' => $userId,
                'user_agent' => substr($request->userAgent() ?? '', 0, 100),
            ];

            Log::warning("RateLimit: Превышен лимит [{$limiter}]", $context);

            // Записываем в system_logs для мониторинга
            try {
                SystemLog::create([
                    'source' => 'rate_limiter',
                    'level' => 'warning',
                    'message' => "Превышен лимит {$limiter} для IP {$ip}",
                    'context' => $context,
                ]);
            } catch (\Exception $e) {
                // Игнорируем ошибки записи
            }
        }
    }
}
