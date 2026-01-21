<?php

namespace App\Http\Controllers;

use App\Jobs\ExportDealsJob;
use App\Models\SystemLog;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Запустить экспорт сделок (асинхронно).
     */
    public function startExport(Request $request)
    {
        $this->authorize('export-deals');

        $user = Auth::user();

        $validated = $request->validate([
            'format' => 'sometimes|in:csv,xlsx',
            'status' => 'nullable|in:New,In Progress,Closed',
            'manager_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'search' => 'nullable|string|max:255',
            'priority' => 'nullable|boolean',
            'sla_overdue' => 'nullable|boolean',
            'unassigned' => 'nullable|boolean',
        ]);

        $format = $validated['format'] ?? 'xlsx';
        $exportId = uniqid('export_', true);

        // Сохраняем начальный статус с user_id для проверки доступа
        cache()->put(
            'export:'.$exportId,
            [
                'status' => 'pending',
                'user_id' => $user->id,
                'created_at' => now()->toISOString(),
            ],
            now()->addHours(1)
        );

        // Запускаем джобу
        ExportDealsJob::dispatch(
            $user->id,
            $validated,
            $format,
            $exportId
        )->onQueue('default');

        SystemLog::queue('info', 'Запущен экспорт сделок', [
            'user_id' => $user->id,
            'export_id' => $exportId,
            'format' => $format,
        ]);

        return response()->json([
            'success' => true,
            'export_id' => $exportId,
            'message' => 'Экспорт запущен. Файл будет готов через несколько секунд.',
        ]);
    }

    /**
     * Проверить статус экспорта.
     */
    public function checkStatus(Request $request, string $exportId)
    {
        $status = cache()->get('export:'.$exportId);

        if (!$status) {
            return response()->json([
                'success' => false,
                'error' => 'Экспорт не найден или истёк',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $status['status'],
            'filename' => $status['filename'] ?? null,
            'deals_count' => $status['deals_count'] ?? null,
            'error' => $status['error'] ?? null,
            'download_url' => $status['status'] === 'completed'
                ? route('export.download', ['exportId' => $exportId])
                : null,
        ]);
    }

    /**
     * Скачать экспортированный файл.
     *
     * Проверяем, что пользователь может скачать только свой экспорт.
     */
    public function download(Request $request, string $exportId): StreamedResponse
    {
        $user = Auth::user();
        $status = cache()->get('export:'.$exportId);

        if (!$status || $status['status'] !== 'completed') {
            abort(404, 'Файл не найден');
        }

        // Проверяем, что экспорт принадлежит текущему пользователю
        // или пользователь админ
        if (isset($status['user_id']) && $status['user_id'] !== $user->id && !$user->isAdmin()) {
            abort(403, 'Доступ запрещён');
        }

        $path = $status['path'];

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Файл не найден');
        }

        SystemLog::queue('info', 'Скачан экспорт', [
            'user_id' => $user->id,
            'export_id' => $exportId,
            'filename' => $status['filename'],
        ]);

        return Storage::disk('local')->download($path, $status['filename']);
    }

    /**
     * Быстрый синхронный экспорт (для малых объёмов < 1000).
     */
    public function quickExport(Request $request)
    {
        $this->authorize('export-deals');

        $user = Auth::user();

        $validated = $request->validate([
            'format' => 'sometimes|in:csv,xlsx',
            'status' => 'nullable|in:New,In Progress,Closed',
            'manager_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'search' => 'nullable|string|max:255',
        ]);

        $format = $validated['format'] ?? 'xlsx';

        // Строим запрос
        $query = \App\Models\Deal::with(['contact', 'conversation', 'manager']);

        if ($user->isManager()) {
            $query->where('manager_id', $user->id);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['manager_id']) && $user->isAdmin()) {
            $query->where('manager_id', $validated['manager_id']);
        }

        if (!empty($validated['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($validated['start_date'])->startOfDay());
        }

        if (!empty($validated['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($validated['end_date'])->endOfDay());
        }

        // Проверяем количество
        $count = $query->count();

        if ($count > 1000) {
            return response()->json([
                'success' => false,
                'error' => 'Слишком много записей. Используйте асинхронный экспорт.',
                'count' => $count,
            ], 400);
        }

        $deals = $query->orderBy('updated_at', 'desc')->get();

        // Подготовка данных
        $data = $this->prepareExportData($deals);

        $filename = 'deals_'.now()->format('Y-m-d_H-i').'.'.$format;

        SystemLog::queue('info', 'Быстрый экспорт сделок', [
            'user_id' => $user->id,
            'count' => $count,
            'format' => $format,
        ]);

        if ($format === 'csv') {
            return $this->downloadCsv($data, $filename);
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DealsArrayExport($data),
            $filename
        );
    }

    /**
     * Получить отчёт.
     */
    public function getReport(Request $request, ReportService $reportService)
    {
        $this->authorize('view-reports');

        $user = Auth::user();

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'manager_id' => 'nullable|integer',
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        // Менеджер видит только свои данные
        // Админ может выбирать любого менеджера или смотреть общие данные
        $managerId = null;
        if ($user->isManager()) {
            $managerId = $user->id; // Принудительно только свои данные
        } elseif ($user->isAdmin() && !empty($validated['manager_id'])) {
            $managerId = $validated['manager_id'];
        }

        $report = $reportService->getReport($start, $end, $managerId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Подготовить данные для экспорта.
     */
    protected function prepareExportData($deals): array
    {
        $data = [];

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
            'SLA (мин)',
            'SLA Просрочено',
            'Комментарий',
        ];

        foreach ($deals as $deal) {
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
                $deal->contact?->name ?? '-',
                $deal->contact?->psid ?? '-',
                $deal->conversation?->platform ?? '-',
                $deal->manager?->name ?? 'Не назначен',
                $this->translateStatus($deal->status),
                $deal->is_priority ? 'Да' : 'Нет',
                $deal->ai_score ?? '-',
                $deal->reminder_at?->format('d.m.Y H:i') ?? '-',
                $slaMinutes ?? '-',
                $slaOverdue ? 'Да' : 'Нет',
                mb_substr($deal->comment ?? '', 0, 200),
            ];
        }

        return $data;
    }

    /**
     * Скачать CSV.
     */
    protected function downloadCsv(array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // BOM для Excel
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($data as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function translateStatus(string $status): string
    {
        return match ($status) {
            'New' => 'Новая',
            'In Progress' => 'В работе',
            'Closed' => 'Закрыта',
            default => $status,
        };
    }
}
