<?php

namespace App\Services;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Сервис для обеспечения идемпотентности обработки webhook событий.
 *
 * Использует двухуровневую проверку:
 * 1. Redis SETNX — быстрая проверка (TTL 24 часа)
 * 2. БД webhook_logs — надёжный backup с unique index
 *
 * Уникальный ключ события формируется из:
 * - message.mid (Message ID от Meta) — если доступен
 * - sha256(entry_id + sender_id + timestamp + message_hash) — fallback
 */
class WebhookIdempotencyService
{
    /**
     * TTL для Redis ключей (24 часа в секундах)
     */
    protected const REDIS_TTL = 86400;

    /**
     * Префикс для Redis ключей
     */
    protected const REDIS_PREFIX = 'webhook:idempotency:';

    /**
     * Проверить, обработано ли событие ранее.
     *
     * @param  string  $source  Источник (meta, telegram)
     * @param  array  $payload  Данные события
     * @return bool true если событие уже обработано (дубликат)
     */
    public function isDuplicate(string $source, array $payload): bool
    {
        $eventKey = $this->generateEventKey($source, $payload);

        if (!$eventKey) {
            // Если не можем сгенерировать ключ — пропускаем проверку
            Log::warning('WebhookIdempotency: Не удалось сгенерировать event_key', [
                'source' => $source,
            ]);

            return false;
        }

        // 1. Быстрая проверка в Redis
        if ($this->existsInRedis($source, $eventKey)) {
            Log::info('WebhookIdempotency: Дубликат обнаружен (Redis)', [
                'source' => $source,
                'event_key' => $eventKey,
            ]);

            return true;
        }

        // 2. Проверка в БД (на случай если Redis был перезапущен)
        if ($this->existsInDatabase($source, $eventKey)) {
            // Восстанавливаем в Redis
            $this->markInRedis($source, $eventKey);

            Log::info('WebhookIdempotency: Дубликат обнаружен (DB)', [
                'source' => $source,
                'event_key' => $eventKey,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Отметить событие как обработанное.
     *
     * @param  string  $source  Источник
     * @param  array  $payload  Данные события
     * @param  string  $ip  IP адрес
     * @return string|null Event key или null если не удалось
     */
    public function markAsProcessed(string $source, array $payload, string $ip): ?string
    {
        $eventKey = $this->generateEventKey($source, $payload);

        if (!$eventKey) {
            return null;
        }

        // 1. Записываем в Redis (быстро)
        $this->markInRedis($source, $eventKey);

        // 2. Записываем в БД (надёжно)
        try {
            WebhookLog::create([
                'source' => $source,
                'event_type' => $this->extractEventType($source, $payload),
                'event_key' => $eventKey,
                'payload' => $payload,
                'ip_address' => $ip,
                'response_code' => 200,
                'processed_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation — уже существует
            if ($this->isUniqueViolation($e)) {
                Log::debug('WebhookIdempotency: Duplicate key в БД', [
                    'event_key' => $eventKey,
                ]);
            } else {
                Log::error('WebhookIdempotency: Ошибка записи в БД', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $eventKey;
    }

    /**
     * Сгенерировать уникальный ключ события.
     */
    public function generateEventKey(string $source, array $payload): ?string
    {
        if ($source === 'meta') {
            return $this->generateMetaEventKey($payload);
        }

        if ($source === 'telegram') {
            return $this->generateTelegramEventKey($payload);
        }

        return null;
    }

    /**
     * Генерация ключа для Meta событий.
     *
     * Приоритет:
     * 1. message.mid — уникальный Message ID от Meta
     * 2. sha256(entry_id + sender_id + timestamp + message_text)
     */
    protected function generateMetaEventKey(array $payload): ?string
    {
        // Ищем message.mid в payload
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $messaging) {
                // Приоритет 1: message.mid
                $mid = $messaging['message']['mid'] ?? null;
                if ($mid) {
                    return hash('sha256', "meta:mid:{$mid}");
                }

                // Приоритет 2: postback
                if (isset($messaging['postback'])) {
                    $senderId = $messaging['sender']['id'] ?? '';
                    $timestamp = $messaging['timestamp'] ?? '';
                    $postbackPayload = $messaging['postback']['payload'] ?? '';

                    return hash('sha256', "meta:postback:{$senderId}:{$timestamp}:{$postbackPayload}");
                }

                // Fallback: комбинация полей
                $senderId = $messaging['sender']['id'] ?? '';
                $timestamp = $messaging['timestamp'] ?? '';
                $entryId = $entry['id'] ?? '';
                $entryTime = $entry['time'] ?? '';

                if ($senderId && $timestamp) {
                    $messageText = $messaging['message']['text'] ?? '';
                    $messageHash = substr(md5($messageText), 0, 8);

                    return hash('sha256', "meta:fallback:{$entryId}:{$entryTime}:{$senderId}:{$timestamp}:{$messageHash}");
                }
            }

            // Instagram changes
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $mid = $value['message']['mid'] ?? $value['mid'] ?? null;
                if ($mid) {
                    return hash('sha256', "meta:ig:mid:{$mid}");
                }
            }
        }

        return null;
    }

    /**
     * Генерация ключа для Telegram событий.
     */
    protected function generateTelegramEventKey(array $payload): ?string
    {
        // update_id — уникальный ID обновления в Telegram
        $updateId = $payload['update_id'] ?? null;
        if ($updateId) {
            return hash('sha256', "telegram:update:{$updateId}");
        }

        // Message ID
        $messageId = $payload['message']['message_id'] ?? $payload['callback_query']['id'] ?? null;
        $chatId = $payload['message']['chat']['id'] ?? $payload['callback_query']['message']['chat']['id'] ?? null;

        if ($messageId && $chatId) {
            return hash('sha256', "telegram:msg:{$chatId}:{$messageId}");
        }

        return null;
    }

    /**
     * Проверить существование в Redis.
     */
    protected function existsInRedis(string $source, string $eventKey): bool
    {
        try {
            $key = self::REDIS_PREFIX.$source.':'.$eventKey;

            return (bool) Redis::exists($key);
        } catch (\Exception $e) {
            // Redis недоступен — продолжаем без него
            Log::warning('WebhookIdempotency: Redis недоступен', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Записать в Redis.
     */
    protected function markInRedis(string $source, string $eventKey): void
    {
        try {
            $key = self::REDIS_PREFIX.$source.':'.$eventKey;
            Redis::setex($key, self::REDIS_TTL, '1');
        } catch (\Exception $e) {
            Log::warning('WebhookIdempotency: Ошибка записи в Redis', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Проверить существование в БД.
     */
    protected function existsInDatabase(string $source, string $eventKey): bool
    {
        try {
            return WebhookLog::where('source', $source)
                ->where('event_key', $eventKey)
                ->exists();
        } catch (\Exception $e) {
            Log::warning('WebhookIdempotency: Ошибка запроса к БД', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Извлечь тип события из payload.
     */
    protected function extractEventType(string $source, array $payload): string
    {
        if ($source === 'meta') {
            $entry = $payload['entry'][0] ?? [];
            if (isset($entry['messaging'])) {
                $messaging = $entry['messaging'][0] ?? [];
                if (isset($messaging['message'])) {
                    return 'message';
                }
                if (isset($messaging['postback'])) {
                    return 'postback';
                }
                if (isset($messaging['delivery'])) {
                    return 'delivery';
                }
                if (isset($messaging['read'])) {
                    return 'read';
                }
            }
            if (isset($entry['changes'])) {
                return 'instagram_'.($entry['changes'][0]['field'] ?? 'unknown');
            }

            return $payload['object'] ?? 'unknown';
        }

        if ($source === 'telegram') {
            if (isset($payload['message'])) {
                return 'message';
            }
            if (isset($payload['callback_query'])) {
                return 'callback_query';
            }
            if (isset($payload['edited_message'])) {
                return 'edited_message';
            }

            return 'unknown';
        }

        return 'unknown';
    }

    /**
     * Проверить, является ли исключение нарушением unique constraint.
     */
    protected function isUniqueViolation(\Exception $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Duplicate entry') ||
               str_contains($message, 'UNIQUE constraint') ||
               str_contains($message, 'unique violation') ||
               str_contains($message, '23505'); // PostgreSQL unique violation code
    }
}
