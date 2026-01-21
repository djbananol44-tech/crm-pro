<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMetaMessage;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    public function __construct(
        protected WebhookIdempotencyService $idempotency
    ) {}

    /**
     * Верификация Webhook (GET запрос от Meta).
     *
     * Meta отправляет GET запрос для подтверждения владения endpoint.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('MetaWebhook: Получен запрос верификации', [
            'mode' => $mode,
            'token_received' => !empty($token),
            'challenge' => $challenge,
        ]);

        SystemLog::meta('info', 'Запрос верификации webhook', [
            'mode' => $mode,
            'ip' => $request->ip(),
        ]);

        // Получаем токен верификации из настроек
        $verifyToken = Setting::get('meta_webhook_verify_token')
            ?: config('services.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('MetaWebhook: Верификация успешна');
            SystemLog::meta('info', 'Webhook верификация успешна');

            return response($challenge, 200);
        }

        Log::warning('MetaWebhook: Верификация не пройдена', [
            'expected_token' => substr($verifyToken ?? '', 0, 10).'...',
            'received_token' => substr($token ?? '', 0, 10).'...',
        ]);

        SystemLog::meta('warning', 'Webhook верификация отклонена');

        return response('Forbidden', 403);
    }

    /**
     * Обработка входящих событий (POST запрос от Meta).
     *
     * Сообщения сразу отправляются в Redis очередь для мгновенной обработки.
     * Идемпотентность обеспечивается через WebhookIdempotencyService.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $startTime = microtime(true);

        Log::info('MetaWebhook: Получен webhook', [
            'object' => $payload['object'] ?? 'unknown',
            'entries_count' => count($payload['entry'] ?? []),
        ]);

        try {
            // ─────────────────────────────────────────────────────────
            // IDEMPOTENCY CHECK: Проверяем дубликат ПЕРЕД обработкой
            // ─────────────────────────────────────────────────────────
            if ($this->idempotency->isDuplicate('meta', $payload)) {
                Log::info('MetaWebhook: Дубликат события, пропускаем', [
                    'event_key' => $this->idempotency->generateEventKey('meta', $payload),
                ]);

                SystemLog::meta('info', 'Дубликат webhook пропущен');

                // Возвращаем 200 чтобы Meta не повторял
                return response('DUPLICATE_IGNORED', 200);
            }

            // Проверяем, что это событие от страницы
            if (($payload['object'] ?? '') !== 'page') {
                Log::warning('MetaWebhook: Неизвестный тип объекта', [
                    'object' => $payload['object'] ?? null,
                ]);

                return response('OK', 200);
            }

            // ─────────────────────────────────────────────────────────
            // MARK AS PROCESSED: Отмечаем событие ПЕРЕД dispatch
            // Это предотвращает race condition при параллельных запросах
            // ─────────────────────────────────────────────────────────
            $eventKey = $this->idempotency->markAsProcessed('meta', $payload, $request->ip());

            // Обрабатываем каждую запись — отправляем в Redis для мгновенной обработки
            $dispatchedCount = 0;
            foreach ($payload['entry'] ?? [] as $entry) {
                $dispatchedCount += $this->processEntry($entry);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            SystemLog::meta('info', 'Webhook обработан', [
                'entries' => count($payload['entry'] ?? []),
                'dispatched' => $dispatchedCount,
                'event_key' => $eventKey,
                'duration_ms' => $duration,
            ]);

            // Meta требует быстрый ответ 200
            return response('EVENT_RECEIVED', 200);

        } catch (\Exception $e) {
            Log::error('MetaWebhook: Ошибка обработки', ['error' => $e->getMessage()]);

            SystemLog::meta('error', 'Ошибка обработки webhook', [
                'error' => $e->getMessage(),
            ]);

            // Все равно возвращаем 200, чтобы Meta не повторял запрос
            return response('ERROR_LOGGED', 200);
        }
    }

    /**
     * Обработка одной записи из webhook.
     *
     * @return int Количество dispatched jobs
     */
    protected function processEntry(array $entry): int
    {
        $pageId = $entry['id'] ?? null;
        $time = $entry['time'] ?? null;
        $dispatched = 0;

        Log::info('MetaWebhook: Обработка записи', [
            'page_id' => $pageId,
            'time' => $time,
        ]);

        // Обрабатываем события сообщений (Messenger)
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messagingEvent) {
                $dispatched += $this->processMessagingEvent($messagingEvent, $entry);
            }
        }

        // Обрабатываем изменения (changes) — для Instagram
        if (isset($entry['changes'])) {
            foreach ($entry['changes'] as $change) {
                $dispatched += $this->processChange($change);
            }
        }

        return $dispatched;
    }

    /**
     * Обработка события сообщения (Messenger).
     * Мгновенно отправляет в Redis Queue.
     *
     * @return int 1 если job dispatched, 0 если нет
     */
    protected function processMessagingEvent(array $event, array $entry): int
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $mid = $event['message']['mid'] ?? null;

        Log::info('MetaWebhook: Событие сообщения', [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'mid' => $mid,
            'has_message' => isset($event['message']),
        ]);

        // Если это новое сообщение от пользователя (не от страницы)
        if (isset($event['message']) && $senderId !== $recipientId) {
            ProcessMetaMessage::dispatch($entry, 'messenger')
                ->onQueue('meta')
                ->onConnection('redis');

            Log::info('MetaWebhook: Сообщение отправлено в очередь', [
                'sender_id' => $senderId,
                'mid' => $mid,
                'queue' => 'meta',
            ]);

            return 1;
        }

        // Postback также обрабатываем
        if (isset($event['postback']) && $senderId) {
            ProcessMetaMessage::dispatch($entry, 'messenger')
                ->onQueue('meta')
                ->onConnection('redis');

            return 1;
        }

        return 0;
    }

    /**
     * Обработка изменений (Instagram).
     *
     * @return int 1 если job dispatched, 0 если нет
     */
    protected function processChange(array $change): int
    {
        $field = $change['field'] ?? null;
        $value = $change['value'] ?? [];

        Log::info('MetaWebhook: Изменение', ['field' => $field]);

        // Обработка сообщений Instagram
        if ($field === 'messages') {
            ProcessMetaMessage::dispatch(['messaging' => [$value]], 'instagram')
                ->onQueue('meta')
                ->onConnection('redis');

            Log::info('MetaWebhook: Instagram сообщение отправлено в очередь');

            return 1;
        }

        return 0;
    }
}
