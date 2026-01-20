<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSlaPings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(TelegramService $telegram): void
    {
        if (!$telegram->isAvailable()) {
            Log::info('SendSlaPings: Telegram не настроен, пропуск');
            return;
        }

        $sent = $telegram->sendSlaPings();

        Log::info('SendSlaPings: Отправлено пингов', ['count' => $sent]);
    }
}
