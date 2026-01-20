<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\AiAnalysisService;
use App\Services\MetaApiService;
use App\Jobs\EvaluateManagerPerformance;
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

        if ($user->isManager()) {
            $query->where('manager_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('manager_id') && $user->isAdmin()) {
            $query->where('manager_id', $request->manager_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($contactQuery) use ($search) {
                        $contactQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('psid', 'like', "%{$search}%");
                    });
            });
        }

        // Сортировка: приоритет > SLA просрочка > reminder > updated_at
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

        $deals = $query->paginate(15)->withQueryString();

        $managers = $user->isAdmin() 
            ? User::where('role', 'manager')->orWhere('role', 'admin')->get(['id', 'name'])
            : collect();

        return Inertia::render('Dashboard', [
            'deals' => $deals,
            'managers' => $managers,
            'filters' => [
                'status' => $request->status,
                'manager_id' => $request->manager_id,
                'search' => $request->search,
            ],
            'statuses' => [
                ['value' => 'New', 'label' => 'Новая'],
                ['value' => 'In Progress', 'label' => 'В работе'],
                ['value' => 'Closed', 'label' => 'Закрыта'],
            ],
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function show(Deal $deal, MetaApiService $metaApi, AiAnalysisService $aiService): Response
    {
        $user = Auth::user();

        if ($user->isManager() && $deal->manager_id !== $user->id && $deal->manager_id !== null) {
            abort(403, 'У вас нет доступа к этой сделке');
        }

        // Логируем просмотр
        if (!$deal->is_viewed) {
            $deal->update(['is_viewed' => true]);
            ActivityLog::logViewed($deal, $user);
        }

        $deal->load(['contact', 'conversation', 'manager', 'activityLogs.user']);

        $messages = [];
        try {
            if ($deal->conversation) {
                $messages = $metaApi->getMessages($deal->conversation->conversation_id, 20);
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
        $activityLogs = $deal->activityLogs->take(20)->map(fn($log) => [
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
                'link' => $deal->conversation->link,
                'platform' => $deal->conversation->platform,
                'updated_time' => $deal->conversation->updated_time?->format('d.m.Y H:i'),
            ] : null,
            'messages' => $messages,
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
        $user = Auth::user();

        if ($user->isManager() && $deal->manager_id !== $user->id && $deal->manager_id !== null) {
            abort(403, 'У вас нет доступа к этой сделке');
        }

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
        $user = Auth::user();

        if (!$deal->canChangeManager($user)) {
            return redirect()->back()->with('error', 'Невозможно изменить менеджера');
        }

        $oldManager = $deal->manager;
        $deal->update(['manager_id' => $user->id]);
        
        ActivityLog::logManagerAssigned($deal, $oldManager, $user, $user);

        return redirect()->back()->with('success', 'Вы назначены ответственным');
    }

    public function refreshAiSummary(Deal $deal, MetaApiService $metaApi, AiAnalysisService $aiService)
    {
        $user = Auth::user();

        if ($user->isManager() && $deal->manager_id !== $user->id && $deal->manager_id !== null) {
            abort(403, 'У вас нет доступа к этой сделке');
        }

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
            return redirect()->back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    public function translateMessages(Deal $deal, MetaApiService $metaApi, AiAnalysisService $aiService)
    {
        $user = Auth::user();

        if ($user->isManager() && $deal->manager_id !== $user->id && $deal->manager_id !== null) {
            abort(403);
        }

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
}
