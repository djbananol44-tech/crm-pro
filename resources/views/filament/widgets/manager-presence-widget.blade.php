<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
                <span>Статус менеджеров</span>
                <span class="text-xs text-gray-500 font-normal ml-auto">
                    {{ $this->getOnlineCount() }}/{{ $this->getTotalManagers() }} в сети
                </span>
            </div>
        </x-slot>

        <div class="space-y-3">
            @forelse($this->getManagers() as $manager)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    {{-- Avatar with status --}}
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold">
                            {{ substr($manager->name, 0, 1) }}
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 block h-3.5 w-3.5 rounded-full ring-2 ring-[rgb(11,15,20)] {{ $manager->isOnline() ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $manager->name }}
                            </p>
                            @if($manager->isOnline())
                                <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/50 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                                    В сети
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $manager->getPresenceStatus() }}
                        </p>
                    </div>

                    {{-- Stats --}}
                    <div class="flex items-center gap-4 text-xs">
                        <div class="text-center">
                            <div class="font-bold text-gray-900 dark:text-white">
                                {{ $manager->deals_count }}
                            </div>
                            <div class="text-gray-500">сделок</div>
                        </div>
                        @if($manager->getAverageRating())
                            <div class="text-center">
                                <div class="font-bold text-amber-500">
                                    ⭐ {{ $manager->getAverageRating() }}
                                </div>
                                <div class="text-gray-500">рейтинг</div>
                            </div>
                        @endif
                    </div>

                    {{-- Telegram status --}}
                    <div class="flex-shrink-0">
                        @if($manager->hasTelegram())
                            <span class="text-blue-500" title="Telegram подключен">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.015-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.44-.751-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.015 3.333-1.386 4.025-1.627 4.477-1.635.099-.002.321.023.465.144.121.101.154.237.169.332.015.095.033.311.019.48z"/>
                                </svg>
                            </span>
                        @else
                            <span class="text-gray-300 dark:text-gray-600" title="Telegram не подключен">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.015-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.44-.751-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.015 3.333-1.386 4.025-1.627 4.477-1.635.099-.002.321.023.465.144.121.101.154.237.169.332.015.095.033.311.019.48z"/>
                                </svg>
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-6 text-gray-500">
                    <x-heroicon-o-users class="w-8 h-8 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">Нет менеджеров</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
