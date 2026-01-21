<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\SystemLog;
use App\Services\AiAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class JgglDoctor extends Command
{
    protected $signature = 'jggl:doctor 
                            {--fix : Попытаться исправить проблемы автоматически}
                            {--json : Вывод в формате JSON}';

    protected $description = '🏥 Полная диагностика JGGL CRM: DB, Redis, Queue, SSL, Webhooks, API';

    private array $checks = [];

    private int $passed = 0;

    private int $failed = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        if (!$this->option('json')) {
            $this->printHeader();
        }

        // Core Infrastructure
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkQueue();
        $this->checkAppKey();
        $this->checkPermissions();

        // External Services
        $this->checkMetaApi();
        $this->checkTelegramBot();
        $this->checkGeminiApi();

        // Web & SSL
        $this->checkSsl();
        $this->checkWebhookEndpoints();

        // System Health
        $this->checkRecentErrors();
        $this->checkDiskSpace();

        // Output
        if ($this->option('json')) {
            $this->outputJson();
        } else {
            $this->outputTable();
            $this->outputSummary();
        }

        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Checks
    // ─────────────────────────────────────────────────────────────────────────

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $version = DB::selectOne('SELECT version()')->version ?? 'unknown';
            $tablesCount = count(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));

            $this->addCheck('database', 'ok', 'PostgreSQL', "v{$version}, {$tablesCount} таблиц");
        } catch (\Exception $e) {
            $this->addCheck('database', 'error', 'PostgreSQL', $e->getMessage());
        }
    }

    private function checkRedis(): void
    {
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');
        $needsRedis = $cacheDriver === 'redis' || $queueDriver === 'redis';

        try {
            $pong = Redis::ping();
            $info = Redis::info('server');
            $version = $info['redis_version'] ?? 'unknown';
            $memory = $info['used_memory_human'] ?? 'N/A';

            $this->addCheck('redis', 'ok', 'Redis', "v{$version}, RAM: {$memory}");
        } catch (\Exception $e) {
            // Redis is critical only if used for cache/queue
            $status = $needsRedis ? 'error' : 'warning';
            $details = $needsRedis
                ? 'Недоступен! Cache/Queue настроены на Redis'
                : 'Недоступен (не критично, используется sync)';

            $this->addCheck('redis', $status, 'Redis', $details);
        }
    }

    private function checkQueue(): void
    {
        $driver = config('queue.default');
        $failedCount = 0;
        $pendingMeta = 0;
        $pendingAi = 0;
        $pendingDefault = 0;

        // Check failed_jobs table
        try {
            $failedCount = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Check Redis queues
        if ($driver === 'redis') {
            try {
                $pendingMeta = (int) Redis::llen('queues:meta');
                $pendingAi = (int) Redis::llen('queues:ai');
                $pendingDefault = (int) Redis::llen('queues:default');
            } catch (\Exception $e) {
                // Redis not available - not critical
            }
        }

        $total = $pendingMeta + $pendingAi + $pendingDefault;
        $status = $failedCount > 0 ? 'warning' : 'ok';
        $details = "Driver: {$driver}, В очереди: {$total}, Failed: {$failedCount}";

        $this->addCheck('queue', $status, 'Очередь задач', $details);
    }

    private function checkAppKey(): void
    {
        $key = config('app.key');

        if (empty($key)) {
            $this->addCheck('app_key', 'error', 'APP_KEY', 'Не установлен! Выполните: php artisan key:generate');
        } elseif (!str_starts_with($key, 'base64:')) {
            $this->addCheck('app_key', 'warning', 'APP_KEY', 'Формат нестандартный');
        } else {
            $this->addCheck('app_key', 'ok', 'APP_KEY', 'Установлен ✓');
        }
    }

    private function checkPermissions(): void
    {
        $dirs = [
            storage_path() => 'storage/',
            storage_path('logs') => 'storage/logs/',
            base_path('bootstrap/cache') => 'bootstrap/cache/',
        ];

        $issues = [];
        foreach ($dirs as $path => $name) {
            if (!is_writable($path)) {
                $issues[] = $name;
            }
        }

        if (empty($issues)) {
            $this->addCheck('permissions', 'ok', 'Права доступа', 'storage/, bootstrap/cache/ — OK');
        } else {
            $this->addCheck('permissions', 'error', 'Права доступа', 'Нет записи: '.implode(', ', $issues));
        }
    }

    private function checkMetaApi(): void
    {
        $token = Setting::get('meta_access_token');
        $pageId = Setting::get('meta_page_id');
        $lastCheck = Setting::get('meta_last_check');

        if (empty($token) || empty($pageId)) {
            $this->addCheck('meta', 'disabled', 'Meta API', 'Не настроен (токен или Page ID отсутствует)');

            return;
        }

        // Try to verify token
        try {
            $response = Http::timeout(10)->get('https://graph.facebook.com/v18.0/me', [
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                $name = $response->json('name') ?? 'Unknown';
                Setting::set('meta_last_check', now()->toISOString());
                $this->addCheck('meta', 'ok', 'Meta API', "Подключен: {$name}");
            } else {
                $error = $response->json('error.message') ?? 'Unknown error';
                $this->addCheck('meta', 'error', 'Meta API', "Ошибка: {$error}");
            }
        } catch (\Exception $e) {
            $this->addCheck('meta', 'error', 'Meta API', 'Таймаут или сетевая ошибка');
        }
    }

    private function checkTelegramBot(): void
    {
        $token = Setting::get('telegram_bot_token');
        $mode = Setting::get('telegram_mode', 'polling');
        $lastCheck = Setting::get('telegram_last_check');

        if (empty($token)) {
            $this->addCheck('telegram', 'disabled', 'Telegram Bot', 'Не настроен');

            return;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful()) {
                $username = $response->json('result.username') ?? 'unknown';
                Setting::set('telegram_last_check', now()->toISOString());
                $this->addCheck('telegram', 'ok', 'Telegram Bot', "@{$username} (mode: {$mode})");
            } else {
                $error = $response->json('description') ?? 'Invalid token';
                $this->addCheck('telegram', 'error', 'Telegram Bot', $error);
            }
        } catch (\Exception $e) {
            $this->addCheck('telegram', 'error', 'Telegram Bot', 'Таймаут или сетевая ошибка');
        }
    }

    private function checkGeminiApi(): void
    {
        $key = Setting::get('gemini_api_key');
        $enabled = Setting::get('ai_enabled', false);
        $lastCheck = Setting::get('gemini_last_check');

        if (empty($key)) {
            $this->addCheck('gemini', 'disabled', 'Gemini AI', 'API ключ не настроен');

            return;
        }

        if (!$enabled) {
            $this->addCheck('gemini', 'warning', 'Gemini AI', 'Ключ есть, но AI отключен в настройках');

            return;
        }

        try {
            $ai = app(AiAnalysisService::class);
            $result = $ai->testConnection();

            if ($result['success']) {
                Setting::set('gemini_last_check', now()->toISOString());
                $this->addCheck('gemini', 'ok', 'Gemini AI', 'Работает ✓');
            } else {
                $this->addCheck('gemini', 'error', 'Gemini AI', $result['message']);
            }
        } catch (\Exception $e) {
            $this->addCheck('gemini', 'error', 'Gemini AI', $e->getMessage());
        }
    }

    private function checkSsl(): void
    {
        $appUrl = config('app.url');

        if (!str_starts_with($appUrl, 'https://')) {
            $this->addCheck('ssl', 'warning', 'SSL/HTTPS', "APP_URL не HTTPS: {$appUrl}");

            return;
        }

        try {
            $context = stream_context_create([
                'ssl' => ['capture_peer_cert' => true, 'verify_peer' => false],
            ]);

            $host = parse_url($appUrl, PHP_URL_HOST);
            $client = @stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($client) {
                $params = stream_context_get_params($client);
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? '');

                if ($cert) {
                    $validTo = date('d.m.Y', $cert['validTo_time_t']);
                    $daysLeft = (int) (($cert['validTo_time_t'] - time()) / 86400);

                    $status = $daysLeft > 14 ? 'ok' : ($daysLeft > 0 ? 'warning' : 'error');
                    $this->addCheck('ssl', $status, 'SSL сертификат', "Действует до {$validTo} ({$daysLeft} дней)");
                } else {
                    $this->addCheck('ssl', 'warning', 'SSL сертификат', 'Не удалось прочитать сертификат');
                }
                fclose($client);
            } else {
                $this->addCheck('ssl', 'error', 'SSL сертификат', "Нет SSL на {$host}:443");
            }
        } catch (\Exception $e) {
            $this->addCheck('ssl', 'warning', 'SSL сертификат', 'Проверка недоступна локально');
        }
    }

    private function checkWebhookEndpoints(): void
    {
        $appUrl = config('app.url');
        $endpoints = [
            '/api/webhooks/meta' => 'Meta Webhook',
            '/api/webhooks/telegram' => 'Telegram Webhook',
            '/api/health' => 'Health Check',
        ];

        $working = 0;
        $issues = [];

        foreach ($endpoints as $path => $name) {
            try {
                $response = Http::timeout(5)
                    ->withoutVerifying()
                    ->get($appUrl.$path);

                // Meta webhook returns 403 without token, that's OK
                // Telegram returns 405 on GET, that's OK
                // Health should return 200
                if ($response->status() < 500) {
                    $working++;
                } else {
                    $issues[] = "{$name}: HTTP {$response->status()}";
                }
            } catch (\Exception $e) {
                $issues[] = "{$name}: недоступен";
            }
        }

        if (empty($issues)) {
            $this->addCheck('webhooks', 'ok', 'Webhook Endpoints', "Все {$working} эндпоинта доступны");
        } else {
            $this->addCheck('webhooks', 'warning', 'Webhook Endpoints', implode('; ', $issues));
        }
    }

    private function checkRecentErrors(): void
    {
        try {
            $errors24h = SystemLog::whereIn('level', ['error', 'critical'])
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            $errors1h = SystemLog::whereIn('level', ['error', 'critical'])
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($errors1h > 10) {
                $this->addCheck('errors', 'error', 'Системные ошибки', "{$errors1h} за последний час!");
            } elseif ($errors24h > 50) {
                $this->addCheck('errors', 'warning', 'Системные ошибки', "{$errors24h} за 24 часа");
            } else {
                $this->addCheck('errors', 'ok', 'Системные ошибки', "{$errors24h} за 24ч, {$errors1h} за 1ч");
            }
        } catch (\Exception $e) {
            $this->addCheck('errors', 'warning', 'Системные ошибки', 'Таблица system_logs недоступна');
        }
    }

    private function checkDiskSpace(): void
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());

        if ($free === false || $total === false) {
            $this->addCheck('disk', 'warning', 'Диск', 'Не удалось проверить');

            return;
        }

        $freeGb = round($free / 1024 / 1024 / 1024, 1);
        $usedPercent = round((1 - $free / $total) * 100);

        if ($freeGb < 1) {
            $this->addCheck('disk', 'error', 'Диск', "Критически мало места: {$freeGb} GB");
        } elseif ($usedPercent > 90) {
            $this->addCheck('disk', 'warning', 'Диск', "Занято {$usedPercent}%, свободно {$freeGb} GB");
        } else {
            $this->addCheck('disk', 'ok', 'Диск', "Свободно {$freeGb} GB ({$usedPercent}% занято)");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Output
    // ─────────────────────────────────────────────────────────────────────────

    private function addCheck(string $id, string $status, string $name, string $details): void
    {
        $this->checks[$id] = compact('status', 'name', 'details');

        match ($status) {
            'ok' => $this->passed++,
            'error' => $this->failed++,
            'warning', 'disabled' => $this->warnings++,
        };
    }

    private function printHeader(): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>╔═══════════════════════════════════════════════════════════════╗</>');
        $this->line('<fg=cyan;options=bold>║        🏥 JGGL CRM — Системная диагностика                   ║</>');
        $this->line('<fg=cyan;options=bold>╚═══════════════════════════════════════════════════════════════╝</>');
        $this->newLine();
    }

    private function outputTable(): void
    {
        $rows = [];

        foreach ($this->checks as $id => $check) {
            $icon = match ($check['status']) {
                'ok' => '🟢',
                'warning' => '🟡',
                'error' => '🔴',
                'disabled' => '⚪',
                default => '⚪',
            };

            $rows[] = [
                $check['name'],
                $icon.' '.ucfirst($check['status']),
                $this->truncate($check['details'], 50),
            ];
        }

        $this->table(
            ['<fg=cyan>Компонент</>', '<fg=cyan>Статус</>', '<fg=cyan>Детали</>'],
            $rows
        );
    }

    private function outputSummary(): void
    {
        $this->newLine();

        $total = $this->passed + $this->failed + $this->warnings;

        if ($this->failed === 0) {
            $this->info("✅ Диагностика завершена: {$this->passed}/{$total} OK, {$this->warnings} предупреждений");
        } else {
            $this->error("❌ Обнаружено проблем: {$this->failed}. Требуется внимание!");
        }

        // Quick tips
        if ($this->failed > 0 || $this->warnings > 0) {
            $this->newLine();
            $this->line('<fg=yellow>💡 Рекомендации:</>');

            if (isset($this->checks['meta']) && $this->checks['meta']['status'] !== 'ok') {
                $this->line('   • Meta API: Настройте в /admin/settings');
            }
            if (isset($this->checks['telegram']) && $this->checks['telegram']['status'] !== 'ok') {
                $this->line('   • Telegram: Создайте бота через @BotFather');
            }
            if (isset($this->checks['ssl']) && $this->checks['ssl']['status'] !== 'ok') {
                $this->line('   • SSL: Используйте Cloudflare Tunnel или Certbot');
            }
        }

        $this->newLine();
    }

    private function outputJson(): void
    {
        $output = [
            'status' => $this->failed > 0 ? 'unhealthy' : 'healthy',
            'timestamp' => now()->toISOString(),
            'summary' => [
                'passed' => $this->passed,
                'failed' => $this->failed,
                'warnings' => $this->warnings,
            ],
            'checks' => $this->checks,
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length - 3).'...'
            : $text;
    }
}
