<?php

namespace App\Console;

use App\Jobs\SendSlaPings;
use App\Jobs\SyncMetaConversations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Определить расписание команд приложения.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Синхронизация с Meta API каждые 5 минут
        $schedule->job(new SyncMetaConversations)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/meta-sync.log'));

        // SLA пинги каждые 5 минут
        $schedule->job(new SendSlaPings)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
    }

    /**
     * Зарегистрировать команды для приложения.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
