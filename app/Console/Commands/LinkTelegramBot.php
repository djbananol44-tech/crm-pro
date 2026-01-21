<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LinkTelegramBot extends Command
{
    protected $signature = 'crm:link-bot {--webhook : Установить webhook вместо polling}';
    protected $description = 'Настройка и подключение Telegram бота';

    public function handle(): int
    {
        $this->info('🤖 Настройка Telegram бота...');
        $this->newLine();

        // Проверяем токен
        $token = Setting::get('telegram_bot_token');

        if (empty($token)) {
            $this->warn('⚠️  Токен Telegram бота не настроен.');
            $this->info('   Установите токен в админ-панели: /admin/settings');
            
            SystemLog::bot('warning', 'Попытка настройки бота без токена');
            return Command::SUCCESS;
        }

        // Проверяем подключение к API
        $this->info('🔍 Проверка подключения к Telegram API...');
        
        try {
            $response = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getMe");

            if (!$response->successful() || !($response->json('ok') ?? false)) {
                $this->error('❌ Неверный токен бота!');
                $this->error('   Ошибка: ' . ($response->json('description') ?? 'Неизвестная ошибка'));
                
                SystemLog::bot('error', 'Неверный токен бота', [
                    'response' => $response->json(),
                ]);
                return Command::FAILURE;
            }

            $botInfo = $response->json('result');
            $this->info("✅ Бот подключен: @{$botInfo['username']} ({$botInfo['first_name']})");
            
            SystemLog::bot('info', 'Бот успешно подключен', [
                'username' => $botInfo['username'],
                'bot_id' => $botInfo['id'],
            ]);

        } catch (\Exception $e) {
            $this->error('❌ Ошибка подключения к Telegram API: ' . $e->getMessage());
            SystemLog::bot('error', 'Ошибка подключения к API', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        // Настройка Webhook или Polling
        if ($this->option('webhook')) {
            return $this->setupWebhook($token);
        } else {
            return $this->setupPolling($token);
        }
    }

    protected function setupWebhook(string $token): int
    {
        $this->newLine();
        $this->info('🌐 Настройка Webhook...');

        $appUrl = config('app.url');
        $webhookUrl = rtrim($appUrl, '/') . '/api/webhooks/telegram';

        try {
            // Удаляем старый webhook
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/deleteWebhook");

            // Устанавливаем новый
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$token}/setWebhook", [
                    'url' => $webhookUrl,
                    'allowed_updates' => ['message', 'callback_query'],
                    'drop_pending_updates' => true,
                ]);

            if ($response->json('ok')) {
                $this->info("✅ Webhook установлен: {$webhookUrl}");
                
                SystemLog::bot('info', 'Webhook установлен', ['url' => $webhookUrl]);
                
                $this->newLine();
                $this->warn('⚠️  Убедитесь, что URL доступен из интернета!');
                $this->info("   Проверьте: {$webhookUrl}");
                
                return Command::SUCCESS;
            } else {
                $this->error('❌ Ошибка установки webhook: ' . ($response->json('description') ?? 'Неизвестно'));
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function setupPolling(string $token): int
    {
        $this->newLine();
        $this->info('🔄 Настройка Long Polling...');

        try {
            // Удаляем webhook для работы через polling
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$token}/deleteWebhook", [
                    'drop_pending_updates' => true,
                ]);

            if ($response->json('ok')) {
                $this->info('✅ Webhook удалён, бот готов к Long Polling');
                
                SystemLog::bot('info', 'Бот настроен для Long Polling');
                
                $this->newLine();
                $this->info('🚀 Для запуска бота используйте:');
                $this->comment('   php artisan telegram:worker');
                
                return Command::SUCCESS;
            } else {
                $this->error('❌ Ошибка настройки polling');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
