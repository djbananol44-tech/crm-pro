<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApiStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.api-status-widget';

    protected static ?int $sort = -100;

    protected int|string|array $columnSpan = 'full';

    // Обновление каждые 60 секунд
    protected static ?string $pollingInterval = '60s';

    public function getStatuses(): array
    {
        return [
            'meta' => $this->getMetaStatus(),
            'telegram' => $this->getTelegramStatus(),
            'gemini' => $this->getGeminiStatus(),
        ];
    }

    private function getMetaStatus(): array
    {
        $token = Setting::get('meta_access_token');

        if (empty($token)) {
            return [
                'status' => 'unconfigured',
                'label' => 'Meta API',
                'message' => 'Токен не настроен',
                'icon' => '📘',
            ];
        }

        // Кэшируем проверку на 5 минут
        $status = Cache::remember('api_status_meta', 300, function () use ($token) {
            try {
                $response = Http::timeout(5)->get('https://graph.facebook.com/me', [
                    'access_token' => $token,
                ]);

                return $response->successful();
            } catch (\Exception $e) {
                return false;
            }
        });

        return [
            'status' => $status ? 'online' : 'error',
            'label' => 'Meta API',
            'message' => $status ? 'Подключено' : 'Ошибка подключения',
            'icon' => '📘',
        ];
    }

    private function getTelegramStatus(): array
    {
        $token = Setting::get('telegram_bot_token');

        if (empty($token)) {
            return [
                'status' => 'unconfigured',
                'label' => 'Telegram',
                'message' => 'Токен не настроен',
                'icon' => '📱',
            ];
        }

        $status = Cache::remember('api_status_telegram', 300, function () use ($token) {
            try {
                $response = Http::timeout(5)->get("https://api.telegram.org/bot{$token}/getMe");

                return $response->successful() && ($response->json('ok') ?? false);
            } catch (\Exception $e) {
                return false;
            }
        });

        $webhookActive = Setting::get('telegram_webhook_active') === 'true';

        return [
            'status' => $status ? 'online' : 'error',
            'label' => 'Telegram',
            'message' => $status
                ? ($webhookActive ? 'Webhook активен' : 'Long Polling')
                : 'Ошибка подключения',
            'icon' => '📱',
        ];
    }

    private function getGeminiStatus(): array
    {
        $key = Setting::get('gemini_api_key');
        $enabled = Setting::get('ai_enabled');
        $enabled = $enabled === true || $enabled === 'true' || $enabled === '1';

        if (empty($key)) {
            return [
                'status' => 'unconfigured',
                'label' => 'Gemini AI',
                'message' => 'API ключ не настроен',
                'icon' => '🤖',
            ];
        }

        if (!$enabled) {
            return [
                'status' => 'disabled',
                'label' => 'Gemini AI',
                'message' => 'Отключено',
                'icon' => '🤖',
            ];
        }

        // Не проверяем Gemini каждый раз - просто проверяем наличие ключа
        return [
            'status' => 'online',
            'label' => 'Gemini AI',
            'message' => 'Активен',
            'icon' => '🤖',
        ];
    }

    public function refreshStatuses(): void
    {
        Cache::forget('api_status_meta');
        Cache::forget('api_status_telegram');

        $this->dispatch('$refresh');
    }
}
