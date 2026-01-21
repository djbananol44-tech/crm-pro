<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\WebhookLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для верификации подписи Meta Webhook (X-Hub-Signature-256).
 *
 * ОБОСНОВАНИЕ размещения в Middleware (а не в Controller):
 *
 * 1. Separation of Concerns (SRP) — проверка подписи это security concern,
 *    не бизнес-логика обработки сообщений.
 *
 * 2. Fail Fast — если подпись неверна, контроллер НЕ вызывается,
 *    никакие jobs не dispatch'атся, записи не создаются.
 *
 * 3. Reusability — middleware можно применить к другим вебхукам.
 *
 * 4. Clean Controller — контроллер занимается только бизнес-логикой.
 *
 * 5. Centralized Logging — отклонённые запросы логируются в одном месте.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/webhooks#verification
 */
class VerifyMetaWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // GET запросы (верификация) пропускаем — у них нет подписи
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $signature = $request->header('X-Hub-Signature-256');
        $rawBody = $request->getContent();

        // Если подпись отсутствует
        if (empty($signature)) {
            return $this->rejectRequest(
                $request,
                'Отсутствует заголовок X-Hub-Signature-256',
                'missing_signature'
            );
        }

        // Получаем секрет из настроек или конфига
        $appSecret = $this->getAppSecret();

        if (empty($appSecret)) {
            // Если секрет не настроен — логируем warning, но пропускаем
            // (для обратной совместимости при первоначальной настройке)
            Log::warning('VerifyMetaWebhookSignature: App Secret не настроен, проверка подписи пропущена');
            SystemLog::meta('warning', 'Meta App Secret не настроен — webhook не защищён');

            return $next($request);
        }

        // Вычисляем ожидаемую подпись
        $expectedSignature = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        // Безопасное сравнение (защита от timing attacks)
        if (!hash_equals($expectedSignature, $signature)) {
            return $this->rejectRequest(
                $request,
                'Неверная подпись X-Hub-Signature-256',
                'invalid_signature',
                [
                    'expected_prefix' => substr($expectedSignature, 0, 20).'...',
                    'received_prefix' => substr($signature, 0, 20).'...',
                ]
            );
        }

        Log::debug('VerifyMetaWebhookSignature: Подпись верифицирована');

        return $next($request);
    }

    /**
     * Получить App Secret из настроек или конфига.
     */
    protected function getAppSecret(): ?string
    {
        // Приоритет: БД settings -> config/services.php -> env
        return Setting::get('meta_app_secret')
            ?: config('services.meta.app_secret')
            ?: env('META_APP_SECRET');
    }

    /**
     * Отклонить запрос с логированием.
     */
    protected function rejectRequest(
        Request $request,
        string $reason,
        string $code,
        array $context = []
    ): Response {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // Логируем в Laravel log
        Log::warning("VerifyMetaWebhookSignature: {$reason}", array_merge([
            'ip' => $ip,
            'user_agent' => $userAgent,
            'code' => $code,
        ], $context));

        // Логируем в system_logs
        SystemLog::meta('warning', "Webhook отклонён: {$reason}", array_merge([
            'ip' => $ip,
            'code' => $code,
        ], $context));

        // Логируем в webhook_logs (если таблица существует)
        try {
            WebhookLog::create([
                'source' => 'meta',
                'event_type' => 'signature_verification_failed',
                'payload' => [
                    'reason' => $reason,
                    'code' => $code,
                    'body_preview' => substr($request->getContent(), 0, 500),
                ],
                'ip_address' => $ip,
                'response_code' => 403,
                'response_body' => 'Forbidden: Invalid signature',
                'processed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Игнорируем ошибки записи в webhook_logs
        }

        return response()->json([
            'error' => 'Forbidden',
            'code' => $code,
            'message' => $reason,
        ], 403);
    }
}
