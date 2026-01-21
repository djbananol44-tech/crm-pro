<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class CrmCheck extends Command
{
    protected $signature = 'crm:check {--fix : Попытаться исправить проблемы}';

    protected $description = 'Полная диагностика CRM системы';

    private array $results = [];

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('🔍 JGGL CRM — Системная диагностика');
        $this->newLine();

        // Проверки
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkDirectoryPermissions();
        $this->checkMetaApi();
        $this->checkTelegramApi();
        $this->checkGeminiApi();
        $this->checkQueue();
        $this->checkScheduler();

        // Вывод результатов
        $this->displayResults();

        // Подсчет
        $passed = collect($this->results)->where('status', 'ok')->count();
        $failed = collect($this->results)->where('status', 'error')->count();
        $warnings = collect($this->results)->where('status', 'warning')->count();

        $this->newLine();

        if ($failed === 0) {
            $this->components->info("✅ Все проверки пройдены! ({$passed} OK, {$warnings} предупреждений)");

            return Command::SUCCESS;
        }

        $this->components->error("❌ Обнаружено проблем: {$failed}");

        return Command::FAILURE;
    }

    private function checkDatabase(): void
    {
        $this->components->task('База данных (PostgreSQL)', function () {
            try {
                DB::connection()->getPdo();
                $version = DB::selectOne('SELECT version()')->version ?? 'Unknown';
                $this->results['database'] = [
                    'status' => 'ok',
                    'name' => 'База данных',
                    'message' => 'Подключено',
                    'details' => substr($version, 0, 50),
                ];

                return true;
            } catch (\Exception $e) {
                $this->results['database'] = [
                    'status' => 'error',
                    'name' => 'База данных',
                    'message' => 'Ошибка подключения',
                    'details' => $e->getMessage(),
                ];

                return false;
            }
        });
    }

    private function checkRedis(): void
    {
        $this->components->task('Redis (Кэш/Очереди)', function () {
            try {
                // Проверяем, используется ли Redis
                $cacheDriver = config('cache.default');
                $queueDriver = config('queue.default');

                if ($cacheDriver !== 'redis' && $queueDriver !== 'redis') {
                    $this->results['redis'] = [
                        'status' => 'warning',
                        'name' => 'Redis',
                        'message' => 'Не используется',
                        'details' => "Cache: {$cacheDriver}, Queue: {$queueDriver}",
                    ];

                    return true;
                }

                Redis::ping();
                $this->results['redis'] = [
                    'status' => 'ok',
                    'name' => 'Redis',
                    'message' => 'Подключено',
                    'details' => 'PING → PONG',
                ];

                return true;
            } catch (\Exception $e) {
                $this->results['redis'] = [
                    'status' => 'warning',
                    'name' => 'Redis',
                    'message' => 'Недоступен',
                    'details' => 'Используется fallback',
                ];

                return true;
            }
        });
    }

    private function checkDirectoryPermissions(): void
    {
        $this->components->task('Права на директории', function () {
            $dirs = [
                storage_path('logs'),
                storage_path('framework/cache'),
                storage_path('framework/sessions'),
                storage_path('framework/views'),
                base_path('bootstrap/cache'),
            ];

            $issues = [];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                if (!is_writable($dir)) {
                    $issues[] = basename($dir);
                }
            }

            if (empty($issues)) {
                $this->results['permissions'] = [
                    'status' => 'ok',
                    'name' => 'Права доступа',
                    'message' => 'OK',
                    'details' => 'Все директории доступны для записи',
                ];

                return true;
            }

            $this->results['permissions'] = [
                'status' => 'error',
                'name' => 'Права доступа',
                'message' => 'Проблемы с записью',
                'details' => implode(', ', $issues),
            ];

            return false;
        });
    }

    private function checkMetaApi(): void
    {
        $this->components->task('Meta Business API', function () {
            try {
                $token = Setting::get('meta_access_token');

                if (empty($token)) {
                    $this->results['meta'] = [
                        'status' => 'warning',
                        'name' => 'Meta API',
                        'message' => 'Не настроен',
                        'details' => 'Добавьте токен в настройках',
                    ];

                    return true;
                }

                $response = Http::timeout(10)->get('https://graph.facebook.com/me', [
                    'access_token' => $token,
                ]);

                if ($response->successful()) {
                    $name = $response->json('name') ?? 'Connected';
                    $this->results['meta'] = [
                        'status' => 'ok',
                        'name' => 'Meta API',
                        'message' => 'Подключено',
                        'details' => $name,
                    ];

                    return true;
                }

                $error = $response->json('error.message') ?? 'Unknown error';
                $this->results['meta'] = [
                    'status' => 'error',
                    'name' => 'Meta API',
                    'message' => 'Ошибка',
                    'details' => $error,
                ];

                return false;

            } catch (\Exception $e) {
                $this->results['meta'] = [
                    'status' => 'error',
                    'name' => 'Meta API',
                    'message' => 'Ошибка подключения',
                    'details' => $e->getMessage(),
                ];

                return false;
            }
        });
    }

    private function checkTelegramApi(): void
    {
        $this->components->task('Telegram Bot API', function () {
            try {
                $token = Setting::get('telegram_bot_token');

                if (empty($token)) {
                    $this->results['telegram'] = [
                        'status' => 'warning',
                        'name' => 'Telegram Bot',
                        'message' => 'Не настроен',
                        'details' => 'Добавьте токен в настройках',
                    ];

                    return true;
                }

                $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

                if ($response->successful() && ($response->json('ok') ?? false)) {
                    $username = $response->json('result.username') ?? 'Connected';
                    $this->results['telegram'] = [
                        'status' => 'ok',
                        'name' => 'Telegram Bot',
                        'message' => 'Подключено',
                        'details' => "@{$username}",
                    ];

                    return true;
                }

                $error = $response->json('description') ?? 'Invalid token';
                $this->results['telegram'] = [
                    'status' => 'error',
                    'name' => 'Telegram Bot',
                    'message' => 'Ошибка',
                    'details' => $error,
                ];

                return false;

            } catch (\Exception $e) {
                $this->results['telegram'] = [
                    'status' => 'error',
                    'name' => 'Telegram Bot',
                    'message' => 'Ошибка подключения',
                    'details' => $e->getMessage(),
                ];

                return false;
            }
        });
    }

    private function checkGeminiApi(): void
    {
        $this->components->task('Gemini AI API', function () {
            try {
                $key = Setting::get('gemini_api_key');
                $enabled = Setting::get('ai_enabled');
                $enabled = $enabled === true || $enabled === 'true' || $enabled === '1';

                if (empty($key)) {
                    $this->results['gemini'] = [
                        'status' => 'warning',
                        'name' => 'Gemini AI',
                        'message' => 'Не настроен',
                        'details' => 'Добавьте API ключ в настройках',
                    ];

                    return true;
                }

                if (!$enabled) {
                    $this->results['gemini'] = [
                        'status' => 'warning',
                        'name' => 'Gemini AI',
                        'message' => 'Отключен',
                        'details' => 'Включите в настройках',
                    ];

                    return true;
                }

                // Простая проверка - пробуем получить список моделей
                $response = Http::timeout(10)
                    ->withHeader('x-goog-api-key', $key)
                    ->get('https://generativelanguage.googleapis.com/v1/models');

                if ($response->successful()) {
                    $this->results['gemini'] = [
                        'status' => 'ok',
                        'name' => 'Gemini AI',
                        'message' => 'Подключено',
                        'details' => 'API ключ валиден',
                    ];

                    return true;
                }

                $error = $response->json('error.message') ?? 'Invalid key';
                $this->results['gemini'] = [
                    'status' => 'error',
                    'name' => 'Gemini AI',
                    'message' => 'Ошибка',
                    'details' => $error,
                ];

                return false;

            } catch (\Exception $e) {
                $this->results['gemini'] = [
                    'status' => 'error',
                    'name' => 'Gemini AI',
                    'message' => 'Ошибка подключения',
                    'details' => $e->getMessage(),
                ];

                return false;
            }
        });
    }

    private function checkQueue(): void
    {
        $this->components->task('Очередь задач', function () {
            $driver = config('queue.default');

            try {
                // Получаем метрики очередей
                $metrics = $this->getQueueMetrics();

                $status = 'ok';
                $message = 'Работает';

                // Проверяем failed jobs
                if ($metrics['failed'] > 0) {
                    $status = 'warning';
                    $message = "{$metrics['failed']} failed jobs";
                }

                // Проверяем длину очередей
                $totalPending = array_sum($metrics['queues']);
                if ($totalPending > 100) {
                    $status = 'warning';
                    $message = "Очередь переполнена: {$totalPending}";
                }

                $queueDetails = [];
                foreach ($metrics['queues'] as $queue => $count) {
                    if ($count > 0) {
                        $queueDetails[] = "{$queue}: {$count}";
                    }
                }

                $this->results['queue'] = [
                    'status' => $status,
                    'name' => 'Очередь',
                    'message' => $message,
                    'details' => $queueDetails ? implode(', ', $queueDetails) : "Driver: {$driver}",
                    'metrics' => $metrics,
                ];

                return $status === 'ok';

            } catch (\Exception $e) {
                $this->results['queue'] = [
                    'status' => 'warning',
                    'name' => 'Очередь',
                    'message' => 'Не удалось получить метрики',
                    'details' => $e->getMessage(),
                ];

                return true;
            }
        });
    }

    /**
     * Получить метрики очередей.
     */
    public function getQueueMetrics(): array
    {
        $metrics = [
            'driver' => config('queue.default'),
            'queues' => [
                'default' => 0,
                'meta' => 0,
                'ai' => 0,
            ],
            'failed' => 0,
            'processed_today' => 0,
        ];

        try {
            // Для Redis
            if (config('queue.default') === 'redis') {
                $connection = config('queue.connections.redis.connection', 'default');
                $prefix = config('database.redis.options.prefix', '');

                foreach (array_keys($metrics['queues']) as $queue) {
                    try {
                        $key = $prefix."queues:{$queue}";
                        $metrics['queues'][$queue] = (int) Redis::llen($key);
                    } catch (\Exception $e) {
                        // Игнорируем
                    }
                }
            }

            // Failed jobs из БД
            $metrics['failed'] = DB::table('failed_jobs')->count();

        } catch (\Exception $e) {
            // Игнорируем
        }

        return $metrics;
    }

    private function checkScheduler(): void
    {
        $this->components->task('Планировщик задач', function () {
            $lastRun = cache('scheduler:last_run');

            if ($lastRun) {
                $ago = now()->diffForHumans($lastRun);
                $this->results['scheduler'] = [
                    'status' => 'ok',
                    'name' => 'Планировщик',
                    'message' => 'Работает',
                    'details' => "Последний запуск: {$ago}",
                ];
            } else {
                $this->results['scheduler'] = [
                    'status' => 'warning',
                    'name' => 'Планировщик',
                    'message' => 'Не запускался',
                    'details' => 'Проверьте cron или scheduler контейнер',
                ];
            }

            return true;
        });
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->components->info('📊 Результаты диагностики:');
        $this->newLine();

        $headers = ['Компонент', 'Статус', 'Сообщение', 'Детали'];
        $rows = [];

        foreach ($this->results as $result) {
            $statusIcon = match ($result['status']) {
                'ok' => '🟢',
                'warning' => '🟡',
                'error' => '🔴',
                default => '⚪',
            };

            $rows[] = [
                $result['name'],
                $statusIcon.' '.ucfirst($result['status']),
                $result['message'],
                substr($result['details'] ?? '', 0, 40),
            ];
        }

        $this->table($headers, $rows);
    }
}
