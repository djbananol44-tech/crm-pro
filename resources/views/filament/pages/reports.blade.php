<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Фильтр периода --}}
        <x-filament::section>
            <x-slot name="heading">Период отчёта</x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="date"
                            wire:model.live="startDate"
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 mt-1">Начало периода</p>
                </div>
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="date"
                            wire:model.live="endDate"
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 mt-1">Конец периода</p>
                </div>
            </div>
        </x-filament::section>

        @php $report = $this->getReportData(); @endphp

        {{-- Общая статистика --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold text-primary-600">{{ $report['totals']['total'] }}</p>
                    <p class="text-sm text-gray-500">Всего сделок</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold text-success-600">{{ $report['totals']['closed'] }}</p>
                    <p class="text-sm text-gray-500">Закрыто успешно</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold text-warning-600">{{ $report['totals']['hot_leads'] }}</p>
                    <p class="text-sm text-gray-500">Горячих лидов</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Таблица по менеджерам --}}
        <x-filament::section>
            <x-slot name="heading">Статистика по менеджерам</x-slot>
            <x-slot name="description">Период: {{ $report['period']['start'] }} — {{ $report['period']['end'] }}</x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Менеджер</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Всего</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Новые</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">В работе</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Закрыто</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Конверсия</th>
                            <th class="text-center py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Ср. время ответа</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['managers'] as $manager)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $manager['manager'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $manager['email'] }}</p>
                                </div>
                            </td>
                            <td class="text-center py-3 px-4 font-semibold">{{ $manager['total_deals'] }}</td>
                            <td class="text-center py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $manager['new_deals'] }}
                                </span>
                            </td>
                            <td class="text-center py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                    {{ $manager['in_progress'] }}
                                </span>
                            </td>
                            <td class="text-center py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                    {{ $manager['closed_deals'] }}
                                </span>
                            </td>
                            <td class="text-center py-3 px-4">
                                <span class="font-semibold {{ $manager['conversion'] >= 50 ? 'text-green-600' : ($manager['conversion'] >= 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $manager['conversion'] }}%
                                </span>
                            </td>
                            <td class="text-center py-3 px-4 text-gray-600 dark:text-gray-400">
                                @if($manager['avg_response_time'])
                                    {{ $manager['avg_response_time'] }} мин
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                Нет данных за выбранный период
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
