<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SystemHealthWidget extends Widget
{
    protected static string $view = 'filament.widgets.system-health-widget';

    protected static ?int $sort = -90;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '120s';

    public function getHealthData(): array
    {
        return Cache::remember('system_health_data', 60, function () {
            return [
                'database' => $this->checkDatabase(),
                'storage' => $this->checkStorage(),
                'queue' => $this->checkQueue(),
                'meta' => $this->checkMeta(),
                'telegram' => $this->checkTelegram(),
                'gemini' => $this->checkGemini(),
            ];
        });
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $tables = DB::select("SELECT count(*) as cnt FROM information_schema.tables WHERE table_schema = 'public'");

            return [
                'status' => 'ok',
                'label' => 'База данных',
                'icon' => '🗄️',
                'details' => 'PostgreSQL: '.($tables[0]->cnt ?? 0).' таблиц',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'label' => 'База данных',
                'icon' => '🗄️',
                'details' => 'Ошибка подключения',
            ];
        }
    }

    private function checkStorage(): array
    {
        $writable = is_writable(storage_path('logs'));

        return [
            'status' => $writable ? 'ok' : 'error',
            'label' => 'Хранилище',
            'icon' => '📁',
            'details' => $writable ? 'Доступно для записи' : 'Нет прав на запись',
        ];
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');

        return [
            'status' => 'ok',
            'label' => 'Очередь',
            'icon' => '⚙️',
            'details' => ucfirst($driver),
        ];
    }

    private function checkMeta(): array
    {
        $token = Setting::get('meta_access_token');
        if (empty($token)) {
            return [
                'status' => 'warning',
                'label' => 'Meta API',
                'icon' => '📘',
                'details' => 'Не настроен',
            ];
        }

        try {
            $response = Http::timeout(5)->get('https://graph.facebook.com/me', [
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                return [
                    'status' => 'ok',
                    'label' => 'Meta API',
                    'icon' => '📘',
                    'details' => $response->json('name') ?? 'Подключено',
                ];
            }

            return [
                'status' => 'error',
                'label' => 'Meta API',
                'icon' => '📘',
                'details' => 'Невалидный токен',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'label' => 'Meta API',
                'icon' => '📘',
                'details' => 'Timeout',
            ];
        }
    }

    private function checkTelegram(): array
    {
        $token = Setting::get('telegram_bot_token');
        if (empty($token)) {
            return [
                'status' => 'warning',
                'label' => 'Telegram',
                'icon' => '📱',
                'details' => 'Не настроен',
            ];
        }

        try {
            $response = Http::timeout(5)->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful() && ($response->json('ok') ?? false)) {
                $username = $response->json('result.username');

                return [
                    'status' => 'ok',
                    'label' => 'Telegram',
                    'icon' => '📱',
                    'details' => "@{$username}",
                ];
            }

            return [
                'status' => 'error',
                'label' => 'Telegram',
                'icon' => '📱',
                'details' => 'Невалидный токен',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'label' => 'Telegram',
                'icon' => '📱',
                'details' => 'Timeout',
            ];
        }
    }

    private function checkGemini(): array
    {
        $key = Setting::get('gemini_api_key');
        $enabled = Setting::get('ai_enabled');
        $enabled = $enabled === true || $enabled === 'true' || $enabled === '1';

        if (empty($key)) {
            return [
                'status' => 'warning',
                'label' => 'Gemini AI',
                'icon' => '🤖',
                'details' => 'Не настроен',
            ];
        }

        if (!$enabled) {
            return [
                'status' => 'warning',
                'label' => 'Gemini AI',
                'icon' => '🤖',
                'details' => 'Отключен',
            ];
        }

        return [
            'status' => 'ok',
            'label' => 'Gemini AI',
            'icon' => '🤖',
            'details' => 'Активен',
        ];
    }

    public function refreshHealth(): void
    {
        Cache::forget('system_health_data');

        Notification::make()
            ->title('Данные обновлены')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    public function restartWorkers(): void
    {
        try {
            Artisan::call('queue:restart');

            Notification::make()
                ->title('Воркеры перезапущены')
                ->body('Команда queue:restart выполнена')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка перезапуска')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->dispatch('$refresh');
    }

    public function runDiagnostics(): void
    {
        try {
            Artisan::call('crm:check');
            $output = Artisan::output();

            Notification::make()
                ->title('Диагностика завершена')
                ->body('Результат в консоли')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка диагностики')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
