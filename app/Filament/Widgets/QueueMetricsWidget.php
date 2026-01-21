<?php

namespace App\Filament\Widgets;

use App\Models\SystemLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMetricsWidget extends BaseWidget
{
    protected ?string $heading = '📊 Метрики очередей';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $metrics = $this->getQueueMetrics();
        $recentErrors = SystemLog::errors()->recent(60)->count();

        $stats = [];

        // Failed jobs
        $failedStatus = $metrics['failed'] === 0 ? 'success' : ($metrics['failed'] > 5 ? 'danger' : 'warning');
        $stats[] = Stat::make('Failed Jobs', $metrics['failed'])
            ->description('Упавшие задачи')
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color($failedStatus)
            ->chart($this->getFailedJobsChart());

        // Queue lengths
        $totalPending = array_sum($metrics['queues']);
        $pendingStatus = $totalPending < 10 ? 'success' : ($totalPending > 50 ? 'warning' : 'primary');
        $stats[] = Stat::make('В очереди', $totalPending)
            ->description($this->formatQueueDetails($metrics['queues']))
            ->descriptionIcon('heroicon-m-queue-list')
            ->color($pendingStatus);

        // Meta queue
        $stats[] = Stat::make('Meta очередь', $metrics['queues']['meta'] ?? 0)
            ->description('Webhook события')
            ->descriptionIcon('heroicon-m-chat-bubble-left-right')
            ->color($metrics['queues']['meta'] > 0 ? 'info' : 'gray');

        // AI queue
        $stats[] = Stat::make('AI очередь', $metrics['queues']['ai'] ?? 0)
            ->description('Анализ сделок')
            ->descriptionIcon('heroicon-m-sparkles')
            ->color($metrics['queues']['ai'] > 0 ? 'warning' : 'gray');

        // Recent errors
        $errorStatus = $recentErrors === 0 ? 'success' : ($recentErrors > 10 ? 'danger' : 'warning');
        $stats[] = Stat::make('Ошибки (час)', $recentErrors)
            ->description('SystemLog errors')
            ->descriptionIcon('heroicon-m-bug-ant')
            ->color($errorStatus);

        return $stats;
    }

    protected function getQueueMetrics(): array
    {
        $metrics = [
            'driver' => config('queue.default'),
            'queues' => [
                'default' => 0,
                'meta' => 0,
                'ai' => 0,
            ],
            'failed' => 0,
        ];

        try {
            if (config('queue.default') === 'redis') {
                $prefix = config('database.redis.options.prefix', '');

                foreach (array_keys($metrics['queues']) as $queue) {
                    try {
                        $key = $prefix."queues:{$queue}";
                        $metrics['queues'][$queue] = (int) Redis::llen($key);
                    } catch (\Exception $e) {
                        // Игнорируем
                    }
                }
            }

            $metrics['failed'] = DB::table('failed_jobs')->count();

        } catch (\Exception $e) {
            // Игнорируем
        }

        return $metrics;
    }

    protected function formatQueueDetails(array $queues): string
    {
        $parts = [];
        foreach ($queues as $name => $count) {
            if ($count > 0) {
                $parts[] = "{$name}: {$count}";
            }
        }

        return $parts ? implode(' | ', $parts) : 'Пусто';
    }

    protected function getFailedJobsChart(): array
    {
        // Простая история failed jobs за последние 7 дней
        try {
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $count = DB::table('failed_jobs')
                    ->whereDate('failed_at', $date)
                    ->count();
                $data[] = $count;
            }

            return $data;
        } catch (\Exception $e) {
            return [0, 0, 0, 0, 0, 0, 0];
        }
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
