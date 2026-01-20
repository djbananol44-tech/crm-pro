<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook 
                            {action=set : Действие: set или delete}
                            {--url= : URL для webhook (по умолчанию берётся из APP_URL)}';

    protected $description = 'Установить или удалить Telegram webhook';

    public function handle(): int
    {
        $botToken = Setting::get('telegram_bot_token');

        if (empty($botToken)) {
            $this->error('❌ Telegram Bot Token не настроен!');
            $this->line('Установите его в админ-панели: Настройки → Telegram');
            return Command::FAILURE;
        }

        $action = $this->argument('action');

        if ($action === 'set') {
            return $this->setWebhook($botToken);
        }

        if ($action === 'delete') {
            return $this->deleteWebhook($botToken);
        }

        $this->error("Неизвестное действие: {$action}");
        return Command::FAILURE;
    }

    protected function setWebhook(string $botToken): int
    {
        $webhookUrl = $this->option('url') ?: url('/api/webhooks/telegram');

        $this->info("🔗 Устанавливаю webhook: {$webhookUrl}");

        $response = Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        if ($response->successful() && $response->json('ok')) {
            $this->info('✅ Webhook успешно установлен!');
            
            $this->newLine();
            $this->table(['Параметр', 'Значение'], [
                ['Webhook URL', $webhookUrl],
                ['Описание', $response->json('description')],
            ]);

            return Command::SUCCESS;
        }

        $this->error('❌ Ошибка установки webhook:');
        $this->error($response->json('description') ?? 'Unknown error');
        return Command::FAILURE;
    }

    protected function deleteWebhook(string $botToken): int
    {
        $this->info('🗑️ Удаляю webhook...');

        $response = Http::post("https://api.telegram.org/bot{$botToken}/deleteWebhook");

        if ($response->successful() && $response->json('ok')) {
            $this->info('✅ Webhook удалён!');
            return Command::SUCCESS;
        }

        $this->error('❌ Ошибка удаления webhook:');
        $this->error($response->json('description') ?? 'Unknown error');
        return Command::FAILURE;
    }
}
