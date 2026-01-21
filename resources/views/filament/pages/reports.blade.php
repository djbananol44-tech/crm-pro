<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Фильтры --}}
        <x-filament::section>
            <x-slot name="heading">📊 Фильтры отчёта</x-slot>
            
            {{ $this->form }}
        </x-filament::section>

        @php $report = $this->getReportData(); @endphp

        {{-- Основные метрики --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- A.1: Количество обращений --}}
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold text-primary-600">{{ $report['leads']['total'] }}</p>
                    <p class="text-sm text-gray-500">Новых обращений</p>
                    <p class="text-xs text-gray-400 mt-1">~{{ $report['leads']['avg_per_day'] }} в день</p>
                </div>
            </x-filament::section>

            {{-- A.2: Среднее время ответа --}}
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold {{ ($report['response_time']['avg_minutes'] ?? 999) <= 15 ? 'text-success-600' : (($report['response_time']['avg_minutes'] ?? 999) <= 30 ? 'text-warning-600' : 'text-danger-600') }}">
                        {{ $report['response_time']['formatted'] }}
                    </p>
                    <p class="text-sm text-gray-500">Среднее время ответа</p>
                    @if($report['response_time']['median_minutes'])
                        <p class="text-xs text-gray-400 mt-1">Медиана: {{ round($report['response_time']['median_minutes']) }} мин</p>
                    @endif
                </div>
            </x-filament::section>

            {{-- A.3: Конверсия --}}
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold text-success-600">{{ $report['status_distribution']['conversion_rate'] }}%</p>
                    <p class="text-sm text-gray-500">Конверсия (закрыто)</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $report['status_distribution']['closed'] }} из {{ $report['status_distribution']['total'] }}</p>
                </div>
            </x-filament::section>

            {{-- A.4: SLA --}}
            <x-filament::section>
                <div class="text-center">
                    <p class="text-3xl font-bold {{ $report['sla']['within_sla_percentage'] >= 80 ? 'text-success-600' : ($report['sla']['within_sla_percentage'] >= 60 ? 'text-warning-600' : 'text-danger-600') }}">
                        {{ $report['sla']['within_sla_percentage'] }}%
                    </p>
                    <p class="text-sm text-gray-500">В рамках SLA ({{ $report['sla']['sla_minutes'] }} мин)</p>
                    <p class="text-xs text-gray-400 mt-1">Просрочено: {{ $report['sla']['overdue_count'] }}</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Распределение по статусам --}}
        <x-filament::section>
            <x-slot name="heading">📈 Распределение по статусам</x-slot>
            <x-slot name="description">Период: {{ $report['period']['start'] }} — {{ $report['period']['end'] }}</x-slot>
            
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $report['status_distribution']['new'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Новые</p>
                    <p class="text-xs text-gray-500">{{ $report['status_distribution']['percentages']['new'] }}%</p>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $report['status_distribution']['in_progress'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">В работе</p>
                    <p class="text-xs text-gray-500">{{ $report['status_distribution']['percentages']['in_progress'] }}%</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $report['status_distribution']['closed'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Закрыто</p>
                    <p class="text-xs text-gray-500">{{ $report['status_distribution']['percentages']['closed'] }}%</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Распределение времени ответа --}}
        @if(!empty($report['response_time']['distribution']))
        <x-filament::section>
            <x-slot name="heading">⏱️ Распределение времени ответа</x-slot>
            
            <div class="grid grid-cols-5 gap-2">
                @foreach($report['response_time']['distribution'] as $range => $count)
                <div class="text-center p-3 rounded-lg {{ str_starts_with($range, '<') ? 'bg-green-50 dark:bg-green-900/20' : (str_starts_with($range, '>') ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-800') }}">
                    <p class="text-lg font-bold">{{ $count }}</p>
                    <p class="text-xs text-gray-500">{{ $range }}</p>
                </div>
                @endforeach
            </div>
        </x-filament::section>
        @endif

        {{-- Таблица по менеджерам --}}
        @if(!empty($report['managers']))
        <x-filament::section>
            <x-slot name="heading">👥 Статистика по менеджерам</x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10">
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
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $manager['name'] }}</p>
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
                                <span class="font-semibold {{ $manager['conversion_rate'] >= 50 ? 'text-green-600' : ($manager['conversion_rate'] >= 25 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $manager['conversion_rate'] }}%
                                </span>
                            </td>
                            <td class="text-center py-3 px-4 text-gray-600 dark:text-gray-400">
                                {{ $manager['avg_response_formatted'] }}
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
        @endif

        {{-- SLA детали --}}
        @if($report['sla']['overdue_count'] > 0)
        <x-filament::section>
            <x-slot name="heading">⚠️ SLA — детали просрочки</x-slot>
            
            <div class="grid grid-cols-3 gap-4">
                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                    <p class="text-2xl font-bold text-red-600">{{ $report['sla']['overdue_count'] }}</p>
                    <p class="text-sm text-gray-600">Просроченных сделок</p>
                </div>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                    <p class="text-2xl font-bold text-red-600">{{ $report['sla']['overdue_percentage'] }}%</p>
                    <p class="text-sm text-gray-600">Доля просрочки</p>
                </div>
                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                    <p class="text-2xl font-bold text-red-600">{{ $report['sla']['avg_overdue_formatted'] }}</p>
                    <p class="text-sm text-gray-600">Средняя просрочка</p>
                </div>
            </div>
        </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
