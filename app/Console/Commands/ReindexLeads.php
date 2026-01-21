<?php

namespace App\Console\Commands;

use App\Models\Deal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Команда для полной переиндексации search_vector во всех deals.
 *
 * Использование:
 *   php artisan crm:reindex-leads              # Переиндексировать все
 *   php artisan crm:reindex-leads --chunk=500  # С кастомным размером chunk
 *   php artisan crm:reindex-leads --dry-run    # Только показать статистику
 *
 * @see docs/search.md
 */
class ReindexLeads extends Command
{
    protected $signature = 'crm:reindex-leads 
                            {--chunk=1000 : Размер chunk для batch update}
                            {--dry-run : Показать статистику без изменений}';

    protected $description = 'Переиндексировать search_vector для всех deals (полнотекстовый поиск)';

    public function handle(): int
    {
        $this->info('');
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║           🔍 JGGL CRM — Reindex Leads                     ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');
        $this->info('');

        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

        // Статистика
        $totalDeals = Deal::count();
        $dealsWithVector = Deal::whereNotNull('search_vector')->count();
        $dealsWithoutVector = $totalDeals - $dealsWithVector;

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Всего deals', number_format($totalDeals)],
                ['С search_vector', number_format($dealsWithVector)],
                ['Без search_vector', number_format($dealsWithoutVector)],
                ['Chunk size', number_format($chunkSize)],
            ]
        );

        if ($dryRun) {
            $this->warn('🔸 Dry run mode — изменения не будут применены');

            return self::SUCCESS;
        }

        if ($totalDeals === 0) {
            $this->info('✅ Нет deals для индексации');

            return self::SUCCESS;
        }

        if (!$this->confirm("Переиндексировать {$totalDeals} deals?", true)) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $this->info('');
        $this->info("🚀 Начинаю переиндексацию ({$chunkSize} записей за раз)...");
        $this->info('');

        $bar = $this->output->createProgressBar($totalDeals);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% | %memory:6s%');
        $bar->start();

        $processed = 0;
        $errors = 0;

        // Используем chunk для экономии памяти
        Deal::query()
            ->select('id')
            ->orderBy('id')
            ->chunk($chunkSize, function ($deals) use (&$processed, &$errors, $bar) {
                $ids = $deals->pluck('id')->toArray();

                try {
                    // UPDATE с touch updated_at чтобы триггер сработал
                    // Но не меняем реальный updated_at
                    DB::statement("
                        UPDATE deals 
                        SET search_vector = (
                            SELECT 
                                setweight(to_tsvector('russian', coalesce(c.name, '')), 'A') ||
                                setweight(to_tsvector('russian', coalesce(c.first_name, '')), 'A') ||
                                setweight(to_tsvector('russian', coalesce(c.last_name, '')), 'A') ||
                                setweight(to_tsvector('russian', coalesce(deals.ai_summary, '')), 'B') ||
                                setweight(to_tsvector('russian', coalesce(deals.ai_intent, '')), 'B') ||
                                setweight(to_tsvector('russian', coalesce(deals.comment, '')), 'C') ||
                                setweight(to_tsvector('russian', coalesce(deals.last_message_text, '')), 'C') ||
                                setweight(to_tsvector('simple', coalesce(c.psid, '')), 'D') ||
                                setweight(to_tsvector('simple', coalesce(deals.status, '')), 'D')
                            FROM contacts c WHERE c.id = deals.contact_id
                        )
                        WHERE deals.id IN (".implode(',', $ids).')
                    ');

                    $processed += count($ids);
                } catch (\Exception $e) {
                    $errors += count($ids);
                    \Illuminate\Support\Facades\Log::error('ReindexLeads: Batch error', [
                        'ids' => $ids,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance(count($ids));
            });

        $bar->finish();
        $this->info('');
        $this->info('');

        // Результат
        if ($errors > 0) {
            $this->warn("⚠️  Завершено с ошибками: {$processed} успешно, {$errors} ошибок");

            return self::FAILURE;
        }

        $this->info("✅ Переиндексировано {$processed} deals");

        // Статистика индекса
        $this->info('');
        $this->info('📊 Статистика индекса:');

        try {
            $indexSize = DB::selectOne("
                SELECT pg_size_pretty(pg_relation_size('deals_search_vector_gin_idx')) as size
            ");
            $this->info("   GIN индекс: {$indexSize->size}");
        } catch (\Exception $e) {
            // Индекс может не существовать
        }

        $this->info('');

        return self::SUCCESS;
    }
}
