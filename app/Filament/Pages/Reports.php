<?php

namespace App\Filament\Pages;

use App\Models\Deal;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Отчёты';
    protected static ?string $title = 'Отчёты и аналитика';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?int $navigationSort = 90;
    protected static string $view = 'filament.pages.reports';

    public ?string $period = 'month';
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function getReportData(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $managers = User::where('role', 'manager')->get();
        $data = [];

        foreach ($managers as $manager) {
            $deals = Deal::where('manager_id', $manager->id)
                ->whereBetween('created_at', [$start, $end])
                ->get();

            $totalDeals = $deals->count();
            $closedDeals = $deals->where('status', 'Closed')->count();
            $conversion = $totalDeals > 0 ? round(($closedDeals / $totalDeals) * 100, 1) : 0;

            // Среднее время ответа (примерное)
            $avgResponseTime = Deal::where('manager_id', $manager->id)
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('last_manager_response_at')
                ->whereNotNull('last_client_message_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (last_manager_response_at - last_client_message_at)) / 60) as avg_minutes')
                ->value('avg_minutes');

            $data[] = [
                'manager' => $manager->name,
                'email' => $manager->email,
                'total_deals' => $totalDeals,
                'closed_deals' => $closedDeals,
                'in_progress' => $deals->where('status', 'In Progress')->count(),
                'new_deals' => $deals->where('status', 'New')->count(),
                'conversion' => $conversion,
                'avg_response_time' => $avgResponseTime ? round($avgResponseTime, 1) : null,
            ];
        }

        // Общая статистика
        $totalStats = [
            'total' => Deal::whereBetween('created_at', [$start, $end])->count(),
            'closed' => Deal::where('status', 'Closed')->whereBetween('created_at', [$start, $end])->count(),
            'hot_leads' => Deal::where('ai_score', '>', 80)->whereBetween('created_at', [$start, $end])->count(),
        ];

        return [
            'managers' => $data,
            'totals' => $totalStats,
            'period' => [
                'start' => $start->format('d.m.Y'),
                'end' => $end->format('d.m.Y'),
            ],
        ];
    }

    public function downloadPdf()
    {
        $data = $this->getReportData();

        $html = view('filament.pages.reports-pdf', $data)->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = 'crm-report-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Скачать PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action('downloadPdf'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
