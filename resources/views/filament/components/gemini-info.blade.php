@php
    $status = \App\Services\AiAnalysisService::getStatus();
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
        <div>
            <dt class="text-gray-500 dark:text-gray-400">AI включен:</dt>
            <dd class="text-gray-900 dark:text-gray-100">
                @if($status['enabled'])
                    ✅ Да
                @else
                    ❌ Нет
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-gray-500 dark:text-gray-400">API ключ:</dt>
            <dd class="text-gray-900 dark:text-gray-100">
                @if($status['has_key'])
                    🔑 Установлен
                @else
                    ⚠️ Не установлен
                @endif
            </dd>
        </div>

        @if($status['last_latency_ms'])
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Latency:</dt>
            <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ $status['last_latency_ms'] }}ms</dd>
        </div>
        @endif

        @if($status['last_error'])
        <div class="col-span-2">
            <dt class="text-red-500 dark:text-red-400">Последняя ошибка:</dt>
            <dd class="text-red-700 dark:text-red-300 text-xs break-all">{{ $status['last_error'] }}</dd>
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
            💡 Сохраните API ключ — Gemini активируется автоматически.
            @if($status['status'] === 'error')
            <br><span class="text-yellow-600 dark:text-yellow-400">При ошибках API повторные запросы блокируются на 5 мин.</span>
            @endif
        </p>
    </div>
</div>
