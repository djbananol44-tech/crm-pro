<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class CrmHealthCheck extends Command
{
    protected $signature = 'crm:check';
    protected $description = 'Полная диагностика всех систем CRM';

    protected array $results = [];

    public function handle(): int
    {
        $this->info('');
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║       🔍 CRM Pro — Диагностика системы                    ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');
        $this->info('');

        // Проверки
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkMetaApi();
        $this->checkTelegramBot();
        $this->checkGeminiAi();
        $this->checkDirectories();
        $this->checkQueue();

        // Результаты
        $this->displayResults();

        // Логируем
        SystemLog::info('system', 'Запущена диагностика системы', $this->results);

        // Возвращаем код ошибки если есть проблемы
        $hasErrors = collect($this->results)->contains(fn($r) => $r['status'] === '❌');
        
        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    protected function checkDatabase(): void
    {
        $this->info('🗄️  Проверка PostgreSQL...');

        try {
            DB::connection()->getPdo();
            $version = DB::selectOne("SELECT version()")->version ?? 'Unknown';
            $tables = DB::selectOne("SELECT count(*) as count FROM information_schema.tables WHERE table_schema = 'public'")->count;
            
            $this->results['database'] = [
                'name' => 'PostgreSQL',
                'status' => '✅',
                'message' => "Подключено • {$tables} таблиц",
            ];
        } catch (\Exception $e) {
            $this->results['database'] = [
                'name' => 'PostgreSQL',
                'status' => '❌',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkRedis(): void
    {
        $this->info('🔴 Проверка Redis...');

        try {
            $ping = Redis::ping();
            $info = Redis::info('memory');
            $usedMb = round(($info['used_memory'] ?? 0) / 1024 / 1024, 2);

            $this->results['redis'] = [
                'name' => 'Redis',
                'status' => '✅',
                'message' => "Подключено • {$usedMb} MB используется",
            ];
        } catch (\Exception $e) {
            $this->results['redis'] = [
                'name' => 'Redis',
                'status' => '❌',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkMetaApi(): void
    {
        $this->info('📘 Проверка Meta API...');

        $token = Setting::get('meta_access_token');
        $pageId = Setting::get('meta_page_id');

        if (empty($token) || empty($pageId)) {
            $this->results['meta_api'] = [
                'name' => 'Meta API',
                'status' => '⚠️',
                'message' => 'Не настроен (токен или Page ID отсутствует)',
            ];
            return;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("https://graph.facebook.com/v19.0/{$pageId}");

            if ($response->successful()) {
                $pageName = $response->json('name') ?? 'OK';
                $this->results['meta_api'] = [
                    'name' => 'Meta API',
                    'status' => '✅',
                    'message' => "Подключено • Страница: {$pageName}",
                ];
            } else {
                $error = $response->json('error.message') ?? 'Неизвестная ошибка';
                $this->results['meta_api'] = [
                    'name' => 'Meta API',
                    'status' => '❌',
                    'message' => "Ошибка: {$error}",
                ];
            }
        } catch (\Exception $e) {
            $this->results['meta_api'] = [
                'name' => 'Meta API',
                'status' => '❌',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkTelegramBot(): void
    {
        $this->info('🤖 Проверка Telegram Bot...');

        $token = Setting::get('telegram_bot_token');

        if (empty($token)) {
            $this->results['telegram'] = [
                'name' => 'Telegram Bot',
                'status' => '⚠️',
                'message' => 'Не настроен (токен отсутствует)',
            ];
            return;
        }

        try {
            $response = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful() && ($response->json('ok') ?? false)) {
                $username = $response->json('result.username');
                $this->results['telegram'] = [
                    'name' => 'Telegram Bot',
                    'status' => '✅',
                    'message' => "Подключено • @{$username}",
                ];
            } else {
                $error = $response->json('description') ?? 'Неверный токен';
                $this->results['telegram'] = [
                    'name' => 'Telegram Bot',
                    'status' => '❌',
                    'message' => "Ошибка: {$error}",
                ];
            }
        } catch (\Exception $e) {
            $this->results['telegram'] = [
                'name' => 'Telegram Bot',
                'status' => '❌',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkGeminiAi(): void
    {
        $this->info('🧠 Проверка Gemini AI...');

        $apiKey = Setting::get('gemini_api_key');
        $enabled = filter_var(Setting::get('ai_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);

        if (empty($apiKey)) {
            $this->results['gemini'] = [
                'name' => 'Gemini AI',
                'status' => '⚠️',
                'message' => 'Не настроен (API ключ отсутствует)',
            ];
            return;
        }

        if (!$enabled) {
            $this->results['gemini'] = [
                'name' => 'Gemini AI',
                'status' => '⚠️',
                'message' => 'Выключен (ai_enabled = false)',
            ];
            return;
        }

        $this->results['gemini'] = [
            'name' => 'Gemini AI',
            'status' => '✅',
            'message' => 'Настроен и включён',
        ];
    }

    protected function checkDirectories(): void
    {
        $this->info('📁 Проверка директорий...');

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
                $issues[] = basename($dir) . ' не существует';
            } elseif (!is_writable($dir)) {
                $issues[] = basename($dir) . ' не записываем';
            }
        }

        if (empty($issues)) {
            $this->results['directories'] = [
                'name' => 'Директории',
                'status' => '✅',
                'message' => 'Все директории доступны для записи',
            ];
        } else {
            $this->results['directories'] = [
                'name' => 'Директории',
                'status' => '❌',
                'message' => implode(', ', $issues),
            ];
        }
    }

    protected function checkQueue(): void
    {
        $this->info('📨 Проверка очереди...');

        try {
            $pending = Redis::llen('queues:default') ?? 0;
            $meta = Redis::llen('queues:meta') ?? 0;
            $ai = Redis::llen('queues:ai') ?? 0;
            $failed = DB::table('failed_jobs')->count();

            $status = $failed > 5 ? '⚠️' : '✅';
            $message = "default: {$pending}, meta: {$meta}, ai: {$ai}";
            
            if ($failed > 0) {
                $message .= " | ⚠️ {$failed} ошибок";
            }

            $this->results['queue'] = [
                'name' => 'Очередь',
                'status' => $status,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            $this->results['queue'] = [
                'name' => 'Очередь',
                'status' => '❌',
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }

    protected function displayResults(): void
    {
        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                     РЕЗУЛЬТАТЫ                            ');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('');

        $table = [];
        foreach ($this->results as $result) {
            $table[] = [
                $result['status'],
                $result['name'],
                $result['message'],
            ];
        }

        $this->table(['', 'Сервис', 'Статус'], $table);

        $this->info('');

        // Summary
        $ok = collect($this->results)->filter(fn($r) => $r['status'] === '✅')->count();
        $warn = collect($this->results)->filter(fn($r) => $r['status'] === '⚠️')->count();
        $err = collect($this->results)->filter(fn($r) => $r['status'] === '❌')->count();

        if ($err > 0) {
            $this->error("❌ Найдено {$err} критических проблем!");
        } elseif ($warn > 0) {
            $this->warn("⚠️  Есть {$warn} предупреждений, но система работает.");
        } else {
            $this->info("✅ Все системы работают нормально!");
        }

        $this->info('');
    }
}
