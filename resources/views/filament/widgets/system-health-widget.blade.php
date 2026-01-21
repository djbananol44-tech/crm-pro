<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🏥</span>
                    <span>Системный отчёт</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        wire:click="refreshHealth"
                        wire:loading.attr="disabled"
                        size="sm"
                        color="gray"
                    >
                        <x-slot name="icon">
                            <svg wire:loading.class="animate-spin" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </x-slot>
                        Обновить
                    </x-filament::button>
                    
                    <x-filament::button
                        wire:click="restartWorkers"
                        wire:loading.attr="disabled"
                        size="sm"
                        color="warning"
                    >
                        <x-slot name="icon">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </x-slot>
                        Перезапустить воркеры
                    </x-filament::button>
                </div>
            </div>
        </x-slot>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($this->getHealthData() as $key => $check)
                <div class="rounded-xl p-4 border transition-all hover:scale-105
                    @if($check['status'] === 'ok') 
                        bg-emerald-500/10 border-emerald-500/30
                    @elseif($check['status'] === 'warning')
                        bg-amber-500/10 border-amber-500/30
                    @else
                        bg-red-500/10 border-red-500/30
                    @endif
                ">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">{{ $check['icon'] }}</span>
                        <span class="relative flex h-2.5 w-2.5">
                            @if($check['status'] === 'ok')
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            @elseif($check['status'] === 'warning')
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                            @else
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            @endif
                        </span>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-200">{{ $check['label'] }}</h4>
                    <p class="text-xs text-slate-400 mt-1 truncate" title="{{ $check['details'] }}">
                        {{ $check['details'] }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Кнопка полной диагностики --}}
        <div class="mt-4 pt-4 border-t border-slate-700/50">
            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-500">
                    Для полной диагностики выполните: <code class="px-1.5 py-0.5 rounded bg-slate-800 text-indigo-400">php artisan crm:check</code>
                </p>
                <x-filament::button
                    wire:click="runDiagnostics"
                    wire:loading.attr="disabled"
                    size="xs"
                    color="gray"
                >
                    Запустить диагностику
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
