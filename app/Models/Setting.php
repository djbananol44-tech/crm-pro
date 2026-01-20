<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
    ];

    /**
     * Время кэширования в секундах.
     */
    protected static int $cacheTime = 3600;

    /**
     * Получить значение настройки по ключу.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";

        return Cache::remember($cacheKey, self::$cacheTime, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Установить значение настройки.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function set(string $key, mixed $value): bool
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );

        // Очищаем кэш
        Cache::forget("setting:{$key}");
        Cache::forget('settings:meta');

        return $setting->wasRecentlyCreated || $setting->wasChanged();
    }

    /**
     * Получить все настройки группы.
     *
     * @param string $group
     * @return array
     */
    public static function getGroup(string $group): array
    {
        $cacheKey = "settings:{$group}";

        return Cache::remember($cacheKey, self::$cacheTime, function () use ($group) {
            $settings = self::where('group', $group)->get();
            $result = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = self::castValue($setting->value, $setting->type);
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
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
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
}
