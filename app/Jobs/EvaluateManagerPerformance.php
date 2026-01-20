<?php

namespace App\Jobs;

use App\Models\Deal;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\AiAnalysisService;
use App\Services\MetaApiService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EvaluateManagerPerformance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    protected int $dealId;

    public function __construct(int $dealId)
    {
        $this->dealId = $dealId;
    }

    public function handle(MetaApiService $metaApi, AiAnalysisService $aiService, TelegramService $telegram): void
    {
        $deal = Deal::with(['contact', 'conversation', 'manager'])->find($this->dealId);

        if (!$deal) {
            Log::warning('EvaluateManagerPerformance: Сделка не найдена', ['deal_id' => $this->dealId]);
            return;
        }

        if ($deal->manager_rating !== null) {
            Log::info('EvaluateManagerPerformance: Уже оценена', ['deal_id' => $this->dealId]);
            return;
        }

        if (!$aiService->isAvailable()) {
            Log::info('EvaluateManagerPerformance: AI недоступен');
            return;
        }

        Log::info('EvaluateManagerPerformance: Начало оценки', ['deal_id' => $this->dealId]);

        try {
            // Получаем сообщения
            $messages = [];
            if ($deal->conversation) {
                $messages = $metaApi->getMessages($deal->conversation->conversation_id, 50);
            }

            if (empty($messages)) {
                Log::info('EvaluateManagerPerformance: Нет сообщений для оценки');
                return;
            }

            // Оцениваем менеджера
            $managerName = $deal->manager?->name ?? 'Менеджер';
            $evaluation = $aiService->evaluateManagerPerformance($messages, $managerName);

            if ($evaluation['rating'] === null) {
                Log::warning('EvaluateManagerPerformance: Не удалось получить оценку');
                return;
            }

            // Сохраняем оценку
            $deal->update([
                'manager_rating' => $evaluation['rating'],
                'manager_review' => $evaluation['review'],
                'rated_at' => now(),
            ]);

            ActivityLog::logRated($deal, $evaluation['rating'], $evaluation['review'] ?? '');

            Log::info('EvaluateManagerPerformance: Оценка сохранена', [
                'deal_id' => $this->dealId,
                'rating' => $evaluation['rating'],
            ]);

            // Проверяем среднюю оценку менеджера за день
            $this->checkManagerDailyRating($deal->manager, $telegram);

        } catch (\Exception $e) {
            Log::error('EvaluateManagerPerformance: Ошибка', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Проверить среднюю оценку менеджера за день и уведомить админов.
     */
    protected function checkManagerDailyRating(?User $manager, TelegramService $telegram): void
    {
        if (!$manager) return;

        $today = Carbon::today();

        // Получаем среднюю оценку за сегодня
        $avgRating = Deal::where('manager_id', $manager->id)
            ->whereDate('rated_at', $today)
            ->whereNotNull('manager_rating')
            ->avg('manager_rating');

        if ($avgRating === null) return;

        // Если средняя оценка ниже 4.0 — уведомляем админов
        if ($avgRating < 4.0) {
            $dealsCount = Deal::where('manager_id', $manager->id)
                ->whereDate('rated_at', $today)
                ->whereNotNull('manager_rating')
                ->count();

            // Уведомляем только если есть хотя бы 2 оценки
            if ($dealsCount < 2) return;

            $message = <<<MSG
⚠️ <b>Внимание! Качество работы снизилось</b>

👤 Менеджер: <b>{$manager->name}</b>
📊 Средняя оценка за сегодня: <b>{$avgRating}/5</b>
📋 Закрытых сделок: {$dealsCount}

Рекомендуется провести проверку качества обслуживания.
MSG;

            $telegram->notifyAdmins($message);

            Log::warning('EvaluateManagerPerformance: Низкая оценка менеджера', [
                'manager_id' => $manager->id,
                'avg_rating' => $avgRating,
            ]);
        }
    }
}
