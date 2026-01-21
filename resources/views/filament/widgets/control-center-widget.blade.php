<x-filament-widgets::widget>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <x-heroicon-o-command-line class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white">Центр управления полётами</h2>
                    <p class="text-xs text-zinc-500">Мониторинг и контроль всех систем</p>
                </div>
            </div>
            <x-filament::button wire:click="refreshAll" icon="heroicon-o-arrow-path" size="sm" color="gray">
                Обновить
            </x-filament::button>
        </div>

        {{-- Services Status --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($this->services as $name => $service)
                <div class="relative overflow-hidden rounded-xl p-4 
                    @if($service['status'] === 'online') bg-emerald-500/10 border border-emerald-500/30
                    @elseif($service['status'] === 'warning') bg-amber-500/10 border border-amber-500/30
                    @else bg-rose-500/10 border border-rose-500/30
                    @endif">
                    {{-- Status Indicator --}}
                    <div class="absolute top-3 right-3">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75
                                @if($service['status'] === 'online') bg-emerald-400
                                @elseif($service['status'] === 'warning') bg-amber-400
                                @else bg-rose-400
                                @endif"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5
                                @if($service['status'] === 'online') bg-emerald-500
                                @elseif($service['status'] === 'warning') bg-amber-500
                                @else bg-rose-500
                                @endif"></span>
                        </span>
                    </div>
                    
                    {{-- Service Info --}}
                    <p class="text-[10px] uppercase tracking-wider font-medium mb-1
                        @if($service['status'] === 'online') text-emerald-400
                        @elseif($service['status'] === 'warning') text-amber-400
                        @else text-rose-400
                        @endif">
                        {{ str_replace('_', ' ', $name) }}
                    </p>
                    <p class="text-xs text-zinc-300 truncate">{{ $service['message'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Stats & Quick Actions --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Stats --}}
            <div class="rounded-2xl bg-[rgb(var(--surface))] border border-white/10 p-5">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5 text-indigo-400" />
                    Статистика
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-2xl font-bold text-white">{{ $this->stats['total_deals'] ?? 0 }}</p>
                        <p class="text-xs text-zinc-500">Всего сделок</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-amber-400">{{ $this->stats['active_deals'] ?? 0 }}</p>
                        <p class="text-xs text-zinc-500">Активных</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-emerald-400">{{ $this->stats['today_deals'] ?? 0 }}</p>
                        <p class="text-xs text-zinc-500">Сегодня</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-indigo-400">
                            {{ $this->stats['online_managers'] ?? 0 }}/{{ $this->stats['total_managers'] ?? 0 }}
                        </p>
                        <p class="text-xs text-zinc-500">Менеджеры онлайн</p>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="rounded-2xl bg-[rgb(var(--surface))] border border-white/10 p-5">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-bolt class="w-5 h-5 text-amber-400" />
                    Быстрые действия
                </h3>
                <div class="grid grid-cols-2 gap-3">
                    <x-filament::button wire:click="clearCache" icon="heroicon-o-trash" size="sm" color="gray" class="w-full justify-start">
                        Очистить кэш
                    </x-filament::button>
                    <x-filament::button wire:click="restartQueue" icon="heroicon-o-arrow-path" size="sm" color="gray" class="w-full justify-start">
                        Рестарт очереди
                    </x-filament::button>
                    <x-filament::button wire:click="syncMeta" icon="heroicon-o-cloud-arrow-down" size="sm" color="gray" class="w-full justify-start">
                        Синхр. Meta
                    </x-filament::button>
                    <x-filament::button wire:click="linkBot" icon="heroicon-o-paper-airplane" size="sm" color="gray" class="w-full justify-start">
                        Настр. бота
                    </x-filament::button>
                    <x-filament::button wire:click="runHealthCheck" icon="heroicon-o-heart" size="sm" color="primary" class="w-full justify-start col-span-2">
                        Полная диагностика
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Recent Logs --}}
        @if(count($this->recentLogs) > 0)
        <div class="rounded-2xl bg-[rgb(var(--surface))] border border-white/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5 text-zinc-400" />
                    Последние события системы
                </h3>
            </div>
            <div class="divide-y divide-white/5 max-h-64 overflow-y-auto">
                @foreach($this->recentLogs as $log)
                    <div class="px-5 py-3 flex items-center gap-3 hover:bg-white/10 transition-colors">
                        <span class="text-lg flex-shrink-0">{{ $log['icon'] }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-zinc-300 truncate">{{ $log['message'] }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    @if($log['level'] === 'error' || $log['level'] === 'critical') bg-rose-500/20 text-rose-400
                                    @elseif($log['level'] === 'warning') bg-amber-500/20 text-amber-400
                                    @else bg-zinc-500/20 text-zinc-400
                                    @endif">
                                    {{ $log['level'] }}
                                </span>
                                <span class="text-xs text-zinc-500">{{ $log['service'] }}</span>
                            </div>
                        </div>
                        <span class="text-xs text-zinc-600 flex-shrink-0">{{ $log['time'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</x-filament-widgets::widget>
