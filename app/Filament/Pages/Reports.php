<?php

namespace App\Filament\Pages;

use App\Jobs\ExportDealsJob;
use App\Models\Deal;
use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Отчёты';

    protected static ?string $title = 'Отчёты и аналитика';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.reports';

    public ?string $period = 'month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $managerId = null;

    public ?string $exportFormat = 'xlsx';

    protected ReportService $reportService;

    public function boot(ReportService $reportService): void
    {
        $this->reportService = $reportService;
    }

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('period')
                    ->label('Период')
                    ->options([
                        'today' => 'Сегодня',
                        'yesterday' => 'Вчера',
                        'week' => 'Эта неделя',
                        'last_week' => 'Прошлая неделя',
                        'month' => 'Этот месяц',
                        'last_month' => 'Прошлый месяц',
                        'quarter' => 'Этот квартал',
                        'custom' => 'Свой период',
                    ])
                    ->default('month')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateDates()),

                DatePicker::make('startDate')
                    ->label('Начало периода')
                    ->required()
                    ->visible(fn () => $this->period === 'custom'),

                DatePicker::make('endDate')
                    ->label('Конец периода')
                    ->required()
                    ->visible(fn () => $this->period === 'custom'),

                Select::make('managerId')
                    ->label('Менеджер')
                    ->options(
                        User::where('role', 'manager')
                            ->pluck('name', 'id')
                            ->prepend('Все менеджеры', '')
                    )
                    ->placeholder('Все менеджеры'),
            ])
            ->columns(4);
    }

    public function updateDates(): void
    {
        $presets = ReportService::getPeriodPresets();

        if (isset($presets[$this->period])) {
            $preset = $presets[$this->period];
            $this->startDate = $preset['start']->format('Y-m-d');
            $this->endDate = $preset['end']->format('Y-m-d');
        }
    }

    public function getReportData(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        return $this->reportService->getReport($start, $end, $this->managerId);
    }

    /**
     * Старый метод для совместимости.
     */
    public function getManagersReportData(): array
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
        $data = $this->getManagersReportData();

        $html = view('filament.pages.reports-pdf', $data)->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = 'crm-report-'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function exportCsv()
    {
        return $this->startExport('csv');
    }

    public function exportXlsx()
    {
        return $this->startExport('xlsx');
    }

    protected function startExport(string $format)
    {
        $user = auth()->user();
        $exportId = uniqid('export_', true);

        $filters = [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'manager_id' => $this->managerId,
        ];

        // Сохраняем статус
        cache()->put(
            'export:'.$exportId,
            ['status' => 'pending', 'created_at' => now()->toISOString()],
            now()->addHours(1)
        );

        // Запускаем Job
        ExportDealsJob::dispatch($user->id, $filters, $format, $exportId)
            ->onQueue('default');

        Notification::make()
            ->title('Экспорт запущен')
            ->body('Файл будет готов через несколько секунд. ID: '.substr($exportId, -8))
            ->success()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Скачать')
                    ->url(route('export.download', ['exportId' => $exportId]))
                    ->openUrlInNewTab(),
            ])
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Экспорт CSV')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action('exportCsv'),

            Action::make('exportXlsx')
                ->label('Экспорт XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action('exportXlsx'),

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
