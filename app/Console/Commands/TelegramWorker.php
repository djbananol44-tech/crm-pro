<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramController;
use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramWorker extends Command
{
    protected $signature = 'telegram:worker {--timeout=60 : Таймаут для long polling}';
    protected $description = 'Запуск Telegram бота в режиме Long Polling';

    protected bool $running = true;
    protected int $lastUpdateId = 0;

    public function handle(): int
    {
        $this->info('🤖 Запуск Telegram Bot Worker...');
        
        // Проверяем токен
        $token = Setting::get('telegram_bot_token');

        if (empty($token)) {
            $this->error('❌ Токен Telegram бота не настроен!');
            $this->info('   Установите токен в админ-панели: /admin/settings');
            return Command::FAILURE;
        }

        // Проверяем API
        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
            
            if (!$response->successful() || !($response->json('ok') ?? false)) {
                $this->error('❌ Неверный токен бота!');
                return Command::FAILURE;
            }

            $botInfo = $response->json('result');
            $this->info("✅ Подключен как @{$botInfo['username']}");
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка подключения: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Удаляем webhook для работы через polling
        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/deleteWebhook");

        $this->newLine();
        $this->info('🔄 Запущен Long Polling. Для остановки нажмите Ctrl+C');
        $this->newLine();

        SystemLog::bot('info', 'Telegram Worker запущен', [
            'bot' => $botInfo['username'],
            'mode' => 'long_polling',
        ]);

        // Обработка сигналов для graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->running = false;
                $this->warn("\n🛑 Получен сигнал остановки...");
            });
            pcntl_signal(SIGTERM, function () {
                $this->running = false;
            });
        }

        // Основной цикл
        $timeout = (int) $this->option('timeout');
        $controller = app(TelegramController::class);

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                $updates = $this->getUpdates($token, $timeout);

                foreach ($updates as $update) {
                    $this->processUpdate($update, $controller);
                    $this->lastUpdateId = $update['update_id'];
                }

            } catch (\Exception $e) {
                if ($this->running) {
                    $this->error('❌ Ошибка: ' . $e->getMessage());
                    SystemLog::bot('error', 'Ошибка в Worker', ['error' => $e->getMessage()]);
                    sleep(5); // Пауза перед повтором
                }
            }
        }

        $this->info('👋 Telegram Worker остановлен');
        SystemLog::bot('info', 'Telegram Worker остановлен');

        return Command::SUCCESS;
    }

    protected function getUpdates(string $token, int $timeout): array
    {
        $response = Http::timeout($timeout + 10)
            ->post("https://api.telegram.org/bot{$token}/getUpdates", [
                'offset' => $this->lastUpdateId + 1,
                'timeout' => $timeout,
                'allowed_updates' => ['message', 'callback_query'],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Ошибка API: ' . $response->status());
        }

        $data = $response->json();

        if (!($data['ok'] ?? false)) {
            throw new \Exception('API вернул ошибку: ' . ($data['description'] ?? 'Неизвестно'));
        }

        return $data['result'] ?? [];
    }

    protected function processUpdate(array $update, TelegramController $controller): void
    {
        $updateId = $update['update_id'] ?? 'unknown';

        // Определяем тип обновления
        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'] ?? null;
            $text = $update['message']['text'] ?? '[не текст]';
            $from = $update['message']['from']['username'] ?? $update['message']['from']['first_name'] ?? 'unknown';
            
            $this->line("📨 [{$updateId}] @{$from}: {$text}");
            
        } elseif (isset($update['callback_query'])) {
            $callbackData = $update['callback_query']['data'] ?? '';
            $from = $update['callback_query']['from']['username'] ?? 'unknown';
            
            $this->line("🔘 [{$updateId}] @{$from} нажал: {$callbackData}");
        }

        // Создаём фейковый Request и обрабатываем
        try {
            $request = new Request();
            $request->setMethod('POST');
            $request->merge($update);

            $controller->webhook($request);
            
        } catch (\Exception $e) {
            $this->error("   ⚠️ Ошибка обработки: " . $e->getMessage());
            SystemLog::bot('error', 'Ошибка обработки update', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
