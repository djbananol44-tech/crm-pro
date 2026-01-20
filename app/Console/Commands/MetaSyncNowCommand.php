<?php

namespace App\Console\Commands;

use App\Jobs\SyncMetaConversations;
use App\Services\MetaApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetaSyncNowCommand extends Command
{
    /**
     * Название и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'meta:sync-now 
                            {--platform= : Платформа для синхронизации (messenger/instagram)}
                            {--dry-run : Тестовый запуск без сохранения в БД}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Мгновенный запуск синхронизации с Meta API для проверки интеграции';

    /**
     * Выполнить консольную команду.
     */
    public function handle(MetaApiService $metaApi): int
    {
        $platform = $this->option('platform');
        $dryRun = $this->option('dry-run');

        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║     СИНХРОНИЗАЦИЯ С META API — РУЧНОЙ ЗАПУСК              ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Валидация платформы
        if ($platform && !in_array($platform, ['messenger', 'instagram'])) {
            $this->error('❌ Ошибка: неверная платформа. Используйте: messenger или instagram');
            return self::FAILURE;
        }

        // Проверка конфигурации
        $this->info('🔧 Проверка конфигурации...');
        
        $pageId = config('services.meta.page_id');
        $accessToken = config('services.meta.access_token');

        if (empty($pageId)) {
            $this->error('❌ Ошибка: META_PAGE_ID не настроен в .env');
            return self::FAILURE;
        }

        if (empty($accessToken)) {
            $this->error('❌ Ошибка: META_ACCESS_TOKEN не настроен в .env');
            return self::FAILURE;
        }

        $this->info("   ✓ PAGE_ID: {$pageId}");
        $this->info("   ✓ ACCESS_TOKEN: " . substr($accessToken, 0, 20) . '...');
        $this->newLine();

        // Информация о запуске
        $this->info('📋 Параметры запуска:');
        $this->info('   • Платформа: ' . ($platform ?: 'все'));
        $this->info('   • Режим: ' . ($dryRun ? 'тестовый (dry-run)' : 'боевой'));
        $this->info('   • Время: ' . now()->format('d.m.Y H:i:s'));
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  Тестовый режим: данные НЕ будут сохранены в базу данных');
            $this->newLine();
        }

        // Запрос подтверждения
        if (!$this->confirm('Начать синхронизацию?', true)) {
            $this->info('Отменено пользователем.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('🚀 Запуск синхронизации...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            // Тестовый запуск — только проверка API
            if ($dryRun) {
                $this->testApiConnection($metaApi, $platform);
            } else {
                // Боевой запуск
                $job = new SyncMetaConversations($platform);
                $job->handle($metaApi);
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('╔════════════════════════════════════════════════════════════╗');
            $this->info('║     ✅ СИНХРОНИЗАЦИЯ УСПЕШНО ЗАВЕРШЕНА                     ║');
            $this->info('╚════════════════════════════════════════════════════════════╝');
            $this->info("   Время выполнения: {$duration} сек.");
            $this->newLine();

            Log::info('meta:sync-now: Синхронизация завершена успешно', [
                'platform' => $platform,
                'dry_run' => $dryRun,
                'duration' => $duration,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('╔════════════════════════════════════════════════════════════╗');
            $this->error('║     ❌ ОШИБКА СИНХРОНИЗАЦИИ                                ║');
            $this->error('╚════════════════════════════════════════════════════════════╝');
            $this->error("   Сообщение: {$e->getMessage()}");
            $this->newLine();

            if ($this->option('verbose')) {
                $this->error('   Стек вызовов:');
                $this->line($e->getTraceAsString());
            }

            Log::error('meta:sync-now: Ошибка синхронизации', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Тестовый запуск — проверка подключения к API.
     */
    protected function testApiConnection(MetaApiService $metaApi, ?string $platform): void
    {
        $this->info('📡 Тестирование подключения к Meta Graph API...');
        $this->newLine();

        // Тест получения бесед
        $this->info('   → Запрос списка бесед...');
        
        try {
            $conversations = $metaApi->getConversations($platform);
            $count = count($conversations);
            
            $this->info("   ✓ Получено бесед: {$count}");

            if ($count > 0) {
                $this->newLine();
                $this->info('   📝 Примеры бесед:');
                
                foreach (array_slice($conversations, 0, 3) as $conv) {
                    $id = $conv['id'] ?? 'N/A';
                    $updated = $conv['updated_time'] ?? 'N/A';
                    $this->info("      • ID: {$id}");
                    $this->info("        Обновлено: {$updated}");
                    
                    // Попробуем получить PSID участника
                    $psid = $metaApi->extractParticipantPsid($conv);
                    if ($psid) {
                        $this->info("        PSID участника: {$psid}");
                        
                        // Тест получения профиля
                        try {
                            $profile = $metaApi->getUserProfile($psid);
                            $name = $profile['name'] ?? 'Не указано';
                            $this->info("        Имя: {$name}");
                        } catch (\Exception $e) {
                            $this->warn("        ⚠️ Не удалось получить профиль: {$e->getMessage()}");
                        }
                    }
                    $this->newLine();
                }
            }

        } catch (\Exception $e) {
            $this->error("   ✗ Ошибка: {$e->getMessage()}");
            throw $e;
        }
    }
}
