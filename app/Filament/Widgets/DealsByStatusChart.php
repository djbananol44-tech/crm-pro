<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DealsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Воронка продаж';
    
    protected static ?string $description = 'Распределение сделок по этапам';
    
    protected static ?int $sort = 2;
    
    protected static ?string $pollingInterval = '60s';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = Deal::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [
            'New' => [
                'label' => 'Новые', 
                'color' => 'rgba(99, 102, 241, 0.85)',
                'border' => 'rgba(99, 102, 241, 1)',
            ],
            'In Progress' => [
                'label' => 'В работе', 
                'color' => 'rgba(245, 158, 11, 0.85)',
                'border' => 'rgba(245, 158, 11, 1)',
            ],
            'Closed' => [
                'label' => 'Завершённые', 
                'color' => 'rgba(16, 185, 129, 0.85)',
                'border' => 'rgba(16, 185, 129, 1)',
            ],
        ];

        $labels = [];
        $counts = [];
        $colors = [];
        $borders = [];

        foreach ($statuses as $key => $config) {
            $labels[] = $config['label'];
            $counts[] = $data->get($key)?->count ?? 0;
            $colors[] = $config['color'];
            $borders[] = $config['border'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Количество',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                    'borderColor' => $borders,
                    'borderWidth' => 2,
                    'borderRadius' => 12,
                    'borderSkipped' => false,
                    'barThickness' => 50,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                        'font' => [
                            'size' => 11,
                        ],
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'font' => [
                            'size' => 12,
                            'weight' => 500,
                        ],
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
