<?php

namespace App\Jobs;

use App\Models\Deal;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportDealsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 минут для больших экспортов

    public function __construct(
        public int $userId,
        public array $filters,
        public string $format = 'xlsx', // xlsx или csv
        public ?string $exportId = null
    ) {
        $this->exportId = $exportId ?? uniqid('export_', true);
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::error('ExportDealsJob: User not found', ['user_id' => $this->userId]);

            return;
        }

        $startTime = microtime(true);

        SystemLog::queue('info', 'Начат экспорт сделок', [
            'export_id' => $this->exportId,
            'user_id' => $this->userId,
            'format' => $this->format,
            'filters' => $this->sanitizeFilters($this->filters),
        ]);

        try {
            // Строим запрос с фильтрами
            $query = $this->buildQuery($user);

            // Получаем данные
            $deals = $query->get();

            // Генерируем файл
            $filename = $this->generateFilename();
            $path = 'exports/'.$filename;

            // Создаём директорию если нужно
            Storage::disk('local')->makeDirectory('exports');

            // Подготавливаем данные для экспорта
            $exportData = $this->prepareExportData($deals);

            // Экспортируем
            if ($this->format === 'csv') {
                $this->exportToCsv($exportData, $path);
            } else {
                $this->exportToXlsx($exportData, $path);
            }

            $duration = round(microtime(true) - $startTime, 2);

            SystemLog::queue('info', 'Экспорт сделок завершён', [
                'export_id' => $this->exportId,
                'user_id' => $this->userId,
                'filename' => $filename,
                'deals_count' => $deals->count(),
                'duration_sec' => $duration,
                'file_size' => Storage::disk('local')->size($path),
            ]);

            // Сохраняем информацию об экспорте в кэш для отслеживания
            // user_id используется для проверки доступа при скачивании
            cache()->put(
                'export:'.$this->exportId,
                [
                    'status' => 'completed',
                    'filename' => $filename,
                    'path' => $path,
                    'deals_count' => $deals->count(),
                    'user_id' => $this->userId,
                    'created_at' => now()->toISOString(),
                    'expires_at' => now()->addHours(24)->toISOString(),
                ],
                now()->addHours(24)
            );

        } catch (\Exception $e) {
            SystemLog::queue('error', 'Ошибка экспорта сделок', [
                'export_id' => $this->exportId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            cache()->put(
                'export:'.$this->exportId,
                [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'user_id' => $this->userId,
                ],
                now()->addHours(1)
            );

            throw $e;
        }
    }

    /**
     * Построить запрос с фильтрами.
     */
    protected function buildQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Deal::with(['contact', 'conversation', 'manager']);

        // Менеджер видит только свои сделки
        if ($user->isManager()) {
            $query->where('manager_id', $user->id);
        }

        // Фильтр по менеджеру (только для админа)
        if ($user->isAdmin() && !empty($this->filters['manager_id'])) {
            $query->where('manager_id', $this->filters['manager_id']);
        }

        // Фильтр по статусу
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // Фильтр по дате создания
        if (!empty($this->filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($this->filters['start_date'])->startOfDay());
        }

        if (!empty($this->filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($this->filters['end_date'])->endOfDay());
        }

        // Фильтр по поиску
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('contact', function ($cq) use ($search) {
                    $cq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('psid', 'ilike', "%{$search}%");
                })
                    ->orWhere('comment', 'ilike', "%{$search}%")
                    ->orWhere('ai_summary', 'ilike', "%{$search}%");
            });
        }

        // Фильтр по приоритету
        if (!empty($this->filters['priority'])) {
            $query->where('is_priority', true);
        }

        // Фильтр по SLA
        if (!empty($this->filters['sla_overdue'])) {
            $query->whereNotNull('last_client_message_at')
                ->where(function ($q) {
                    $q->whereNull('last_manager_response_at')
                        ->orWhereRaw(
                            'EXTRACT(EPOCH FROM (COALESCE(last_manager_response_at, NOW()) - last_client_message_at)) / 60 > 30'
                        );
                });
        }

        // Фильтр "без менеджера"
        if (!empty($this->filters['unassigned'])) {
            $query->whereNull('manager_id');
        }

        return $query->orderBy('updated_at', 'desc');
    }

    /**
     * Подготовить данные для экспорта.
     */
    protected function prepareExportData($deals): array
    {
        $data = [];

        // Заголовки
        $data[] = [
            'ID',
            'Дата создания',
            'Дата обновления',
            'Контакт',
            'PSID',
            'Платформа',
            'Менеджер',
            'Статус',
            'Приоритет',
            'AI Score',
            'Напоминание',
            'Последнее сообщение клиента',
            'Последний ответ менеджера',
            'SLA (мин)',
            'SLA Просрочено',
            'Комментарий',
            'AI Summary',
            'Текст последнего сообщения',
        ];

        foreach ($deals as $deal) {
            // Расчёт SLA
            $slaMinutes = null;
            $slaOverdue = false;

            if ($deal->last_client_message_at) {
                $responseTime = $deal->last_manager_response_at ?? now();
                $slaMinutes = round($deal->last_client_message_at->diffInMinutes($responseTime), 1);
                $slaOverdue = $slaMinutes > 30;
            }

            $data[] = [
                $deal->id,
                $deal->created_at?->format('d.m.Y H:i'),
                $deal->updated_at?->format('d.m.Y H:i'),
                $deal->contact?->name ?? $deal->contact?->full_name ?? '-',
                $deal->contact?->psid ?? '-',
                $deal->conversation?->platform ?? '-',
                $deal->manager?->name ?? 'Не назначен',
                $this->translateStatus($deal->status),
                $deal->is_priority ? 'Да' : 'Нет',
                $deal->ai_score ?? '-',
                $deal->reminder_at?->format('d.m.Y H:i') ?? '-',
                $deal->last_client_message_at?->format('d.m.Y H:i') ?? '-',
                $deal->last_manager_response_at?->format('d.m.Y H:i') ?? '-',
                $slaMinutes ?? '-',
                $slaOverdue ? 'Да' : 'Нет',
                mb_substr($deal->comment ?? '', 0, 500),
                mb_substr($deal->ai_summary ?? '', 0, 500),
                mb_substr($deal->last_message_text ?? '', 0, 300),
            ];
        }

        return $data;
    }

    /**
     * Экспорт в CSV.
     */
    protected function exportToCsv(array $data, string $path): void
    {
        $content = '';

        foreach ($data as $row) {
            $escapedRow = array_map(function ($cell) {
                $cell = str_replace('"', '""', (string) $cell);

                return '"'.$cell.'"';
            }, $row);
            $content .= implode(',', $escapedRow)."\n";
        }

        // BOM для корректного отображения кириллицы в Excel
        $content = "\xEF\xBB\xBF".$content;

        Storage::disk('local')->put($path, $content);
    }

    /**
     * Экспорт в XLSX.
     */
    protected function exportToXlsx(array $data, string $path): void
    {
        $export = new \App\Exports\DealsArrayExport($data);
        Excel::store($export, $path, 'local');
    }

    /**
     * Генерировать имя файла.
     */
    protected function generateFilename(): string
    {
        $date = now()->format('Y-m-d_H-i-s');
        $extension = $this->format === 'csv' ? 'csv' : 'xlsx';

        return "deals_export_{$date}_{$this->exportId}.{$extension}";
    }

    /**
     * Перевод статуса.
     */
    protected function translateStatus(string $status): string
    {
        return match ($status) {
            'New' => 'Новая',
            'In Progress' => 'В работе',
            'Closed' => 'Закрыта',
            default => $status,
        };
    }

    /**
     * Очистить фильтры от PII для логирования.
     */
    protected function sanitizeFilters(array $filters): array
    {
        $safe = $filters;

        // Не логируем полный поисковый запрос
        if (isset($safe['search'])) {
            $safe['search'] = mb_strlen($safe['search']).' chars';
        }

        return $safe;
    }

    public function failed(\Throwable $exception): void
    {
        SystemLog::queue('error', 'ExportDealsJob failed', [
            'export_id' => $this->exportId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
