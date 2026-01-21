<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'source',
        'event_type',
        'event_key',
        'payload',
        'response_code',
        'response_body',
        'ip_address',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Создать лог входящего вебхука
     */
    public static function logIncoming(string $source, string $eventType, array $payload, string $ip): self
    {
        return self::create([
            'source' => $source,
            'event_type' => $eventType,
            'payload' => $payload,
            'ip_address' => $ip,
        ]);
    }

    /**
     * Обновить результат обработки
     */
    public function markProcessed(int $responseCode, ?string $responseBody = null, ?string $error = null): void
    {
        $this->update([
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'processed_at' => now(),
            'error_message' => $error,
        ]);
    }

    /**
     * Scope для фильтрации по источнику
     */
    public function scopeFromMeta($query)
    {
        return $query->where('source', 'meta');
    }

    public function scopeFromTelegram($query)
    {
        return $query->where('source', 'telegram');
    }

    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error_message');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('response_code', '>=', 200)->where('response_code', '<', 300);
    }

    /**
     * Проверить, существует ли событие с данным ключом
     */
    public static function eventExists(string $source, string $eventKey): bool
    {
        return self::where('source', $source)
            ->where('event_key', $eventKey)
            ->exists();
    }

    /**
     * Статус как badge
     */
    public function getStatusBadgeAttribute(): string
    {
        if (!$this->response_code) {
            return 'pending';
        }
        if ($this->response_code >= 200 && $this->response_code < 300) {
            return 'success';
        }
        if ($this->response_code >= 400) {
            return 'error';
        }

        return 'warning';
    }
}
