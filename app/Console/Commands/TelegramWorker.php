<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramController;
use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot Worker (Long Polling режим).
 *
 * Особенности:
 * - Offset хранится в Redis/Cache для переживания restart
 * - Graceful shutdown по SIGTERM/SIGINT
 * - Автоматическая проверка режима (polling vs webhook)
 */
class TelegramWorker extends Command
{
    protected $signature = 'telegram:worker 
                            {--timeout=60 : Таймаут для long polling}
                            {--force : Запустить даже если режим = webhook}';

    protected $description = 'Запуск Telegram бота в режиме Long Polling';

    protected bool $running = true;

    /**
     * Ключ для хранения offset в Cache/Redis.
     */
    protected const OFFSET_CACHE_KEY = 'telegram:polling:offset';

    /**
     * TTL для offset (7 дней в секундах).
     */
    protected const OFFSET_TTL = 604800;

    public function handle(): int
    {
        $this->info('🤖 Запуск Telegram Bot Worker...');

        // Проверяем режим
        $mode = Setting::get('telegram_mode', 'polling');

        if ($mode === 'webhook' && !$this->option('force')) {
            $this->warn('⚠️  Режим = webhook, bot_worker не требуется');
            $this->line('   Используйте: php artisan telegram:setup --mode=polling');
            $this->line('   Или запустите с --force');

            return Command::SUCCESS;
        }

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
            $this->error('❌ Ошибка подключения: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Удаляем webhook для работы через polling
        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/deleteWebhook");

        // Восстанавливаем offset из кэша
        $lastOffset = $this->getStoredOffset();
        $this->info('📍 Последний offset: '.($lastOffset ?: 'не сохранён'));

        $this->newLine();
        $this->info('🔄 Запущен Long Polling. Для остановки нажмите Ctrl+C');
        $this->newLine();

        SystemLog::bot('info', 'Telegram Worker запущен', [
            'bot' => $botInfo['username'],
            'mode' => 'long_polling',
            'restored_offset' => $lastOffset,
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
                $updates = $this->getUpdates($token, $timeout, $lastOffset);

                foreach ($updates as $update) {
                    $updateId = $update['update_id'];

                    // Пропускаем если уже обработали (защита от дублей)
                    if ($lastOffset && $updateId <= $lastOffset) {
                        continue;
                    }

                    $this->processUpdate($update, $controller);

                    // Сохраняем offset ПОСЛЕ успешной обработки
                    $lastOffset = $updateId;
                    $this->storeOffset($lastOffset);
                }

            } catch (\Exception $e) {
                if ($this->running) {
                    $this->error('❌ Ошибка: '.$e->getMessage());
                    SystemLog::bot('error', 'Ошибка в Worker', ['error' => $e->getMessage()]);
                    sleep(5); // Пауза перед повтором
                }
            }
        }

        // Сохраняем offset при остановке
        if ($lastOffset) {
            $this->storeOffset($lastOffset);
            $this->info("📍 Offset сохранён: {$lastOffset}");
        }

        $this->info('👋 Telegram Worker остановлен');
        SystemLog::bot('info', 'Telegram Worker остановлен', ['last_offset' => $lastOffset]);

        return Command::SUCCESS;
    }

    /**
     * Получить обновления от Telegram API.
     */
    protected function getUpdates(string $token, int $timeout, ?int $lastOffset): array
    {
        $response = Http::timeout($timeout + 10)
            ->post("https://api.telegram.org/bot{$token}/getUpdates", [
                'offset' => $lastOffset ? $lastOffset + 1 : null,
                'timeout' => $timeout,
                'allowed_updates' => ['message', 'callback_query'],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Ошибка API: '.$response->status());
        }

        $data = $response->json();

        if (!($data['ok'] ?? false)) {
            throw new \Exception('API вернул ошибку: '.($data['description'] ?? 'Неизвестно'));
        }

        return $data['result'] ?? [];
    }

    /**
     * Обработать одно обновление.
     */
    protected function processUpdate(array $update, TelegramController $controller): void
    {
        $updateId = $update['update_id'] ?? 'unknown';

        // Определяем тип обновления
        if (isset($update['message'])) {
            $text = $update['message']['text'] ?? '[не текст]';
            $from = $update['message']['from']['username'] ?? $update['message']['from']['first_name'] ?? 'unknown';

            $this->line("📨 [{$updateId}] @{$from}: ".mb_substr($text, 0, 50));

        } elseif (isset($update['callback_query'])) {
            $callbackData = $update['callback_query']['data'] ?? '';
            $from = $update['callback_query']['from']['username'] ?? 'unknown';

            $this->line("🔘 [{$updateId}] @{$from} нажал: {$callbackData}");
        }

        // Создаём фейковый Request и обрабатываем
        try {
            $request = new Request;
            $request->setMethod('POST');
            $request->merge($update);

            $controller->webhook($request);

        } catch (\Exception $e) {
            $this->error('   ⚠️ Ошибка обработки: '.$e->getMessage());
            SystemLog::bot('error', 'Ошибка обработки update', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
            ]);
            // Не прерываем цикл — offset всё равно сохранится
        }
    }

    /**
     * Получить сохранённый offset.
     */
    protected function getStoredOffset(): ?int
    {
        try {
            return Cache::get(self::OFFSET_CACHE_KEY);
        } catch (\Exception $e) {
            $this->warn("⚠️ Не удалось получить offset из кэша: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Сохранить offset в кэш.
     */
    protected function storeOffset(int $offset): void
    {
        try {
            Cache::put(self::OFFSET_CACHE_KEY, $offset, self::OFFSET_TTL);
        } catch (\Exception $e) {
            $this->warn("⚠️ Не удалось сохранить offset: {$e->getMessage()}");
        }
    }
}
