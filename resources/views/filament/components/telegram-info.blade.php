@php
    $status = \App\Services\TelegramService::getStatus();
    $statusColor = match ($status['status']) {
        'ok' => 'green',
        'error' => 'red',
        default => 'gray',
    };
    $statusIcon = match ($status['status']) {
        'ok' => '🟢',
        'error' => '🔴',
        default => '⚪',
    };
@endphp

<div class="rounded-lg border border-white/10 p-4 bg-[rgb(16,21,28)]">
    <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Статус интеграции
        </span>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
            {{ $status['status'] === 'ok' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
            {{ $status['status'] === 'error' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
            {{ $status['status'] === 'disabled' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400' : '' }}
        ">
            {{ $statusIcon }} {{ ucfirst($status['status']) }}
        </span>
    </div>

    <dl class="grid grid-cols-2 gap-2 text-sm">
        @if($status['bot_username'])
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Бот:</dt>
            <dd class="text-gray-900 dark:text-gray-100 font-mono">@{{ $status['bot_username'] }}</dd>
        </div>
        @endif

        <div>
            <dt class="text-gray-500 dark:text-gray-400">Режим:</dt>
            <dd class="text-gray-900 dark:text-gray-100">
                @if($status['mode'] === 'webhook')
                    🔗 Webhook
                @else
                    🔄 Polling
                @endif
            </dd>
        </div>

        @if($status['webhook_url'])
        <div class="col-span-2">
            <dt class="text-gray-500 dark:text-gray-400">Webhook URL:</dt>
            <dd class="text-gray-900 dark:text-gray-100 font-mono text-xs break-all">
                {{ $status['webhook_url'] }}
            </dd>
        </div>
        @endif

        @if($status['last_error'])
        <div class="col-span-2">
            <dt class="text-red-500 dark:text-red-400">Последняя ошибка:</dt>
            <dd class="text-red-700 dark:text-red-300 text-xs">{{ $status['last_error'] }}</dd>
        </div>
        @endif

        @if($status['last_check_at'])
        <div class="col-span-2 text-xs text-gray-400">
            Последняя проверка: {{ \Carbon\Carbon::parse($status['last_check_at'])->diffForHumans() }}
        </div>
        @endif
    </dl>

    <div class="mt-3 pt-3 border-t border-white/10">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            💡 Сохраните токен — бот активируется автоматически.
            @if($status['mode'] === 'webhook')
            <br>Webhook защищён secret_token (проверяется X-Telegram-Bot-Api-Secret-Token).
            @endif
        </p>
    </div>
</div>
