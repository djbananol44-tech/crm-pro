<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class ControlCenterWidget extends Widget
{
    protected static string $view = 'filament.widgets.control-center-widget';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    public array $services = [];

    public array $stats = [];

    public array $recentLogs = [];

    public bool $isLoading = false;

    public function mount(): void
    {
        $this->refreshAll();
    }

    public function refreshAll(): void
    {
        $this->checkServices();
        $this->loadStats();
        $this->loadRecentLogs();
    }

    public function checkServices(): void
    {
        $this->services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'meta_api' => $this->checkMetaApi(),
            'telegram' => $this->checkTelegram(),
            'gemini' => $this->checkGemini(),
            'queue' => $this->checkQueue(),
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $tables = DB::select("SELECT count(*) as count FROM information_schema.tables WHERE table_schema = 'public'");

            return [
                'status' => 'online',
                'message' => "PostgreSQL • {$tables[0]->count} таблиц",
            ];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Нет подключения'];
        }
    }

    protected function checkRedis(): array
    {
        try {
            $ping = Redis::ping();
            $info = Redis::info('memory');
            $usedMb = round(($info['used_memory'] ?? 0) / 1024 / 1024, 1);

            return [
                'status' => 'online',
                'message' => "Redis • {$usedMb}MB",
            ];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Нет подключения'];
        }
    }

    protected function checkMetaApi(): array
    {
        $token = Setting::get('meta_access_token');
        $pageId = Setting::get('meta_page_id');

        if (empty($token) || empty($pageId)) {
            return ['status' => 'warning', 'message' => 'Не настроен'];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->get("https://graph.facebook.com/v19.0/{$pageId}");

            if ($response->successful()) {
                $name = $response->json('name') ?? 'OK';

                return ['status' => 'online', 'message' => "Meta • {$name}"];
            }

            return ['status' => 'offline', 'message' => 'Ошибка API'];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Таймаут'];
        }
    }

    protected function checkTelegram(): array
    {
        $token = Setting::get('telegram_bot_token');

        if (empty($token)) {
            return ['status' => 'warning', 'message' => 'Не настроен'];
        }

        try {
            $response = Http::timeout(5)->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful() && ($response->json('ok') ?? false)) {
                $username = $response->json('result.username') ?? 'OK';

                return ['status' => 'online', 'message' => "TG • @{$username}"];
            }

            return ['status' => 'offline', 'message' => 'Неверный токен'];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Таймаут'];
        }
    }

    protected function checkGemini(): array
    {
        $apiKey = Setting::get('gemini_api_key');
        $enabled = filter_var(Setting::get('ai_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);

        if (empty($apiKey)) {
            return ['status' => 'warning', 'message' => 'Не настроен'];
        }

        if (!$enabled) {
            return ['status' => 'warning', 'message' => 'Выключен'];
        }

        return ['status' => 'online', 'message' => 'Gemini AI • OK'];
    }

    protected function checkQueue(): array
    {
        try {
            $size = Redis::llen('queues:default') ?? 0;
            $failed = DB::table('failed_jobs')->count();

            if ($failed > 0) {
                return ['status' => 'warning', 'message' => "Очередь • {$size} | ⚠️ {$failed} ошибок"];
            }

            return ['status' => 'online', 'message' => "Очередь • {$size} задач"];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Ошибка'];
        }
    }

    public function loadStats(): void
    {
        $this->stats = [
            'total_deals' => Deal::count(),
            'active_deals' => Deal::whereIn('status', ['New', 'In Progress'])->count(),
            'today_deals' => Deal::whereDate('created_at', today())->count(),
            'online_managers' => User::where('role', 'manager')
                ->where('last_activity_at', '>=', now()->subMinutes(5))
                ->count(),
            'total_managers' => User::where('role', 'manager')->count(),
        ];
    }

    public function loadRecentLogs(): void
    {
        $this->recentLogs = SystemLog::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'service' => $log->service,
                'icon' => $log->service_icon,
                'level' => $log->level,
                'color' => $log->level_color,
                'message' => $log->message,
                'time' => $log->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────────────────────

    public function clearCache(): void
    {
        try {
            Artisan::call('optimize:clear');
            Artisan::call('config:cache');

            SystemLog::info('system', 'Кэш очищен через Центр управления');

            $this->dispatch('notify', [
                'title' => 'Кэш очищен',
                'body' => 'Все кэши Laravel успешно очищены',
                'color' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'title' => 'Ошибка',
                'body' => $e->getMessage(),
                'color' => 'danger',
            ]);
        }

        $this->refreshAll();
    }

    public function restartQueue(): void
    {
        try {
            Artisan::call('queue:restart');

            SystemLog::info('system', 'Очередь перезапущена через Центр управления');

            $this->dispatch('notify', [
                'title' => 'Очередь перезапущена',
                'body' => 'Сигнал перезапуска отправлен всем воркерам',
                'color' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'title' => 'Ошибка',
                'body' => $e->getMessage(),
                'color' => 'danger',
            ]);
        }

        $this->refreshAll();
    }

    public function runHealthCheck(): void
    {
        try {
            Artisan::call('crm:check');

            $this->dispatch('notify', [
                'title' => 'Диагностика завершена',
                'body' => 'Результаты записаны в логи',
                'color' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'title' => 'Ошибка',
                'body' => $e->getMessage(),
                'color' => 'danger',
            ]);
        }

        $this->refreshAll();
    }

    public function syncMeta(): void
    {
        try {
            Artisan::call('meta:sync-now');

            SystemLog::info('system', 'Meta синхронизация запущена через Центр управления');

            $this->dispatch('notify', [
                'title' => 'Синхронизация запущена',
                'body' => 'Данные из Meta Business Suite синхронизируются',
                'color' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'title' => 'Ошибка',
                'body' => $e->getMessage(),
                'color' => 'danger',
            ]);
        }

        $this->refreshAll();
    }

    public function linkBot(): void
    {
        try {
            Artisan::call('crm:link-bot');

            $this->dispatch('notify', [
                'title' => 'Бот настроен',
                'body' => Artisan::output(),
                'color' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'title' => 'Ошибка',
                'body' => $e->getMessage(),
                'color' => 'danger',
            ]);
        }

        $this->refreshAll();
    }
}
