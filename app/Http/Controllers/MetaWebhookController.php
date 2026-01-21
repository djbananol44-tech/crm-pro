<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMetaMessage;
use App\Jobs\SyncMetaConversations;
use App\Jobs\SyncSingleConversation;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\WebhookLog;
use App\Notifications\MetaApiErrorNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
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
            'expected_token' => substr($verifyToken ?? '', 0, 10) . '...',
            'received_token' => substr($token ?? '', 0, 10) . '...',
        ]);
        
        SystemLog::meta('warning', 'Webhook верификация отклонена');

        return response('Forbidden', 403);
    }

    /**
     * Обработка входящих событий (POST запрос от Meta).
     * 
     * Сообщения сразу отправляются в Redis очередь для мгновенной обработки.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $startTime = microtime(true);
        
        // Логируем входящий вебхук
        $webhookLog = null;
        try {
            $webhookLog = WebhookLog::logIncoming(
                source: 'meta',
                eventType: $payload['object'] ?? 'unknown',
                payload: $payload,
                ip: $request->ip()
            );
        } catch (\Exception $e) {
            // Продолжаем без записи в БД, если таблица не существует
        }

        Log::info('MetaWebhook: Получен webhook', [
            'object' => $payload['object'] ?? 'unknown',
            'entries_count' => count($payload['entry'] ?? []),
        ]);

        try {
            // Проверяем, что это событие от страницы
            if (($payload['object'] ?? '') !== 'page') {
                Log::warning('MetaWebhook: Неизвестный тип объекта', [
                    'object' => $payload['object'] ?? null,
                ]);
                $webhookLog?->markProcessed(200, 'Ignored: not a page event');
                return response('OK', 200);
            }

            // Обрабатываем каждую запись — отправляем в Redis для мгновенной обработки
            foreach ($payload['entry'] ?? [] as $entry) {
                $this->processEntry($entry);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $webhookLog?->markProcessed(200, "EVENT_RECEIVED in {$duration}ms");
            
            SystemLog::meta('info', 'Webhook обработан', [
                'entries' => count($payload['entry'] ?? []),
                'duration_ms' => $duration,
            ]);
            
            // Meta требует быстрый ответ 200
            return response('EVENT_RECEIVED', 200);
            
        } catch (\Exception $e) {
            Log::error('MetaWebhook: Ошибка обработки', ['error' => $e->getMessage()]);
            $webhookLog?->markProcessed(500, null, $e->getMessage());
            
            SystemLog::meta('error', 'Ошибка обработки webhook', [
                'error' => $e->getMessage(),
            ]);
            
            // Все равно возвращаем 200, чтобы Meta не повторял запрос
            return response('ERROR_LOGGED', 200);
        }
    }

    /**
     * Обработка одной записи из webhook.
     */
    protected function processEntry(array $entry): void
    {
        $pageId = $entry['id'] ?? null;
        $time = $entry['time'] ?? null;

        Log::info('MetaWebhook: Обработка записи', [
            'page_id' => $pageId,
            'time' => $time,
        ]);

        // Обрабатываем события сообщений (Messenger)
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messagingEvent) {
                $this->processMessagingEvent($messagingEvent, $entry);
            }
        }

        // Обрабатываем изменения (changes) — для Instagram
        if (isset($entry['changes'])) {
            foreach ($entry['changes'] as $change) {
                $this->processChange($change);
            }
        }
    }

    /**
     * Обработка события сообщения (Messenger).
     * Мгновенно отправляет в Redis Queue.
     */
    protected function processMessagingEvent(array $event, array $entry): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;

        Log::info('MetaWebhook: Событие сообщения', [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'has_message' => isset($event['message']),
        ]);

        // Если это новое сообщение от пользователя (не от страницы)
        if (isset($event['message']) && $senderId !== $recipientId) {
            // Мгновенная отправка в Redis Queue
            ProcessMetaMessage::dispatch($entry, 'messenger')
                ->onQueue('meta')
                ->onConnection('redis');

            Log::info('MetaWebhook: Сообщение отправлено в очередь', [
                'sender_id' => $senderId,
                'queue' => 'meta',
            ]);
        }

        // Postback также обрабатываем
        if (isset($event['postback']) && $senderId) {
            ProcessMetaMessage::dispatch($entry, 'messenger')
                ->onQueue('meta')
                ->onConnection('redis');
        }
    }

    /**
     * Обработка изменений (Instagram).
     */
    protected function processChange(array $change): void
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
        }
    }
}
