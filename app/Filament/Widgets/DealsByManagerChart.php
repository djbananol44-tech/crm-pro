<?php

namespace App\Filament\Widgets;

use App\Models\Deal;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DealsByManagerChart extends ChartWidget
{
    protected static ?string $heading = 'Распределение по менеджерам';

    protected static ?string $description = 'Активные сделки в работе у каждого менеджера';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = '60s';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = Deal::select('manager_id', DB::raw('count(*) as count'))
            ->whereIn('status', ['New', 'In Progress'])
            ->groupBy('manager_id')
            ->get();

        $labels = [];
        $counts = [];

        // Премиальная палитра цветов
        $colors = [
            'rgba(99, 102, 241, 0.85)',   // indigo
            'rgba(16, 185, 129, 0.85)',   // emerald
            'rgba(245, 158, 11, 0.85)',   // amber
            'rgba(244, 63, 94, 0.85)',    // rose
            'rgba(139, 92, 246, 0.85)',   // violet
            'rgba(14, 165, 233, 0.85)',   // sky
            'rgba(236, 72, 153, 0.85)',   // pink
            'rgba(20, 184, 166, 0.85)',   // teal
        ];

        foreach ($data as $index => $item) {
            if ($item->manager_id) {
                $manager = User::find($item->manager_id);
                $labels[] = $manager ? $manager->name : "ID: {$item->manager_id}";
            } else {
                $labels[] = 'Не назначен';
            }
            $counts[] = $item->count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Сделки',
                    'data' => $counts,
                    'backgroundColor' => array_slice($colors, 0, count($counts)),
                    'borderColor' => 'rgba(255, 255, 255, 1)',
                    'borderWidth' => 3,
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'rectRounded',
                        'padding' => 16,
                        'font' => [
                            'size' => 12,
                            'weight' => 500,
                        ],
                    ],
                ],
            ],
            'cutout' => '65%',
            'maintainAspectRatio' => false,
            'animation' => [
                'animateRotate' => true,
                'animateScale' => true,
            ],
        ];
    }
}
