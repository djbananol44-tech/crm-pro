<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DealsStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $total = Deal::count();
        $new = Deal::where('status', 'New')->count();
        $inProgress = Deal::where('status', 'In Progress')->count();
        $closed = Deal::where('status', 'Closed')->count();
        $overdue = Deal::where('reminder_at', '<', now())
            ->whereIn('status', ['New', 'In Progress'])
            ->count();

        // Изменения за последние 7 дней
        $lastWeekTotal = Deal::where('created_at', '>=', now()->subDays(7))->count();
        $lastWeekNew = Deal::where('status', 'New')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $lastWeekClosed = Deal::where('status', 'Closed')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Всего сделок', number_format($total, 0, ',', ' '))
                ->description($lastWeekTotal > 0 ? "+{$lastWeekTotal} за неделю" : 'Нет новых за неделю')
                ->descriptionIcon($lastWeekTotal > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($lastWeekTotal > 0 ? 'primary' : 'gray')
                ->chart($this->getWeeklyDealsChart())
                ->extraAttributes([
                    'class' => 'ring-1 ring-indigo-500/20',
                ]),

            Stat::make('Новые заявки', number_format($new, 0, ',', ' '))
                ->description($lastWeekNew > 0 ? "+{$lastWeekNew} за неделю" : 'Нет новых')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info')
                ->chart(array_fill(0, 7, max(1, $new)))
                ->extraAttributes([
                    'class' => 'ring-1 ring-sky-500/20',
                ]),

            Stat::make('В работе', number_format($inProgress, 0, ',', ' '))
                ->description($this->getInProgressDescription($inProgress, $total))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'ring-1 ring-amber-500/20',
                ]),

            Stat::make('Завершённые', number_format($closed, 0, ',', ' '))
                ->description($this->getClosedPercentage($closed, $total))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($this->getWeeklyClosedChart())
                ->extraAttributes([
                    'class' => 'ring-1 ring-emerald-500/20',
                ]),

            Stat::make('Просрочено', number_format($overdue, 0, ',', ' '))
                ->description($overdue > 0 ? 'Требуют внимания!' : 'Всё под контролем')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check')
                ->color($overdue > 0 ? 'danger' : 'gray')
                ->extraAttributes([
                    'class' => $overdue > 0 ? 'ring-1 ring-rose-500/30 animate-pulse' : 'ring-1 ring-slate-200',
                ]),
        ];
    }

    /**
     * Получить данные для графика сделок за неделю.
     */
    protected function getWeeklyDealsChart(): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $data[] = Deal::whereDate('created_at', $date)->count();
        }

        return $data;
    }

    /**
     * Получить данные для графика завершённых за неделю.
     */
    protected function getWeeklyClosedChart(): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $data[] = Deal::where('status', 'Closed')
                ->whereDate('updated_at', $date)
                ->count();
        }

        return $data;
    }

    /**
     * Описание для сделок в работе.
     */
    protected function getInProgressDescription(int $inProgress, int $total): string
    {
        if ($total === 0) {
            return 'Нет сделок';
        }
        
        $percentage = round(($inProgress / $total) * 100);
        return "{$percentage}% от общего числа";
    }

    /**
     * Процент завершённых сделок.
     */
    protected function getClosedPercentage(int $closed, int $total): string
    {
        if ($total === 0) {
            return 'Нет сделок';
        }
        
        $percentage = round(($closed / $total) * 100);
        return "Конверсия: {$percentage}%";
    }
}
