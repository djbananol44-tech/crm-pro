<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'is_encrypted',
    ];

    /**
     * Атрибуты, которые должны быть приведены к типам.
     */
    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Время кэширования в секундах.
     */
    protected static int $cacheTime = 3600;

    /**
     * Список ключей, которые должны быть зашифрованы.
     */
    protected static array $secretKeys = [
        'meta_access_token',
        'meta_app_secret',
        'meta_webhook_verify_token',
        'telegram_bot_token',
        'gemini_api_key',
    ];

    /**
     * Проверить, является ли ключ секретным.
     */
    public static function isSecretKey(string $key): bool
    {
        return in_array($key, self::$secretKeys, true);
    }

    /**
     * Получить список секретных ключей.
     */
    public static function getSecretKeys(): array
    {
        return self::$secretKeys;
    }

    /**
     * Получить значение настройки по ключу.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";

        return Cache::remember($cacheKey, self::$cacheTime, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            $value = $setting->value;

            // Расшифровываем если зашифровано
            if ($setting->is_encrypted && !empty($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    Log::error("Setting: Ошибка расшифровки ключа {$key}", ['error' => $e->getMessage()]);

                    return $default;
                }
            }

            return self::castValue($value, $setting->type ?? 'string');
        });
    }

    /**
     * Получить замаскированное значение для отображения в UI.
     * Возвращает **** если значение есть, пустую строку если нет.
     */
    public static function getMasked(string $key): string
    {
        $value = self::get($key);

        if (empty($value)) {
            return '';
        }

        // Показываем последние 4 символа для идентификации
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', min($length - 4, 20)).substr($value, -4);
    }

    /**
     * Проверить, установлено ли значение (без раскрытия).
     */
    public static function hasValue(string $key): bool
    {
        $value = self::get($key);

        return !empty($value);
    }

    /**
     * Установить значение настройки.
     *
     * @param  int|null  $changedBy  ID пользователя, изменившего настройку
     */
    public static function set(string $key, mixed $value, ?int $changedBy = null): bool
    {
        $isSecret = self::isSecretKey($key);
        $oldSetting = self::where('key', $key)->first();
        $hadValue = $oldSetting && !empty($oldSetting->value);

        // Подготавливаем значение
        $storedValue = is_array($value) ? json_encode($value) : $value;

        // Шифруем секретные ключи
        if ($isSecret && !empty($storedValue)) {
            try {
                $storedValue = Crypt::encryptString($storedValue);
            } catch (\Exception $e) {
                Log::error("Setting: Ошибка шифрования ключа {$key}", ['error' => $e->getMessage()]);

                return false;
            }
        }

        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $isSecret && !empty($storedValue),
            ]
        );

        // Логируем изменение (без значения!)
        $wasChanged = $setting->wasRecentlyCreated || $setting->wasChanged();

        if ($wasChanged) {
            self::logAudit(
                key: $key,
                action: $oldSetting ? 'updated' : 'created',
                changedBy: $changedBy ?? auth()->id(),
                hadValueBefore: $hadValue,
                hasValueAfter: !empty($value)
            );
        }

        // Очищаем кэш
        Cache::forget("setting:{$key}");
        Cache::forget('settings:meta');

        return $wasChanged;
    }

    /**
     * Записать аудит изменения настройки.
     */
    protected static function logAudit(
        string $key,
        string $action,
        ?int $changedBy,
        bool $hadValueBefore,
        bool $hasValueAfter
    ): void {
        try {
            SettingAuditLog::create([
                'setting_key' => $key,
                'action' => $action,
                'user_id' => $changedBy,
                'is_secret' => self::isSecretKey($key),
                'had_value_before' => $hadValueBefore,
                'has_value_after' => $hasValueAfter,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Setting: Не удалось записать аудит для {$key}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Получить все настройки группы.
     */
    public static function getGroup(string $group): array
    {
        $cacheKey = "settings:{$group}";

        return Cache::remember($cacheKey, self::$cacheTime, function () use ($group) {
            $settings = self::where('group', $group)->get();
            $result = [];

            foreach ($settings as $setting) {
                $value = $setting->value;

                // Расшифровываем если нужно
                if ($setting->is_encrypted && !empty($value)) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Exception $e) {
                        Log::error("Setting: Ошибка расшифровки ключа {$setting->key}");

                        continue;
                    }
                }

                $result[$setting->key] = self::castValue($value, $setting->type ?? 'string');
            }

            return $result;
        });
    }

    /**
     * Очистить весь кэш настроек.
     */
    public static function clearCache(): void
    {
        $settings = self::all();

        foreach ($settings as $setting) {
            Cache::forget("setting:{$setting->key}");
        }

        $groups = self::distinct('group')->pluck('group');
        foreach ($groups as $group) {
            Cache::forget("settings:{$group}");
        }
    }

    /**
     * Приведение значения к нужному типу.
     */
    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Связь с аудит-логами.
     */
    public function auditLogs()
    {
        return SettingAuditLog::where('setting_key', $this->key)->latest();
    }
}
