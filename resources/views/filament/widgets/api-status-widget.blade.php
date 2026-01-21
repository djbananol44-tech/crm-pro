<x-filament-widgets::widget>
    <div class="flex items-center justify-between gap-4 p-4 rounded-2xl bg-slate-800/50 border border-slate-700/50">
        {{-- Статусы API --}}
        <div class="flex items-center gap-6">
            @foreach($this->getStatuses() as $key => $api)
                <div class="flex items-center gap-2.5">
                    {{-- Индикатор --}}
                    <span class="relative flex h-3 w-3">
                        @if($api['status'] === 'online')
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        @elseif($api['status'] === 'error')
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                        @elseif($api['status'] === 'disabled')
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                        @else
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-slate-500"></span>
                        @endif
                    </span>
                    
                    {{-- Иконка и название --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm">{{ $api['icon'] }}</span>
                        <div>
                            <span class="text-sm font-medium text-slate-200">{{ $api['label'] }}</span>
                            <span class="text-xs text-slate-500 ml-1">
                                @if($api['status'] === 'online')
                                    <span class="text-emerald-400">{{ $api['message'] }}</span>
                                @elseif($api['status'] === 'error')
                                    <span class="text-red-400">{{ $api['message'] }}</span>
                                @elseif($api['status'] === 'disabled')
                                    <span class="text-amber-400">{{ $api['message'] }}</span>
                                @else
                                    <span class="text-slate-400">{{ $api['message'] }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
                
                @if(!$loop->last)
                    <div class="w-px h-6 bg-slate-700"></div>
                @endif
            @endforeach
        </div>

        {{-- Кнопка обновления --}}
        <button 
            wire:click="refreshStatuses" 
            wire:loading.attr="disabled"
            class="p-2 rounded-xl hover:bg-slate-700/50 text-slate-400 hover:text-white transition-all disabled:opacity-50"
            title="Обновить статусы"
        >
            <svg wire:loading.class="animate-spin" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </button>
    </div>
</x-filament-widgets::widget>
