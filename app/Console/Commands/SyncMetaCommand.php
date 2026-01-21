<?php

namespace App\Console\Commands;

use App\Jobs\SyncMetaConversations;
use Illuminate\Console\Command;

class SyncMetaCommand extends Command
{
    /**
     * Название и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'meta:sync 
                            {--platform= : Платформа для синхронизации (messenger/instagram)}
                            {--sync : Выполнить синхронно, без очереди}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Синхронизация бесед с Meta Business Suite';

    /**
     * Выполнить консольную команду.
     */
    public function handle(): int
    {
        $platform = $this->option('platform');
        $sync = $this->option('sync');

        $this->info('Запуск синхронизации с Meta API...');

        if ($platform && !in_array($platform, ['messenger', 'instagram'])) {
            $this->error('Неверная платформа. Используйте: messenger или instagram');

            return self::FAILURE;
        }

        if ($platform) {
            $this->info("Платформа: {$platform}");
        } else {
            $this->info('Платформа: все');
        }

        $job = new SyncMetaConversations($platform);

        if ($sync) {
            $this->info('Выполнение синхронно...');
            $job->handle(app(\App\Services\MetaApiService::class));
            $this->info('Синхронизация завершена!');
        } else {
            dispatch($job);
            $this->info('Задача добавлена в очередь.');
        }

        return self::SUCCESS;
    }
}
