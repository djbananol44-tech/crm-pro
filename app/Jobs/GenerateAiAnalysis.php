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
use Illuminate\Support\Facades\Log;

/**
 * Генерация AI анализа для сделки через очередь.
 *
 * Особенности:
 * - Graceful degradation при ошибках (не падает)
 * - Сохраняет analysis_failed_at при критических ошибках
 * - Логирование с контекстом deal_id, contact_id
 */
class GenerateAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public int $backoff = 30;

    public function __construct(
        public int $dealId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AiAnalysisService $ai, MetaApiService $metaApi): void
    {
        $startTime = microtime(true);

        try {
            $deal = Deal::with(['contact', 'conversation'])->find($this->dealId);

            if (!$deal) {
                Log::warning('GenerateAiAnalysis: Сделка не найдена', ['deal_id' => $this->dealId]);

                return;
            }

            if (!$ai->isAvailable()) {
                Log::info('GenerateAiAnalysis: AI сервис недоступен', [
                    'deal_id' => $deal->id,
                    'contact_id' => $deal->contact_id,
                ]);

                return;
            }

            // Получаем сообщения (максимум 20 по политике Meta)
            $messages = collect();
            if ($deal->conversation) {
                $messages = $metaApi->getMessages(
                    $deal->conversation->conversation_id,
                    MetaApiService::MAX_MESSAGES_PER_CONVERSATION
                );
            }

            if ($messages->isEmpty()) {
                Log::info('GenerateAiAnalysis: Нет сообщений для анализа', [
                    'deal_id' => $deal->id,
                    'contact_id' => $deal->contact_id,
                ]);

                return;
            }

            // Выполняем анализ и сохраняем
            $success = $ai->analyzeAndSaveDeal($deal, $messages);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if ($success) {
                // Refresh для получения обновлённых данных
                $deal->refresh();

                Log::info('GenerateAiAnalysis: Анализ успешен', [
                    'deal_id' => $deal->id,
                    'contact_id' => $deal->contact_id,
                    'score' => $deal->ai_score,
                    'latency_ms' => $latency,
                ]);

                SystemLog::queue('info', 'AI анализ сделки завершён', [
                    'deal_id' => $deal->id,
                    'contact_id' => $deal->contact_id,
                    'score' => $deal->ai_score,
                    'latency_ms' => $latency,
                ]);
            } else {
                Log::warning('GenerateAiAnalysis: Анализ не вернул результат', [
                    'deal_id' => $deal->id,
                    'contact_id' => $deal->contact_id,
                    'latency_ms' => $latency,
                ]);
            }

        } catch (\Exception $e) {
            // Логируем, но НЕ пробрасываем — job не должен падать
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('GenerateAiAnalysis: Ошибка', [
                'deal_id' => $this->dealId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'latency_ms' => $latency,
            ]);

            SystemLog::queue('error', 'Ошибка AI анализа сделки', [
                'deal_id' => $this->dealId,
                'error' => $e->getMessage(),
                'latency_ms' => $latency,
            ]);

            // Помечаем сделку как failed (если она существует)
            try {
                Deal::where('id', $this->dealId)->update([
                    'analysis_failed_at' => now(),
                ]);
            } catch (\Exception $updateError) {
                // Игнорируем ошибку обновления
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateAiAnalysis: Job failed после всех попыток', [
            'deal_id' => $this->dealId,
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        SystemLog::queue('critical', 'AI анализ: Job failed', [
            'deal_id' => $this->dealId,
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        // Помечаем сделку как failed
        try {
            Deal::where('id', $this->dealId)->update([
                'analysis_failed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Игнорируем
        }
    }
}
