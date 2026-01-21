<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\SystemLog;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Analysis Service с поддержкой Gemini API.
 *
 * Особенности:
 * - Строгий JSON парсинг с retry
 * - Логирование latency/success/fail
 * - Graceful degradation при ошибках
 */
class AiAnalysisService
{
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

    protected ?string $apiKey;

    protected bool $enabled;

    protected int $timeout = 30;

    protected int $maxRetries = 2;

    /**
     * Версия промпта для отслеживания изменений.
     */
    protected const PROMPT_VERSION = 'v2.0';

    /**
     * JSON Schema для анализа переписки.
     */
    protected const ANALYSIS_SCHEMA = [
        'summary' => 'string (2-4 предложения на русском)',
        'score' => 'integer (1-100, вероятность покупки)',
        'intent' => 'string (намерение клиента)',
        'objections' => 'array of strings (возражения клиента)',
        'next_best_action' => 'string (рекомендация менеджеру)',
    ];

    public function __construct()
    {
        $this->apiKey = Setting::get('gemini_api_key');
        $aiEnabled = Setting::get('ai_enabled', false);
        $this->enabled = $aiEnabled === true || $aiEnabled === 'true' || $aiEnabled === '1';
    }

    /**
     * Проверить доступность сервиса.
     * Учитывает статус последней проверки.
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled || empty($this->apiKey)) {
            return false;
        }

        // Проверяем статус последней проверки
        $status = Setting::get('gemini_status', 'disabled');

        // Если статус error и проверка была недавно (< 5 мин), не пытаемся
        if ($status === 'error') {
            $lastCheck = Setting::get('gemini_last_check_at');
            if ($lastCheck) {
                $lastCheckTime = \Carbon\Carbon::parse($lastCheck);
                if ($lastCheckTime->diffInMinutes(now()) < 5) {
                    return false; // Не спамим API при ошибках
                }
            }
        }

        return true;
    }

    /**
     * Принудительно проверить доступность (игнорируя кэш статуса).
     */
    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Получить полный анализ переписки.
     *
     * @return array{summary: string|null, score: int|null, intent: string|null, objections: array, next_best_action: string|null}
     */
    public function analyzeConversation(Collection|array $messages): array
    {
        $result = [
            'summary' => null,
            'score' => null,
            'intent' => null,
            'objections' => [],
            'next_best_action' => null,
        ];

        if (!$this->isAvailable() || empty($messages)) {
            return $result;
        }

        $messagesText = $this->formatMessagesForAi($messages);
        if (empty(trim($messagesText))) {
            return $result;
        }

        $messagesCount = is_array($messages) ? count($messages) : $messages->count();
        $startTime = microtime(true);

        Log::info('AiAnalysisService: Запрос анализа', [
            'messages_count' => $messagesCount,
            'prompt_version' => self::PROMPT_VERSION,
        ]);

        try {
            $prompt = $this->buildAnalysisPrompt($messagesText);
            $response = $this->sendRequestWithRetry($prompt);
            $parsed = $this->parseStrictJson($response);

            if ($parsed) {
                $result = $this->normalizeAnalysisResult($parsed);

                $latency = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('AiAnalysisService: Анализ успешен', [
                    'score' => $result['score'],
                    'has_summary' => !empty($result['summary']),
                    'objections_count' => count($result['objections']),
                    'latency_ms' => $latency,
                    'prompt_version' => self::PROMPT_VERSION,
                ]);

                SystemLog::ai('success', 'AI анализ завершён', [
                    'score' => $result['score'],
                    'latency_ms' => $latency,
                ]);
            }

            return $result;

        } catch (Exception $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('AiAnalysisService: Ошибка анализа', [
                'error' => $e->getMessage(),
                'latency_ms' => $latency,
                'prompt_version' => self::PROMPT_VERSION,
            ]);

            SystemLog::ai('error', 'Ошибка AI анализа', [
                'error' => $e->getMessage(),
                'latency_ms' => $latency,
            ]);

            return $result;
        }
    }

    /**
     * Анализировать и сохранить в сделку.
     * При ошибке сохраняет analysis_failed_at.
     */
    public function analyzeAndSaveDeal(Deal $deal, Collection|array $messages): bool
    {
        $startTime = microtime(true);

        try {
            $analysis = $this->analyzeConversation($messages);

            if ($analysis['summary']) {
                $deal->update([
                    'ai_summary' => $analysis['summary'],
                    'ai_score' => $analysis['score'],
                    'ai_intent' => $analysis['intent'],
                    'ai_objections' => $analysis['objections'],
                    'ai_next_action' => $analysis['next_best_action'],
                    'ai_summary_at' => now(),
                    'analysis_failed_at' => null, // Сбрасываем ошибку
                ]);

                ActivityLog::logAiAnalysis($deal, $analysis['score']);

                Log::info('AiAnalysisService: Анализ сохранён в сделку', [
                    'deal_id' => $deal->id,
                    'score' => $analysis['score'],
                ]);

                return true;
            }

            // Анализ вернул пустой результат — не ошибка, но и не успех
            return false;

        } catch (Exception $e) {
            // Критическая ошибка — сохраняем время неудачи
            $deal->update([
                'analysis_failed_at' => now(),
            ]);

            Log::error('AiAnalysisService: Критическая ошибка анализа сделки', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);

            SystemLog::ai('error', 'Критическая ошибка анализа сделки', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);

            // НЕ пробрасываем исключение — job не должен падать
            return false;
        }
    }

    /**
     * Оценить качество работы менеджера.
     *
     * @return array{rating: int|null, review: string|null, strengths: array, weaknesses: array}
     */
    public function evaluateManagerPerformance(Collection|array $messages, string $managerName): array
    {
        $result = ['rating' => null, 'review' => null, 'strengths' => [], 'weaknesses' => []];

        if (!$this->isAvailable() || empty($messages)) {
            return $result;
        }

        $messagesText = $this->formatMessagesForAi($messages);
        if (empty(trim($messagesText))) {
            return $result;
        }

        $startTime = microtime(true);

        $prompt = <<<PROMPT
Ты — эксперт по контролю качества обслуживания клиентов.

Проанализируй переписку менеджера "{$managerName}" с клиентом.

КРИТЕРИИ ОЦЕНКИ (1-5):
1 - Очень плохо: грубость, игнорирование вопросов
2 - Плохо: много пропущенных вопросов, неполные ответы
3 - Удовлетворительно: базовые ответы, но без инициативы
4 - Хорошо: вежливо, информативно, ответил на все вопросы
5 - Отлично: проактивный, предложил дополнительную помощь

ПЕРЕПИСКА:
{$messagesText}

Return ONLY valid JSON. No markdown, no code blocks, no explanations.
{
    "rating": <число от 1 до 5>,
    "review": "<Краткий отзыв на русском: 2-3 предложения>",
    "strengths": ["<сильная сторона 1>", "<сильная сторона 2>"],
    "weaknesses": ["<слабая сторона 1>", "<слабая сторона 2>"]
}
PROMPT;

        try {
            $response = $this->sendRequestWithRetry($prompt);
            $parsed = $this->parseStrictJson($response);

            if ($parsed) {
                $result['rating'] = isset($parsed['rating']) ? max(1, min(5, (int) $parsed['rating'])) : null;
                $result['review'] = $parsed['review'] ?? null;
                $result['strengths'] = $parsed['strengths'] ?? [];
                $result['weaknesses'] = $parsed['weaknesses'] ?? [];
            }

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('AiAnalysisService: Оценка менеджера завершена', [
                'manager' => $managerName,
                'rating' => $result['rating'],
                'latency_ms' => $latency,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('AiAnalysisService: Ошибка оценки менеджера', ['error' => $e->getMessage()]);

            return $result;
        }
    }

    /**
     * Оценить менеджера и сохранить в сделку.
     */
    public function rateAndSaveDeal(Deal $deal, Collection|array $messages): bool
    {
        if (!$this->isAvailable() || $deal->manager_rating !== null) {
            return false;
        }

        $managerName = $deal->manager?->name ?? 'Менеджер';
        $evaluation = $this->evaluateManagerPerformance($messages, $managerName);

        if ($evaluation['rating'] === null) {
            return false;
        }

        $deal->update([
            'manager_rating' => $evaluation['rating'],
            'manager_review' => $evaluation['review'],
            'rated_at' => now(),
        ]);

        ActivityLog::logRated($deal, $evaluation['rating'], $evaluation['review'] ?? '');

        return true;
    }

    /**
     * Обратная совместимость.
     */
    public function getConversationSummary(Collection|array $messages): ?string
    {
        $analysis = $this->analyzeConversation($messages);

        return $analysis['summary'];
    }

    /**
     * Перевести текст на русский язык.
     */
    public function translateToRussian(Collection|array $messages): ?string
    {
        if (!$this->isAvailable() || empty($messages)) {
            return null;
        }

        $messagesText = $this->formatMessagesForAi($messages);

        $prompt = <<<PROMPT
Переведи следующую переписку на русский язык. Сохрани формат [Имя]: Сообщение.
Если текст уже на русском, верни его как есть.

Переписка:
{$messagesText}

Перевод:
PROMPT;

        try {
            return $this->sendRequestWithRetry($prompt);
        } catch (Exception $e) {
            Log::error('AiAnalysisService: Ошибка перевода', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Построить промпт для анализа с строгим JSON форматом.
     */
    protected function buildAnalysisPrompt(string $messagesText): string
    {
        $schemaJson = json_encode(self::ANALYSIS_SCHEMA, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a CRM analytics expert. Analyze the customer conversation below.

TASK:
1. Write a brief summary in Russian (2-4 sentences)
2. Calculate Lead Score (1-100) based on purchase likelihood
3. Identify customer intent
4. List any objections the customer raised
5. Suggest the best next action for the manager

LEAD SCORE CRITERIA:
- 1-20: Just browsing, no intent to buy
- 21-40: Asking questions, not ready to buy
- 41-60: Interested in specific product/service
- 61-80: Discussing prices/terms, ready to consider
- 81-100: Hot lead, actively wants to buy

CONVERSATION:
{$messagesText}

IMPORTANT: Return ONLY valid JSON. No markdown, no code blocks, no explanations before or after.

{
    "summary": "Краткое резюме на русском языке (2-4 предложения)",
    "score": 75,
    "intent": "Намерение клиента кратко",
    "objections": ["возражение 1", "возражение 2"],
    "next_best_action": "Рекомендуемое действие для менеджера"
}
PROMPT;
    }

    /**
     * Отправить запрос с retry логикой.
     */
    protected function sendRequestWithRetry(string $prompt): string
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->sendRequest($prompt);

                return $response;
            } catch (Exception $e) {
                $lastException = $e;

                Log::warning('AiAnalysisService: Retry attempt', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    // Exponential backoff: 1s, 2s
                    sleep($attempt);
                }
            }
        }

        throw $lastException ?? new Exception('All retry attempts failed');
    }

    /**
     * Отправить запрос к Gemini API.
     */
    protected function sendRequest(string $prompt): string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.3, // Меньше = более детерминированный вывод
                    'maxOutputTokens' => 1000,
                    'topP' => 0.8,
                ],
                'safetySettings' => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? 'Неизвестная ошибка API';

            throw new Exception("Gemini API Error: {$error}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (empty($text)) {
            throw new Exception('Gemini API вернул пустой ответ');
        }

        return trim($text);
    }

    /**
     * Строгий парсер JSON с очисткой от мусора.
     */
    protected function parseStrictJson(string $response): ?array
    {
        // Убираем markdown code blocks
        $cleaned = preg_replace('/```(?:json)?\s*/i', '', $response);
        $cleaned = preg_replace('/```\s*$/i', '', $cleaned);
        $cleaned = trim($cleaned);

        // Находим JSON объект
        $jsonStart = strpos($cleaned, '{');
        $jsonEnd = strrpos($cleaned, '}');

        if ($jsonStart === false || $jsonEnd === false || $jsonEnd <= $jsonStart) {
            Log::warning('AiAnalysisService: JSON не найден в ответе', [
                'response_preview' => substr($response, 0, 200),
            ]);

            return null;
        }

        $jsonString = substr($cleaned, $jsonStart, $jsonEnd - $jsonStart + 1);

        // Пробуем распарсить
        $parsed = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('AiAnalysisService: Ошибка парсинга JSON', [
                'error' => json_last_error_msg(),
                'json_preview' => substr($jsonString, 0, 200),
            ]);

            return null;
        }

        return $parsed;
    }

    /**
     * Нормализовать результат анализа.
     */
    protected function normalizeAnalysisResult(array $parsed): array
    {
        return [
            'summary' => $parsed['summary'] ?? null,
            'score' => isset($parsed['score']) ? max(1, min(100, (int) $parsed['score'])) : null,
            'intent' => $parsed['intent'] ?? null,
            'objections' => is_array($parsed['objections'] ?? null) ? $parsed['objections'] : [],
            'next_best_action' => $parsed['next_best_action'] ?? null,
        ];
    }

    /**
     * Форматировать сообщения для AI.
     */
    protected function formatMessagesForAi(Collection|array $messages): string
    {
        $formatted = [];
        foreach ($messages as $message) {
            $from = $message['from']['name'] ?? $message['from']['id'] ?? 'User';
            $text = $message['message'] ?? $message['text'] ?? '';
            if (!empty($text)) {
                // Убираем PII (телефоны, email) для безопасности
                $text = preg_replace('/\b\d{10,}\b/', '[PHONE]', $text);
                $text = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $text);
                $formatted[] = "[{$from}]: {$text}";
            }
        }

        return implode("\n", $formatted);
    }

    /**
     * Проверить доступность API.
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API ключ не настроен'];
        }

        $startTime = microtime(true);

        try {
            $response = $this->sendRequest('Return ONLY: {"status": "ok"}');
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'message' => 'Gemini API работает корректно',
                'latency_ms' => $latency,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Отправить произвольный промпт.
     */
    public function sendRawPrompt(string $prompt): string
    {
        if (!$this->isAvailable()) {
            throw new Exception('AI сервис недоступен');
        }

        return $this->sendRequestWithRetry($prompt);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // АВТО-АКТИВАЦИЯ И СТАТУС
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Валидировать API ключ и автоматически настроить Gemini.
     * Вызывается при сохранении ключа в Settings.
     */
    public static function validateAndSetup(string $apiKey): array
    {
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
        $startTime = microtime(true);

        try {
            // Минимальный test request
            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$apiUrl}?key={$apiKey}", [
                    'contents' => [['parts' => [['text' => 'Return only: OK']]]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => 10,
                    ],
                ]);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown API error';
                $errorCode = $response->json('error.code') ?? $response->status();

                self::updateStatus('error', "API Error ({$errorCode}): {$error}");

                return [
                    'success' => false,
                    'message' => "Ошибка API: {$error}",
                    'error_code' => $errorCode,
                ];
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            if (empty($text)) {
                self::updateStatus('error', 'API вернул пустой ответ');

                return [
                    'success' => false,
                    'message' => 'API вернул пустой ответ. Возможно, ключ недействителен.',
                ];
            }

            // Успех!
            self::updateStatus('ok', null, $latency);

            return [
                'success' => true,
                'message' => "✅ Gemini API работает (latency: {$latency}ms)",
                'latency_ms' => $latency,
            ];

        } catch (Exception $e) {
            $error = $e->getMessage();
            self::updateStatus('error', "Connection error: {$error}");

            return [
                'success' => false,
                'message' => "Ошибка подключения: {$error}",
            ];
        }
    }

    /**
     * Обновить статус интеграции Gemini.
     */
    protected static function updateStatus(string $status, ?string $error = null, ?float $latency = null): void
    {
        Setting::set('gemini_status', $status);
        Setting::set('gemini_last_check_at', now()->toISOString());
        Setting::set('gemini_last_error', $error);

        if ($latency !== null) {
            Setting::set('gemini_last_latency_ms', (string) $latency);
        }

        // Логируем
        if ($status === 'ok') {
            SystemLog::ai('info', 'Gemini API активирован', [
                'latency_ms' => $latency,
            ]);
        } else {
            SystemLog::ai('warning', 'Gemini API ошибка активации', [
                'error' => $error,
            ]);
        }
    }

    /**
     * Получить текущий статус интеграции.
     */
    public static function getStatus(): array
    {
        return [
            'status' => Setting::get('gemini_status', 'disabled'),
            'last_check_at' => Setting::get('gemini_last_check_at'),
            'last_error' => Setting::get('gemini_last_error'),
            'last_latency_ms' => Setting::get('gemini_last_latency_ms'),
            'enabled' => Setting::get('ai_enabled', false) === 'true' || Setting::get('ai_enabled', false) === true,
            'has_key' => Setting::hasValue('gemini_api_key'),
        ];
    }

    /**
     * Проверить текущее соединение и обновить статус.
     */
    public function checkAndUpdateStatus(): array
    {
        $result = $this->testConnection();

        if ($result['success']) {
            self::updateStatus('ok', null, $result['latency_ms'] ?? null);
        } else {
            self::updateStatus('error', $result['message']);
        }

        return array_merge($result, self::getStatus());
    }
}
