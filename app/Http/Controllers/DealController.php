<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateManagerPerformance;
use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\User;
use App\Services\AiAnalysisService;
use App\Services\MetaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $query = Deal::with(['contact', 'conversation', 'manager']);

        // Ограничение для менеджеров
        if ($user->isManager()) {
            $query->where('manager_id', $user->id);
        }

        // Базовые фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('manager_id') && $user->isAdmin()) {
            $query->where('manager_id', $request->manager_id);
        }

        // Расширенные фильтры

        // SLA Overdue - сделки с просроченным временем ответа
        if ($request->boolean('sla_overdue')) {
            $query->where('status', '!=', 'Closed')
                ->whereNotNull('last_client_message_at')
                ->where(function ($q) {
                    $q->whereNull('last_manager_response_at')
                        ->orWhereColumn('last_manager_response_at', '<', 'last_client_message_at');
                })
                ->where('last_client_message_at', '<', now()->subMinutes(30));
        }

        // Priority - только приоритетные
        if ($request->boolean('priority')) {
            $query->where('is_priority', true)
                ->where('status', '!=', 'Closed');
        }

        // Unassigned - без назначенного менеджера
        if ($request->boolean('unassigned')) {
            $query->whereNull('manager_id');
        }

        // Unviewed - непросмотренные
        if ($request->boolean('unviewed')) {
            $query->where('is_viewed', false);
        }

        // Hot leads - AI Score > 80
        if ($request->boolean('hot_leads')) {
            $query->where('ai_score', '>', 80)
                ->where('status', '!=', 'Closed');
        }

        // Поиск: Full-Text Search (Postgres) с fallback на точный поиск
        if ($request->filled('search')) {
            $search = trim($request->search);

            // Определяем тип запроса
            $isExactIdSearch = $this->isExactIdSearch($search);

            if ($isExactIdSearch) {
                // Точный поиск по ID/PSID
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhereHas('contact', function ($contactQuery) use ($search) {
                            $contactQuery->where('psid', $search);
                        });
                });
            } else {
                // Полнотекстовый поиск через tsvector
                $query->where(function ($q) use ($search) {
                    // Сначала пробуем websearch_to_tsquery (поддерживает операторы)
                    $tsQuery = $this->buildSearchQuery($search);

                    $q->whereRaw(
                        "search_vector @@ to_tsquery('russian', ?)",
                        [$tsQuery]
                    )
                    // Fallback на ILIKE для частичных совпадений (короткие запросы)
                        ->orWhere(function ($fallback) use ($search) {
                            if (mb_strlen($search) < 3) {
                                $fallback->whereHas('contact', function ($cq) use ($search) {
                                    $cq->where('name', 'ilike', "%{$search}%")
                                        ->orWhere('psid', 'ilike', "{$search}%");
                                });
                            }
                        });
                });

                // Добавляем ранжирование по релевантности
                if (!$request->filled('sort') || $request->sort === 'smart') {
                    $query->orderByRaw(
                        "ts_rank(search_vector, to_tsquery('russian', ?)) DESC",
                        [$this->buildSearchQuery($search)]
                    );
                }
            }
        }

        // Сортировка
        $sortField = $request->get('sort', 'smart');
        $sortDir = $request->get('dir', 'desc');

        switch ($sortField) {
            case 'sla':
                // Сортировка по времени ожидания ответа
                $query->orderByRaw('
                    CASE WHEN status = \'Closed\' THEN 1 ELSE 0 END ASC,
                    last_client_message_at ASC NULLS LAST
                ');
                break;

            case 'priority':
                $query->orderBy('is_priority', 'desc')
                    ->orderBy('ai_score', 'desc')
                    ->orderBy('updated_at', 'desc');
                break;

            case 'score':
                $query->orderBy('ai_score', $sortDir === 'asc' ? 'asc' : 'desc')
                    ->orderBy('updated_at', 'desc');
                break;

            case 'created':
                $query->orderBy('created_at', $sortDir);
                break;

            case 'updated':
                $query->orderBy('updated_at', $sortDir);
                break;

            case 'smart':
            default:
                // Умная сортировка: приоритет > SLA просрочка > reminder > updated_at
                $query->orderByRaw('
                    CASE 
                        WHEN is_priority = true AND status != \'Closed\' THEN 0
                        WHEN last_client_message_at IS NOT NULL 
                             AND (last_manager_response_at IS NULL OR last_manager_response_at < last_client_message_at)
                             AND last_client_message_at < NOW() - INTERVAL \'30 minutes\'
                             AND status != \'Closed\' THEN 1
                        WHEN reminder_at IS NOT NULL AND reminder_at <= NOW() THEN 2 
                        ELSE 3 
                    END ASC
                ')
                    ->orderBy('updated_at', 'desc');
                break;
        }

        $deals = $query->paginate(15)->withQueryString();

        // Дополнительные данные для каждой сделки
        $deals->getCollection()->transform(function ($deal) {
            $deal->is_sla_overdue = $deal->isSlaOverdue();
            $deal->sla_overdue_minutes = $deal->getSlaOverdueMinutes();

            return $deal;
        });

        $managers = $user->isAdmin()
            ? User::where('role', 'manager')->orWhere('role', 'admin')->get(['id', 'name'])
            : collect();

        // Статистика для быстрых фильтров
        $stats = $this->getQuickFilterStats($user);

        return Inertia::render('Dashboard', [
            'deals' => $deals,
            'managers' => $managers,
            'filters' => [
                'status' => $request->status,
                'manager_id' => $request->manager_id,
                'search' => $request->search,
                'sla_overdue' => $request->boolean('sla_overdue'),
                'priority' => $request->boolean('priority'),
                'unassigned' => $request->boolean('unassigned'),
                'unviewed' => $request->boolean('unviewed'),
                'hot_leads' => $request->boolean('hot_leads'),
                'sort' => $sortField,
                'dir' => $sortDir,
            ],
            'statuses' => [
                ['value' => 'New', 'label' => 'Новая'],
                ['value' => 'In Progress', 'label' => 'В работе'],
                ['value' => 'Closed', 'label' => 'Закрыта'],
            ],
            'quickStats' => $stats,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Получить статистику для быстрых фильтров.
     */
    protected function getQuickFilterStats(User $user): array
    {
        $baseQuery = Deal::query();

        if ($user->isManager()) {
            $baseQuery->where('manager_id', $user->id);
        }

        return [
            'total' => (clone $baseQuery)->count(),
            'new' => (clone $baseQuery)->where('status', 'New')->count(),
            'in_progress' => (clone $baseQuery)->where('status', 'In Progress')->count(),
            'sla_overdue' => (clone $baseQuery)
                ->where('status', '!=', 'Closed')
                ->whereNotNull('last_client_message_at')
                ->where(function ($q) {
                    $q->whereNull('last_manager_response_at')
                        ->orWhereColumn('last_manager_response_at', '<', 'last_client_message_at');
                })
                ->where('last_client_message_at', '<', now()->subMinutes(30))
                ->count(),
            'priority' => (clone $baseQuery)
                ->where('is_priority', true)
                ->where('status', '!=', 'Closed')
                ->count(),
            'unassigned' => (clone $baseQuery)->whereNull('manager_id')->count(),
            'unviewed' => (clone $baseQuery)->where('is_viewed', false)->count(),
            'hot_leads' => (clone $baseQuery)
                ->where('ai_score', '>', 80)
                ->where('status', '!=', 'Closed')
                ->count(),
        ];
    }

    public function show(Deal $deal, MetaApiService $metaApi, AiAnalysisService $aiService): Response
    {
        $this->authorize('view', $deal);

        $user = Auth::user();

        // Логируем просмотр
        if (!$deal->is_viewed) {
            $deal->update(['is_viewed' => true]);
            ActivityLog::logViewed($deal, $user);
        }

        $deal->load(['contact', 'conversation', 'manager', 'activityLogs.user']);

        $messages = [];
        $messagesLimited = false;

        try {
            if ($deal->conversation) {
                $maxMessages = MetaApiService::MAX_MESSAGES_PER_CONVERSATION;
                $messages = $metaApi->getMessages($deal->conversation->conversation_id, $maxMessages);
                // Если вернулось ровно max — вероятно история обрезана
                $messagesLimited = count($messages) >= $maxMessages;
            }
        } catch (\Exception $e) {
            Log::error('DealController: Ошибка получения сообщений', ['error' => $e->getMessage()]);
        }

        // AI-анализ с Lead Score
        if ($aiService->isAvailable() && !empty($messages) && $deal->needsAiAnalysis()) {
            try {
                $analysis = $aiService->analyzeConversation(collect($messages));

                if ($analysis['summary']) {
                    $deal->update([
                        'ai_summary' => $analysis['summary'],
                        'ai_score' => $analysis['score'],
                        'ai_summary_at' => now(),
                    ]);
                    ActivityLog::logAiAnalyzed($deal, $analysis['score']);
                }
            } catch (\Exception $e) {
                Log::error('DealController: Ошибка AI-анализа', ['error' => $e->getMessage()]);
            }
        }

        $managers = User::whereIn('role', ['manager', 'admin'])->get(['id', 'name']);

        // Получаем логи активности
        $activityLogs = $deal->activityLogs->take(20)->map(fn ($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'icon' => $log->icon,
            'user' => $log->user ? ['name' => $log->user->name] : null,
            'created_at' => $log->created_at->format('d.m.Y H:i'),
        ]);

        return Inertia::render('ClientCard', [
            'deal' => [
                'id' => $deal->id,
                'status' => $deal->status,
                'comment' => $deal->comment,
                'reminder_at' => $deal->reminder_at?->format('Y-m-d\TH:i'),
                'created_at' => $deal->created_at->format('d.m.Y H:i'),
                'updated_at' => $deal->updated_at->format('d.m.Y H:i'),
                'manager_id' => $deal->manager_id,
                'manager' => $deal->manager ? [
                    'id' => $deal->manager->id,
                    'name' => $deal->manager->name,
                ] : null,
                'ai_summary' => $deal->ai_summary,
                'ai_score' => $deal->ai_score,
                'ai_summary_at' => $deal->ai_summary_at?->format('d.m.Y H:i'),
                'last_client_message_at' => $deal->last_client_message_at?->toISOString(),
                'last_manager_response_at' => $deal->last_manager_response_at?->toISOString(),
                'is_sla_overdue' => $deal->isSlaOverdue(),
                'sla_overdue_minutes' => $deal->getSlaOverdueMinutes(),
                'is_priority' => $deal->is_priority,
                'manager_rating' => $deal->manager_rating,
                'manager_review' => $deal->manager_review,
            ],
            'contact' => [
                'id' => $deal->contact->id,
                'psid' => $deal->contact->psid,
                'name' => $deal->contact->name ?? $deal->contact->full_name,
                'first_name' => $deal->contact->first_name,
                'last_name' => $deal->contact->last_name,
            ],
            'conversation' => $deal->conversation ? [
                'id' => $deal->conversation->id,
                'conversation_id' => $deal->conversation->conversation_id,
                'link' => $deal->conversation->meta_business_suite_url ?? $deal->conversation->link,
                'platform' => $deal->conversation->platform,
                'updated_time' => $deal->conversation->updated_time?->format('d.m.Y H:i'),
                'labels' => $deal->conversation->formatted_labels ?? [],
            ] : null,
            'messages' => $messages,
            'messagesLimited' => $messagesLimited,
            'maxMessages' => MetaApiService::MAX_MESSAGES_PER_CONVERSATION,
            'managers' => $managers,
            'statuses' => [
                ['value' => 'New', 'label' => 'Новая'],
                ['value' => 'In Progress', 'label' => 'В работе'],
                ['value' => 'Closed', 'label' => 'Закрыта'],
            ],
            'isAdmin' => $user->isAdmin(),
            'canChangeManager' => $deal->canChangeManager($user),
            'aiEnabled' => $aiService->isAvailable(),
            'activityLogs' => $activityLogs,
        ]);
    }

    public function update(Request $request, Deal $deal)
    {
        $this->authorize('update', $deal);

        $user = Auth::user();

        $validated = $request->validate([
            'status' => 'sometimes|in:New,In Progress,Closed',
            'comment' => 'nullable|string|max:5000',
            'reminder_at' => 'nullable|date',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $oldStatus = $deal->status;
        $oldManagerId = $deal->manager_id;
        $oldComment = $deal->comment;

        // Проверка на изменение manager_id
        if (isset($validated['manager_id']) && !$deal->canChangeManager($user)) {
            unset($validated['manager_id']);
        }

        $deal->update($validated);

        // Логируем изменение статуса
        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            ActivityLog::logStatusChanged($deal, $oldStatus, $validated['status'], $user);

            // Если сделка закрыта — запускаем оценку менеджера
            if ($validated['status'] === 'Closed') {
                EvaluateManagerPerformance::dispatch($deal->id);
            }
        }

        // Логируем назначение менеджера
        if (isset($validated['manager_id']) && $oldManagerId !== $validated['manager_id']) {
            $oldManager = $oldManagerId ? User::find($oldManagerId) : null;
            $newManager = User::find($validated['manager_id']);
            if ($newManager) {
                ActivityLog::logManagerAssigned($deal, $oldManager, $newManager, $user);
            }
        }

        // Логируем изменение комментария
        if (isset($validated['comment']) && $oldComment !== $validated['comment']) {
            ActivityLog::logCommentAdded($deal, $user, $validated['comment']);
        }

        // Логируем установку напоминания
        if (isset($validated['reminder_at']) && $validated['reminder_at']) {
            ActivityLog::logReminderSet($deal, $user, $validated['reminder_at']);
        }

        return redirect()->back()->with('success', 'Сделка успешно обновлена');
    }

    public function assignToMe(Deal $deal)
    {
        $this->authorize('assignToMe', $deal);

        $user = Auth::user();
        $oldManager = $deal->manager;

        $deal->update(['manager_id' => $user->id]);

        ActivityLog::logManagerAssigned($deal, $oldManager, $user, $user);

        return redirect()->back()->with('success', 'Вы назначены ответственным');
    }

    public function refreshAiSummary(Deal $deal, MetaApiService $metaApi, AiAnalysisService $aiService)
    {
        $this->authorize('requestAiAnalysis', $deal);

        if (!$aiService->isAvailable()) {
            return redirect()->back()->with('error', 'AI-сервис недоступен');
        }

        try {
            $deal->load('conversation');

            if (!$deal->conversation) {
                return redirect()->back()->with('error', 'Нет связанной беседы');
            }

            $messages = $metaApi->getMessages($deal->conversation->conversation_id, 20);

            if (empty($messages)) {
                return redirect()->back()->with('error', 'Нет сообщений для анализа');
            }

            $analysis = $aiService->analyzeConversation(collect($messages));

            if ($analysis['summary']) {
                $deal->update([
                    'ai_summary' => $analysis['summary'],
                    'ai_score' => $analysis['score'],
                    'ai_summary_at' => now(),
                ]);

                ActivityLog::logAiAnalyzed($deal, $analysis['score']);

                return redirect()->back()->with('success', 'AI-анализ обновлён');
            }

            return redirect()->back()->with('error', 'Не удалось получить анализ');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка: '.$e->getMessage());
        }
    }

    public function translateMessages(Deal $deal, MetaApiService $metaApi, AiAnalysisService $aiService)
    {
        $this->authorize('translate', $deal);

        if (!$aiService->isAvailable()) {
            return response()->json(['error' => 'AI-сервис недоступен'], 400);
        }

        try {
            $deal->load('conversation');

            if (!$deal->conversation) {
                return response()->json(['error' => 'Нет беседы'], 400);
            }

            $messages = $metaApi->getMessages($deal->conversation->conversation_id, 20);

            if (empty($messages)) {
                return response()->json(['error' => 'Нет сообщений'], 400);
            }

            $translation = $aiService->translateToRussian(collect($messages));

            if ($translation) {
                return response()->json(['translation' => $translation]);
            }

            return response()->json(['error' => 'Не удалось перевести'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ПОИСКОВЫЕ ХЕЛПЕРЫ
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Проверить, является ли запрос точным поиском по ID/PSID.
     *
     * Паттерны:
     * - Числовой ID: "123", "45678"
     * - PSID Meta: длинный числовой токен > 10 цифр
     */
    protected function isExactIdSearch(string $search): bool
    {
        // Только цифры — возможно ID или PSID
        if (preg_match('/^\d+$/', $search)) {
            return true;
        }

        // Паттерн Meta PSID (обычно > 15 цифр)
        if (preg_match('/^\d{15,}$/', $search)) {
            return true;
        }

        return false;
    }

    /**
     * Построить tsquery из пользовательского запроса.
     *
     * Примеры преобразований:
     * - "Иван Петров" → "Иван:* & Петров:*"
     * - "цена доставка" → "цена:* & доставка:*"
     * - "оплата" → "оплата:*"
     */
    protected function buildSearchQuery(string $search): string
    {
        // Очищаем от спецсимволов PostgreSQL tsquery
        $cleaned = preg_replace('/[!&|():*<>\'"]/', ' ', $search);

        // Разбиваем на слова
        $words = array_filter(preg_split('/\s+/', $cleaned));

        if (empty($words)) {
            return '';
        }

        // Формируем tsquery с prefix matching (:*)
        $terms = array_map(function ($word) {
            // Минимальная длина слова для индекса
            if (mb_strlen($word) < 2) {
                return;
            }

            return $word.':*';
        }, $words);

        $terms = array_filter($terms);

        if (empty($terms)) {
            return '';
        }

        // Соединяем через AND для более точных результатов
        return implode(' & ', $terms);
    }
}
