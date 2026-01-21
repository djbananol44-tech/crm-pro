<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\User;
use Carbon\Carbon;

class ReportService
{
    /**
     * Получить полный отчёт за период.
     */
    public function getReport(
        Carbon $startDate,
        Carbon $endDate,
        ?int $managerId = null
    ): array {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        return [
            'period' => [
                'start' => $start->format('d.m.Y'),
                'end' => $end->format('d.m.Y'),
                'days' => $start->diffInDays($end) + 1,
            ],
            'leads' => $this->getLeadsMetrics($start, $end, $managerId),
            'response_time' => $this->getResponseTimeMetrics($start, $end, $managerId),
            'status_distribution' => $this->getStatusDistribution($start, $end, $managerId),
            'sla' => $this->getSlaMetrics($start, $end, $managerId),
            'managers' => $managerId ? null : $this->getManagersBreakdown($start, $end),
        ];
    }

    /**
     * A.1) Количество обращений (новых лидов) за период.
     */
    public function getLeadsMetrics(Carbon $start, Carbon $end, ?int $managerId = null): array
    {
        $query = Deal::whereBetween('created_at', [$start, $end]);

        if ($managerId) {
            $query->where('manager_id', $managerId);
        }

        $total = $query->count();

        // Группировка по дням
        $byDay = Deal::whereBetween('created_at', [$start, $end])
            ->when($managerId, fn ($q) => $q->where('manager_id', $managerId))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Среднее в день
        $days = max(1, $start->diffInDays($end) + 1);
        $avgPerDay = round($total / $days, 1);

        return [
            'total' => $total,
            'avg_per_day' => $avgPerDay,
            'by_day' => $byDay,
        ];
    }

    /**
     * A.2) Среднее время до первого ответа менеджера.
     */
    public function getResponseTimeMetrics(Carbon $start, Carbon $end, ?int $managerId = null): array
    {
        $query = Deal::whereBetween('created_at', [$start, $end])
            ->whereNotNull('last_manager_response_at')
            ->whereNotNull('last_client_message_at')
            ->whereRaw('last_manager_response_at > last_client_message_at');

        if ($managerId) {
            $query->where('manager_id', $managerId);
        }

        // Среднее время ответа в минутах
        $avgMinutes = (clone $query)
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60) as avg_minutes')
            ->value('avg_minutes');

        // Медианное время ответа
        $medianMinutes = $this->calculateMedianResponseTime($start, $end, $managerId);

        // Минимальное и максимальное
        $stats = (clone $query)
            ->selectRaw('
                MIN(EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60) as min_minutes,
                MAX(EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60) as max_minutes
            ')
            ->first();

        // Распределение по диапазонам (< 5 мин, 5-15, 15-30, 30-60, > 60)
        $distribution = $this->getResponseTimeDistribution($start, $end, $managerId);

        return [
            'avg_minutes' => $avgMinutes ? round($avgMinutes, 1) : null,
            'median_minutes' => $medianMinutes,
            'min_minutes' => $stats?->min_minutes ? round($stats->min_minutes, 1) : null,
            'max_minutes' => $stats?->max_minutes ? round($stats->max_minutes, 1) : null,
            'distribution' => $distribution,
            'formatted' => $avgMinutes ? $this->formatMinutes($avgMinutes) : 'Н/Д',
        ];
    }

    /**
     * A.3) Распределение сделок по статусам.
     */
    public function getStatusDistribution(Carbon $start, Carbon $end, ?int $managerId = null): array
    {
        $query = Deal::whereBetween('created_at', [$start, $end]);

        if ($managerId) {
            $query->where('manager_id', $managerId);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($byStatus);

        return [
            'new' => $byStatus['New'] ?? 0,
            'in_progress' => $byStatus['In Progress'] ?? 0,
            'closed' => $byStatus['Closed'] ?? 0,
            'total' => $total,
            'percentages' => [
                'new' => $total > 0 ? round((($byStatus['New'] ?? 0) / $total) * 100, 1) : 0,
                'in_progress' => $total > 0 ? round((($byStatus['In Progress'] ?? 0) / $total) * 100, 1) : 0,
                'closed' => $total > 0 ? round((($byStatus['Closed'] ?? 0) / $total) * 100, 1) : 0,
            ],
            'conversion_rate' => $total > 0 ? round((($byStatus['Closed'] ?? 0) / $total) * 100, 1) : 0,
        ];
    }

    /**
     * A.4) SLA метрики.
     */
    public function getSlaMetrics(Carbon $start, Carbon $end, ?int $managerId = null): array
    {
        // SLA = 30 минут на ответ (настраивается)
        $slaMinutes = 30;

        $query = Deal::whereBetween('created_at', [$start, $end])
            ->whereNotNull('last_client_message_at');

        if ($managerId) {
            $query->where('manager_id', $managerId);
        }

        $total = (clone $query)->count();

        // Просроченные (ответ > SLA или нет ответа)
        $overdueQuery = (clone $query)
            ->where(function ($q) use ($slaMinutes) {
                $q->whereNull('last_manager_response_at')
                    ->orWhereRaw(
                        'EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 > ?',
                        [$slaMinutes]
                    );
            });

        $overdueCount = $overdueQuery->count();

        // Средняя просрочка (только для просроченных)
        $avgOverdueMinutes = Deal::whereBetween('created_at', [$start, $end])
            ->when($managerId, fn ($q) => $q->where('manager_id', $managerId))
            ->whereNotNull('last_client_message_at')
            ->whereNotNull('last_manager_response_at')
            ->whereRaw(
                'EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 > ?',
                [$slaMinutes]
            )
            ->selectRaw(
                'AVG(EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 - ?) as avg_overdue',
                [$slaMinutes]
            )
            ->value('avg_overdue');

        return [
            'sla_minutes' => $slaMinutes,
            'total_with_messages' => $total,
            'overdue_count' => $overdueCount,
            'overdue_percentage' => $total > 0 ? round(($overdueCount / $total) * 100, 1) : 0,
            'avg_overdue_minutes' => $avgOverdueMinutes ? round($avgOverdueMinutes, 1) : null,
            'avg_overdue_formatted' => $avgOverdueMinutes ? $this->formatMinutes($avgOverdueMinutes) : 'Н/Д',
            'within_sla_percentage' => $total > 0 ? round((($total - $overdueCount) / $total) * 100, 1) : 100,
        ];
    }

    /**
     * Разбивка по менеджерам.
     */
    public function getManagersBreakdown(Carbon $start, Carbon $end): array
    {
        $managers = User::where('role', 'manager')->get();
        $breakdown = [];

        foreach ($managers as $manager) {
            $deals = Deal::where('manager_id', $manager->id)
                ->whereBetween('created_at', [$start, $end]);

            $total = (clone $deals)->count();
            $closed = (clone $deals)->where('status', 'Closed')->count();

            // Среднее время ответа
            $avgResponse = (clone $deals)
                ->whereNotNull('last_manager_response_at')
                ->whereNotNull('last_client_message_at')
                ->whereRaw('last_manager_response_at > last_client_message_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60) as avg')
                ->value('avg');

            $breakdown[] = [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'total_deals' => $total,
                'new_deals' => (clone $deals)->where('status', 'New')->count(),
                'in_progress' => (clone $deals)->where('status', 'In Progress')->count(),
                'closed_deals' => $closed,
                'conversion_rate' => $total > 0 ? round(($closed / $total) * 100, 1) : 0,
                'avg_response_minutes' => $avgResponse ? round($avgResponse, 1) : null,
                'avg_response_formatted' => $avgResponse ? $this->formatMinutes($avgResponse) : 'Н/Д',
            ];
        }

        // Сортируем по количеству сделок
        usort($breakdown, fn ($a, $b) => $b['total_deals'] <=> $a['total_deals']);

        return $breakdown;
    }

    /**
     * Вычислить медианное время ответа.
     */
    protected function calculateMedianResponseTime(Carbon $start, Carbon $end, ?int $managerId): ?float
    {
        $query = Deal::whereBetween('created_at', [$start, $end])
            ->whereNotNull('last_manager_response_at')
            ->whereNotNull('last_client_message_at')
            ->whereRaw('last_manager_response_at > last_client_message_at');

        if ($managerId) {
            $query->where('manager_id', $managerId);
        }

        $times = $query
            ->selectRaw('EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 as minutes')
            ->orderBy('minutes')
            ->pluck('minutes')
            ->toArray();

        if (empty($times)) {
            return null;
        }

        $count = count($times);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($times[$middle - 1] + $times[$middle]) / 2, 1);
        }

        return round($times[$middle], 1);
    }

    /**
     * Распределение времени ответа по диапазонам.
     */
    protected function getResponseTimeDistribution(Carbon $start, Carbon $end, ?int $managerId): array
    {
        $query = Deal::whereBetween('created_at', [$start, $end])
            ->whereNotNull('last_manager_response_at')
            ->whereNotNull('last_client_message_at')
            ->whereRaw('last_manager_response_at > last_client_message_at');

        if ($managerId) {
            $query->where('manager_id', $managerId);
        }

        $result = $query->selectRaw('
            SUM(CASE WHEN EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 < 5 THEN 1 ELSE 0 END) as under_5,
            SUM(CASE WHEN EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 BETWEEN 5 AND 15 THEN 1 ELSE 0 END) as from_5_to_15,
            SUM(CASE WHEN EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 BETWEEN 15 AND 30 THEN 1 ELSE 0 END) as from_15_to_30,
            SUM(CASE WHEN EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 BETWEEN 30 AND 60 THEN 1 ELSE 0 END) as from_30_to_60,
            SUM(CASE WHEN EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60 > 60 THEN 1 ELSE 0 END) as over_60
        ')->first();

        return [
            '< 5 мин' => (int) ($result->under_5 ?? 0),
            '5-15 мин' => (int) ($result->from_5_to_15 ?? 0),
            '15-30 мин' => (int) ($result->from_15_to_30 ?? 0),
            '30-60 мин' => (int) ($result->from_30_to_60 ?? 0),
            '> 60 мин' => (int) ($result->over_60 ?? 0),
        ];
    }

    /**
     * Форматировать минуты в читаемый формат.
     */
    public function formatMinutes(float $minutes): string
    {
        if ($minutes < 1) {
            return '< 1 мин';
        }

        if ($minutes < 60) {
            return round($minutes).' мин';
        }

        $hours = floor($minutes / 60);
        $mins = round($minutes % 60);

        if ($mins === 0.0) {
            return $hours.' ч';
        }

        return $hours.' ч '.$mins.' мин';
    }

    /**
     * Получить периоды для UI.
     */
    public static function getPeriodPresets(): array
    {
        return [
            'today' => [
                'label' => 'Сегодня',
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'yesterday' => [
                'label' => 'Вчера',
                'start' => now()->subDay()->startOfDay(),
                'end' => now()->subDay()->endOfDay(),
            ],
            'week' => [
                'label' => 'Эта неделя',
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'last_week' => [
                'label' => 'Прошлая неделя',
                'start' => now()->subWeek()->startOfWeek(),
                'end' => now()->subWeek()->endOfWeek(),
            ],
            'month' => [
                'label' => 'Этот месяц',
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            'last_month' => [
                'label' => 'Прошлый месяц',
                'start' => now()->subMonth()->startOfMonth(),
                'end' => now()->subMonth()->endOfMonth(),
            ],
            'quarter' => [
                'label' => 'Этот квартал',
                'start' => now()->startOfQuarter(),
                'end' => now()->endOfQuarter(),
            ],
        ];
    }
}
