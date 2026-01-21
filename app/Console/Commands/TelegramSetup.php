<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\SystemLog;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Команда настройки Telegram бота.
 *
 * Поддерживает два режима:
 * - webhook: получение обновлений через HTTP webhook (требует HTTPS)
 * - polling: получение обновлений через long polling (bot_worker)
 */
class TelegramSetup extends Command
{
    protected $signature = 'telegram:setup 
                            {--mode= : Режим работы (webhook|polling)}
                            {--force : Принудительная установка без проверок}
                            {--status : Показать текущий статус}';

    protected $description = 'Настройка Telegram бота (webhook или polling режим)';

    public function handle(TelegramService $telegram): int
    {
        // Показать статус
        if ($this->option('status')) {
            return $this->showStatus($telegram);
        }

        $this->info('🤖 Настройка Telegram бота');
        $this->newLine();

        // Проверяем токен
        $token = Setting::get('telegram_bot_token');
        if (empty($token)) {
            $this->error('❌ Токен бота не настроен!');
            $this->line('   Установите токен в админ-панели: /admin/settings');

            return Command::FAILURE;
        }

        // Проверяем подключение
        $this->info('🔍 Проверка подключения к Telegram API...');
        $connectionTest = $telegram->testConnection();

        if (!$connectionTest['success']) {
            $this->error('❌ '.$connectionTest['message']);

            return Command::FAILURE;
        }

        $botUsername = $connectionTest['bot_username'];
        $this->info("✅ Бот подключен: @{$botUsername}");
        $this->newLine();

        // Определяем режим
        $mode = $this->option('mode') ?? Setting::get('telegram_mode', 'polling');

        if (!$this->option('mode')) {
            $mode = $this->choice(
                'Выберите режим работы бота:',
                ['webhook' => 'Webhook (требует HTTPS)', 'polling' => 'Long Polling (docker worker)'],
                $mode
            );
        }

        // Сохраняем режим
        Setting::set('telegram_mode', $mode);

        if ($mode === 'webhook') {
            return $this->setupWebhook($telegram);
        } else {
            return $this->setupPolling($telegram);
        }
    }

    /**
     * Настройка webhook режима.
     */
    protected function setupWebhook(TelegramService $telegram): int
    {
        $this->info('📡 Настройка Webhook режима...');

        $webhookUrl = url('/api/webhooks/telegram');
        $this->line("   URL: {$webhookUrl}");

        // Проверяем HTTPS
        if (!str_starts_with($webhookUrl, 'https://') && !$this->option('force')) {
            $this->error('❌ Webhook требует HTTPS!');
            $this->line('   Текущий APP_URL: '.config('app.url'));
            $this->newLine();
            $this->warn('Решения:');
            $this->line('   1. Настройте HTTPS (Traefik/Certbot)');
            $this->line('   2. Используйте polling: php artisan telegram:setup --mode=polling');
            $this->line('   3. Принудительно: php artisan telegram:setup --mode=webhook --force');

            return Command::FAILURE;
        }

        // Проверяем доступность URL
        if (!$this->option('force')) {
            $this->info('🔍 Проверка доступности URL...');

            try {
                $response = Http::timeout(10)->get($webhookUrl);
                $this->info("   HTTP статус: {$response->status()}");
            } catch (\Exception $e) {
                $this->warn("   ⚠️ URL недоступен извне: {$e->getMessage()}");

                if (!$this->confirm('Продолжить установку webhook?', false)) {
                    return Command::FAILURE;
                }
            }
        }

        // Устанавливаем webhook
        $this->info('📤 Установка webhook...');
        $result = $telegram->setWebhook($webhookUrl);

        if ($result['success']) {
            $this->info('✅ '.$result['message']);

            SystemLog::bot('info', 'Telegram webhook установлен', [
                'url' => $webhookUrl,
                'mode' => 'webhook',
            ]);

            $this->newLine();
            $this->table(['Параметр', 'Значение'], [
                ['Режим', 'Webhook'],
                ['URL', $webhookUrl],
                ['bot_worker', '❌ Не требуется'],
            ]);

            $this->newLine();
            $this->warn('⚠️  Остановите bot_worker если он запущен:');
            $this->line('    docker compose stop bot_worker');

            return Command::SUCCESS;
        } else {
            $this->error('❌ '.$result['message']);

            SystemLog::bot('error', 'Ошибка установки webhook', [
                'url' => $webhookUrl,
                'error' => $result['message'],
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Настройка polling режима.
     */
    protected function setupPolling(TelegramService $telegram): int
    {
        $this->info('🔄 Настройка Polling режима...');

        // Удаляем webhook
        $this->info('📤 Удаление webhook...');
        $result = $telegram->deleteWebhook();

        if ($result['success']) {
            $this->info('✅ Webhook удалён');
        } else {
            $this->warn('⚠️  '.$result['message']);
        }

        // Сбрасываем offset
        $this->resetPollingOffset();

        SystemLog::bot('info', 'Telegram переключен на polling', [
            'mode' => 'polling',
        ]);

        $this->newLine();
        $this->table(['Параметр', 'Значение'], [
            ['Режим', 'Long Polling'],
            ['Webhook', '❌ Удалён'],
            ['bot_worker', '✅ Требуется'],
        ]);

        $this->newLine();
        $this->info('Запустите bot_worker:');
        $this->line('    docker compose up -d bot_worker');
        $this->line('    # или');
        $this->line('    php artisan telegram:worker');

        return Command::SUCCESS;
    }

    /**
     * Показать текущий статус.
     */
    protected function showStatus(TelegramService $telegram): int
    {
        $this->info('📊 Статус Telegram бота');
        $this->newLine();

        $token = Setting::get('telegram_bot_token');
        $mode = Setting::get('telegram_mode', 'polling');

        if (empty($token)) {
            $this->error('❌ Токен не настроен');

            return Command::FAILURE;
        }

        // Проверяем бота
        $connectionTest = $telegram->testConnection();
        $botStatus = $connectionTest['success']
            ? "✅ @{$connectionTest['bot_username']}"
            : "❌ {$connectionTest['message']}";

        // Проверяем webhook
        $webhookInfo = $this->getWebhookInfo($token);
        $webhookStatus = !empty($webhookInfo['url'])
            ? "✅ {$webhookInfo['url']}"
            : '❌ Не установлен';

        // Проверяем offset
        $lastOffset = $this->getPollingOffset();

        $this->table(['Параметр', 'Значение'], [
            ['Бот', $botStatus],
            ['Режим (настройка)', $mode],
            ['Webhook', $webhookStatus],
            ['Polling offset', $lastOffset ?: 'Не сохранён'],
        ]);

        if (!empty($webhookInfo['url']) && $mode === 'polling') {
            $this->newLine();
            $this->warn('⚠️  Webhook установлен, но режим = polling');
            $this->line('   Выполните: php artisan telegram:setup --mode=polling');
        }

        if (empty($webhookInfo['url']) && $mode === 'webhook') {
            $this->newLine();
            $this->warn('⚠️  Webhook не установлен, но режим = webhook');
            $this->line('   Выполните: php artisan telegram:setup --mode=webhook');
        }

        return Command::SUCCESS;
    }

    /**
     * Получить информацию о webhook.
     */
    protected function getWebhookInfo(string $token): array
    {
        try {
            $response = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getWebhookInfo");

            if ($response->successful()) {
                return $response->json('result') ?? [];
            }
        } catch (\Exception $e) {
            // Игнорируем
        }

        return [];
    }

    /**
     * Получить последний offset из Redis.
     */
    protected function getPollingOffset(): ?int
    {
        try {
            return cache()->get('telegram:polling:offset');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Сбросить offset.
     */
    protected function resetPollingOffset(): void
    {
        try {
            cache()->forget('telegram:polling:offset');
            $this->info('✅ Polling offset сброшен');
        } catch (\Exception $e) {
            $this->warn('⚠️  Не удалось сбросить offset: '.$e->getMessage());
        }
    }
}
