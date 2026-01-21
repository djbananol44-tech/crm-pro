<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

/**
 * Унифицированный сервис действий над сделками.
 * 
 * Используется как из Filament (CRM), так и из Telegram бота.
 * Обеспечивает единую логику для всех операций.
 */
class DealActionService
{
    protected AiAnalysisService $aiService;
    protected TelegramService $telegram;
    protected MetaApiService $metaApi;

    public function __construct(
        AiAnalysisService $aiService,
        TelegramService $telegram,
        MetaApiService $metaApi
    ) {
        $this->aiService = $aiService;
        $this->telegram = $telegram;
        $this->metaApi = $metaApi;
    }

    /**
     * Взять сделку в работу (назначить менеджера).
     */
    public function claimDeal(Deal $deal, User $manager): array
    {
        if ($deal->manager_id && $deal->manager_id !== $manager->id) {
            return [
                'success' => false,
                'message' => 'Сделка уже назначена на другого менеджера',
            ];
        }

        if ($deal->manager_id === $manager->id) {
            return [
                'success' => false,
                'message' => 'Вы уже работаете с этой сделкой',
            ];
        }

        $deal->update([
            'manager_id' => $manager->id,
            'status' => 'In Progress',
        ]);

        $this->logActivity($deal, $manager, 'claim', 'Взял сделку в работу');

        Log::info('DealActionService: Deal claimed', [
            'deal_id' => $deal->id,
            'manager_id' => $manager->id,
        ]);

        return [
            'success' => true,
            'message' => '✅ Вы взяли сделку в работу!',
            'deal' => $deal->fresh(['contact', 'manager']),
        ];
    }

    /**
     * Закрыть сделку.
     */
    public function closeDeal(Deal $deal, User $actor, ?string $comment = null): array
    {
        $oldStatus = $deal->status;
        
        $updateData = ['status' => 'Closed'];
        if ($comment) {
            $updateData['comment'] = ($deal->comment ? $deal->comment . "\n\n" : '') . "[Закрыто] " . $comment;
        }

        $deal->update($updateData);

        $this->logActivity($deal, $actor, 'close', "Закрыл сделку (было: {$oldStatus})");

        // Если AI включен, оцениваем работу менеджера
        if ($this->aiService->isAvailable() && $deal->manager_id) {
            try {
                $messages = $this->metaApi->getMessages($deal->conversation->conversation_id ?? '');
                if (!empty($messages)) {
                    $evaluation = $this->aiService->evaluateManagerPerformance(collect($messages));
                    if ($evaluation) {
                        $deal->update([
                            'manager_rating' => $evaluation['rating'] ?? null,
                            'manager_review' => $evaluation['review'] ?? null,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('DealActionService: Failed to evaluate manager', ['error' => $e->getMessage()]);
            }
        }

        Log::info('DealActionService: Deal closed', [
            'deal_id' => $deal->id,
            'actor_id' => $actor->id,
        ]);

        return [
            'success' => true,
            'message' => '✅ Сделка успешно закрыта!',
            'deal' => $deal->fresh(['contact', 'manager']),
        ];
    }

    /**
     * Получить AI-анализ сделки.
     */
    public function getAiAnalysis(Deal $deal, bool $forceRefresh = false): array
    {
        if (!$this->aiService->isAvailable()) {
            return [
                'success' => false,
                'message' => '❌ AI-анализ недоступен. Настройте Gemini API ключ.',
            ];
        }

        // Если уже есть анализ и не нужно обновлять
        if (!$forceRefresh && $deal->ai_summary) {
            return [
                'success' => true,
                'message' => 'AI-анализ',
                'summary' => $deal->ai_summary,
                'score' => $deal->ai_score,
                'cached' => true,
            ];
        }

        try {
            // Получаем сообщения
            $conversationId = $deal->conversation?->conversation_id;
            if (!$conversationId) {
                return [
                    'success' => false,
                    'message' => '❌ Нет данных о переписке',
                ];
            }

            $messages = $this->metaApi->getMessages($conversationId);
            
            if (empty($messages)) {
                return [
                    'success' => false,
                    'message' => '❌ Сообщения не найдены',
                ];
            }

            // Получаем анализ
            $analysis = $this->aiService->getConversationSummary(collect($messages));
            $score = $this->aiService->getLeadScore(collect($messages));

            // Сохраняем результат
            $deal->update([
                'ai_summary' => $analysis,
                'ai_score' => $score,
                'ai_summary_at' => now(),
            ]);

            Log::info('DealActionService: AI analysis completed', [
                'deal_id' => $deal->id,
                'score' => $score,
            ]);

            return [
                'success' => true,
                'message' => '🤖 AI-анализ',
                'summary' => $analysis,
                'score' => $score,
                'cached' => false,
            ];

        } catch (\Exception $e) {
            Log::error('DealActionService: AI analysis failed', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '❌ Ошибка AI-анализа: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Установить напоминание.
     */
    public function setReminder(Deal $deal, User $actor, \DateTimeInterface $reminderAt): array
    {
        $deal->update([
            'reminder_at' => $reminderAt,
            'status' => $deal->status === 'New' ? 'In Progress' : $deal->status,
        ]);

        $this->logActivity($deal, $actor, 'reminder', 'Установил напоминание на ' . $reminderAt->format('d.m.Y H:i'));

        return [
            'success' => true,
            'message' => '⏰ Напоминание установлено на ' . $reminderAt->format('d.m.Y H:i'),
            'deal' => $deal->fresh(),
        ];
    }

    /**
     * Изменить статус сделки.
     */
    public function changeStatus(Deal $deal, User $actor, string $newStatus): array
    {
        $oldStatus = $deal->status;
        
        if ($oldStatus === $newStatus) {
            return [
                'success' => false,
                'message' => 'Статус уже установлен',
            ];
        }

        $deal->update(['status' => $newStatus]);

        $this->logActivity($deal, $actor, 'status_change', "Изменил статус: {$oldStatus} → {$newStatus}");

        // Если статус "Closed", вызываем closeDeal для полной обработки
        if ($newStatus === 'Closed') {
            return $this->closeDeal($deal, $actor);
        }

        return [
            'success' => true,
            'message' => "✅ Статус изменен на: {$newStatus}",
            'deal' => $deal->fresh(),
        ];
    }

    /**
     * Записать действие в лог.
     */
    protected function logActivity(Deal $deal, User $user, string $action, string $description): void
    {
        try {
            ActivityLog::create([
                'deal_id' => $deal->id,
                'user_id' => $user->id,
                'action' => $action,
                'description' => $description,
                'metadata' => [
                    'deal_status' => $deal->status,
                    'timestamp' => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('DealActionService: Failed to log activity', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
