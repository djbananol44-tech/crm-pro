<?php

namespace App\Jobs;

use App\Models\Deal;
use App\Models\SystemLog;
use App\Services\AiAnalysisService;
use App\Services\MetaApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Генерация AI анализа для сделки через очередь
 */
class GenerateAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $dealId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AiAnalysisService $ai, MetaApiService $metaApi): void
    {
        try {
            $deal = Deal::with(['contact', 'conversation'])->find($this->dealId);

            if (!$deal) {
                SystemLog::queue('warning', 'AI Analysis: Сделка не найдена', ['deal_id' => $this->dealId]);
                return;
            }

            if (!$ai->isAvailable()) {
                return;
            }

            // Получаем сообщения
            $messages = collect();
            if ($deal->conversation) {
                $messages = $metaApi->getMessages($deal->conversation->conversation_id);
            }

            if ($messages->isEmpty()) {
                return;
            }

            // Генерируем саммари
            $summary = $ai->getConversationSummary($messages);
            $score = $ai->getLeadScore($messages);

            $deal->update([
                'ai_summary' => $summary,
                'ai_score' => $score,
                'ai_summary_at' => now(),
            ]);

            SystemLog::queue('info', 'AI анализ успешно сгенерирован', [
                'deal_id' => $deal->id,
                'score' => $score,
            ]);

        } catch (\Exception $e) {
            SystemLog::queue('error', 'Ошибка генерации AI анализа', [
                'deal_id' => $this->dealId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
