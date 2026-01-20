<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMetaConversations;
use App\Jobs\SyncSingleConversation;
use App\Models\Setting;
use App\Models\User;
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

        // Получаем токен верификации из настроек
        $verifyToken = Setting::get('meta_webhook_verify_token') 
            ?: config('services.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('MetaWebhook: Верификация успешна');
            return response($challenge, 200);
        }

        Log::warning('MetaWebhook: Верификация не пройдена', [
            'expected_token' => substr($verifyToken, 0, 10) . '...',
            'received_token' => substr($token, 0, 10) . '...',
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Обработка входящих событий (POST запрос от Meta).
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();

        Log::info('MetaWebhook: Получен webhook', [
            'object' => $payload['object'] ?? 'unknown',
            'entries_count' => count($payload['entry'] ?? []),
        ]);

        // Проверяем, что это событие от страницы
        if (($payload['object'] ?? '') !== 'page') {
            Log::warning('MetaWebhook: Неизвестный тип объекта', [
                'object' => $payload['object'] ?? null,
            ]);
            return response('OK', 200);
        }

        // Обрабатываем каждую запись
        foreach ($payload['entry'] ?? [] as $entry) {
            $this->processEntry($entry);
        }

        // Meta требует быстрый ответ 200
        return response('EVENT_RECEIVED', 200);
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

        // Обрабатываем события сообщений
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messagingEvent) {
                $this->processMessagingEvent($messagingEvent);
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
     */
    protected function processMessagingEvent(array $event): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;

        Log::info('MetaWebhook: Событие сообщения', [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'timestamp' => $timestamp,
            'has_message' => isset($event['message']),
            'has_postback' => isset($event['postback']),
        ]);

        // Если это новое сообщение
        if (isset($event['message'])) {
            $messageId = $event['message']['mid'] ?? null;
            $messageText = $event['message']['text'] ?? null;

            Log::info('MetaWebhook: Новое сообщение', [
                'message_id' => $messageId,
                'text_preview' => $messageText ? substr($messageText, 0, 50) . '...' : null,
                'sender_id' => $senderId,
            ]);

            // Запускаем синхронизацию для этого отправителя
            if ($senderId) {
                $this->dispatchSyncForSender($senderId, 'messenger');
            }
        }

        // Если это postback (нажатие кнопки)
        if (isset($event['postback'])) {
            Log::info('MetaWebhook: Postback событие', [
                'payload' => $event['postback']['payload'] ?? null,
                'sender_id' => $senderId,
            ]);

            if ($senderId) {
                $this->dispatchSyncForSender($senderId, 'messenger');
            }
        }
    }

    /**
     * Обработка изменений (Instagram).
     */
    protected function processChange(array $change): void
    {
        $field = $change['field'] ?? null;
        $value = $change['value'] ?? [];

        Log::info('MetaWebhook: Изменение', [
            'field' => $field,
        ]);

        // Обработка сообщений Instagram
        if ($field === 'messages') {
            $senderId = $value['sender']['id'] ?? null;

            Log::info('MetaWebhook: Instagram сообщение', [
                'sender_id' => $senderId,
            ]);

            if ($senderId) {
                $this->dispatchSyncForSender($senderId, 'instagram');
            }
        }
    }

    /**
     * Запустить синхронизацию для конкретного отправителя.
     */
    protected function dispatchSyncForSender(string $senderId, string $platform): void
    {
        Log::info('MetaWebhook: Запуск синхронизации', [
            'sender_id' => $senderId,
            'platform' => $platform,
        ]);

        // Запускаем Job для синхронизации конкретной беседы
        SyncSingleConversation::dispatch($senderId, $platform);
    }
}
