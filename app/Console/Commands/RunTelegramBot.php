<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunTelegramBot extends Command
{
    protected $signature = 'telegram:run {--webhook : Установить webhook вместо long polling}';
    protected $description = 'Запустить Telegram бота (Long Polling или Webhook)';

    private ?string $token = null;
    private bool $running = true;

    public function handle(): int
    {
        $this->token = Setting::get('telegram_bot_token');

        if (empty($this->token)) {
            $this->error('❌ Токен Telegram бота не настроен в БД');
            $this->info('💡 Добавьте токен в админке: /admin/settings');
            return Command::FAILURE;
        }

        // Валидация токена
        if (!$this->validateToken()) {
            $this->error('❌ Токен Telegram бота недействителен');
            return Command::FAILURE;
        }

        $this->info('✅ Токен валиден. Бот: ' . $this->getBotInfo());

        if ($this->option('webhook')) {
            return $this->setupWebhook();
        }

        return $this->runLongPolling();
    }

    private function validateToken(): bool
    {
        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$this->token}/getMe");
            return $response->successful() && ($response->json('ok') ?? false);
        } catch (\Exception $e) {
            Log::error('Telegram token validation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getBotInfo(): string
    {
        try {
            $response = Http::get("https://api.telegram.org/bot{$this->token}/getMe");
            $bot = $response->json('result', []);
            return $bot['username'] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function setupWebhook(): int
    {
        $webhookUrl = config('app.url') . '/api/webhooks/telegram';
        
        $this->info("📡 Устанавливаю webhook: {$webhookUrl}");

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->token}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query'],
            ]);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                $this->info('✅ Webhook успешно установлен');
                
                // Сохраняем статус
                Setting::set('telegram_webhook_active', 'true');
                Setting::set('telegram_webhook_url', $webhookUrl);
                
                return Command::SUCCESS;
            }

            $this->error('❌ Ошибка установки webhook: ' . ($response->json('description') ?? 'Unknown'));
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('❌ Исключение: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function runLongPolling(): int
    {
        // Удаляем webhook если был
        Http::post("https://api.telegram.org/bot{$this->token}/deleteWebhook");
        Setting::set('telegram_webhook_active', 'false');

        $this->info('🔄 Запускаю Long Polling...');
        $this->info('   Нажмите Ctrl+C для остановки');

        // Обработка сигналов для graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT, fn() => $this->running = false);
        }

        $offset = 0;
        $errors = 0;
        $maxErrors = 10;

        while ($this->running) {
            try {
                $response = Http::timeout(35)->get("https://api.telegram.org/bot{$this->token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 30,
                    'allowed_updates' => ['message', 'callback_query'],
                ]);

                if (!$response->successful()) {
                    throw new \Exception('HTTP Error: ' . $response->status());
                }

                $data = $response->json();
                
                if (!($data['ok'] ?? false)) {
                    throw new \Exception('API Error: ' . ($data['description'] ?? 'Unknown'));
                }

                $updates = $data['result'] ?? [];
                $errors = 0; // Сброс счетчика ошибок при успехе

                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $this->processUpdate($update);
                }

                // Проверяем сигналы
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error('Telegram polling error', [
                    'error' => $e->getMessage(),
                    'attempt' => $errors,
                ]);

                $this->warn("⚠️ Ошибка #{$errors}: {$e->getMessage()}");

                if ($errors >= $maxErrors) {
                    $this->error("❌ Превышен лимит ошибок ({$maxErrors}). Остановка.");
                    return Command::FAILURE;
                }

                // Экспоненциальная задержка
                $delay = min(30, pow(2, $errors));
                $this->info("   Повтор через {$delay} сек...");
                sleep($delay);
            }
        }

        $this->info('👋 Бот остановлен');
        return Command::SUCCESS;
    }

    private function processUpdate(array $update): void
    {
        try {
            $updateId = $update['update_id'] ?? 'unknown';
            
            if (isset($update['message'])) {
                $this->processMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->processCallbackQuery($update['callback_query']);
            }

            Log::info('Telegram update processed', ['update_id' => $updateId]);

        } catch (\Exception $e) {
            Log::error('Error processing update', [
                'update' => $update,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if (!$chatId) return;

        // Передаем обработку в TelegramService
        $service = app(TelegramService::class);

        if (str_starts_with($text, '/')) {
            $command = explode(' ', $text)[0];
            $this->info("📩 Команда: {$command} от {$chatId}");
            
            // Эмулируем webhook запрос
            $webhookData = ['message' => $message];
            
            // Вызываем обработчик через HTTP (или напрямую через контроллер)
            app(\App\Http\Controllers\TelegramController::class)->handle(
                new \Illuminate\Http\Request($webhookData)
            );
        }
    }

    private function processCallbackQuery(array $callbackQuery): void
    {
        $chatId = $callbackQuery['from']['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        $this->info("🔘 Callback: {$data} от {$chatId}");

        // Передаем в контроллер
        $webhookData = ['callback_query' => $callbackQuery];
        
        app(\App\Http\Controllers\TelegramController::class)->handle(
            new \Illuminate\Http\Request($webhookData)
        );
    }
}
