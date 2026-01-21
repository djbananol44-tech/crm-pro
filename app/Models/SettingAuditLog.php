<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Аудит-лог изменений настроек.
 *
 * НЕ хранит значения секретов — только факт изменения.
 */
class SettingAuditLog extends Model
{
    protected $table = 'setting_audit_logs';

    protected $fillable = [
        'setting_key',
        'action',
        'user_id',
        'is_secret',
        'had_value_before',
        'has_value_after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
        'had_value_before' => 'boolean',
        'has_value_after' => 'boolean',
    ];

    /**
     * Связь с пользователем, который изменил настройку.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить описание действия на русском.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Создано',
            'updated' => 'Изменено',
            'deleted' => 'Удалено',
            default => $this->action,
        };
    }

    /**
     * Получить описание изменения.
     */
    public function getChangeDescriptionAttribute(): string
    {
        if ($this->action === 'created') {
            return 'Значение установлено';
        }

        if ($this->action === 'deleted') {
            return 'Значение удалено';
        }

        if (!$this->had_value_before && $this->has_value_after) {
            return 'Значение установлено (было пусто)';
        }

        if ($this->had_value_before && !$this->has_value_after) {
            return 'Значение очищено';
        }

        return 'Значение обновлено';
    }

    /**
     * Получить читаемое имя ключа.
     */
    public function getKeyLabelAttribute(): string
    {
        return match ($this->setting_key) {
            'meta_access_token' => 'Meta Access Token',
            'meta_app_secret' => 'Meta App Secret',
            'meta_webhook_verify_token' => 'Meta Webhook Token',
            'meta_page_id' => 'Meta Page ID',
            'meta_app_id' => 'Meta App ID',
            'telegram_bot_token' => 'Telegram Bot Token',
            'telegram_mode' => 'Режим Telegram',
            'gemini_api_key' => 'Gemini API Key',
            'ai_enabled' => 'AI включен',
            default => $this->setting_key,
        };
    }

    /**
     * Scope: только секретные ключи.
     */
    public function scopeSecrets($query)
    {
        return $query->where('is_secret', true);
    }

    /**
     * Scope: за последние N дней.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
