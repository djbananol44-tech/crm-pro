<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Deal;
use App\Models\ActivityLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AiAnalysisService
{
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    protected ?string $apiKey;
    protected bool $enabled;
    protected int $timeout = 30;

    public function __construct()
    {
        $this->apiKey = Setting::get('gemini_api_key');
        $this->enabled = Setting::get('ai_enabled', false);
    }

    public function isAvailable(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Получить анализ переписки с Lead Score.
     * 
     * @return array{summary: string|null, score: int|null}
     */
    public function analyzeConversation(Collection|array $messages): array
    {
        $result = ['summary' => null, 'score' => null];

        if (!$this->isAvailable() || empty($messages)) {
            return $result;
        }

        $messagesText = $this->formatMessagesForAi($messages);
        if (empty(trim($messagesText))) {
            return $result;
        }

        Log::info('AiAnalysisService: Запрос анализа с Lead Scoring', [
            'messages_count' => is_array($messages) ? count($messages) : $messages->count(),
        ]);

        try {
            $prompt = $this->buildScoringPrompt($messagesText);
            $response = $this->sendRequest($prompt);
            $parsed = $this->parseJsonResponse($response);
            
            if ($parsed) {
                $result['summary'] = $parsed['summary'] ?? null;
                $result['score'] = isset($parsed['score']) ? (int) $parsed['score'] : null;
                
                if ($result['score'] !== null) {
                    $result['score'] = max(1, min(100, $result['score']));
                }
            }

            Log::info('AiAnalysisService: Анализ завершён', [
                'score' => $result['score'],
                'has_summary' => !empty($result['summary']),
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('AiAnalysisService: Ошибка анализа', ['error' => $e->getMessage()]);
            return $result;
        }
    }

    /**
     * Оценить качество работы менеджера по переписке.
     * 
     * @return array{rating: int|null, review: string|null}
     */
    public function evaluateManagerPerformance(Collection|array $messages, string $managerName): array
    {
        $result = ['rating' => null, 'review' => null];

        if (!$this->isAvailable() || empty($messages)) {
            return $result;
        }

        $messagesText = $this->formatMessagesForAi($messages);
        if (empty(trim($messagesText))) {
            return $result;
        }

        Log::info('AiAnalysisService: Оценка работы менеджера', ['manager' => $managerName]);

        $prompt = <<<PROMPT
Ты — эксперт по контролю качества обслуживания клиентов.

Проанализируй переписку менеджера "{$managerName}" с клиентом и оцени качество его работы.

КРИТЕРИИ ОЦЕНКИ (1-5):
1 - Очень плохо: грубость, игнорирование вопросов, некомпетентность
2 - Плохо: много пропущенных вопросов, неполные ответы
3 - Удовлетворительно: базовые ответы, но без инициативы
4 - Хорошо: вежливо, информативно, ответил на все вопросы
5 - Отлично: проактивный, предложил дополнительную помощь, эмпатичен

ПЕРЕПИСКА:
{$messagesText}

ВАЖНО: Ответь СТРОГО в формате JSON:
{
    "rating": число от 1 до 5,
    "review": "Краткий отзыв на русском (2-3 предложения): был ли менеджер вежлив, ответил ли на все вопросы, что можно улучшить",
    "strengths": ["сильные стороны"],
    "weaknesses": ["слабые стороны"]
}
PROMPT;

        try {
            $response = $this->sendRequest($prompt);
            $parsed = $this->parseJsonResponse($response);

            if ($parsed) {
                $result['rating'] = isset($parsed['rating']) ? (int) $parsed['rating'] : null;
                $result['review'] = $parsed['review'] ?? null;

                if ($result['rating'] !== null) {
                    $result['rating'] = max(1, min(5, $result['rating']));
                }
            }

            Log::info('AiAnalysisService: Оценка завершена', [
                'manager' => $managerName,
                'rating' => $result['rating'],
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

        // Логируем
        ActivityLog::logRated($deal, $evaluation['rating'], $evaluation['review'] ?? '');

        Log::info('AiAnalysisService: Оценка сохранена', [
            'deal_id' => $deal->id,
            'rating' => $evaluation['rating'],
        ]);

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
            return $this->sendRequest($prompt);
        } catch (Exception $e) {
            Log::error('AiAnalysisService: Ошибка перевода', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function buildScoringPrompt(string $messagesText): string
    {
        return <<<PROMPT
Ты — эксперт CRM-аналитики. Проанализируй переписку с клиентом.

ЗАДАЧА:
1. Составь краткое резюме (2-4 предложения) на русском
2. Оцени вероятность покупки (Lead Score) от 1 до 100

КРИТЕРИИ ОЦЕНКИ Lead Score:
- 1-20: Просто интересуется, нет намерения покупать
- 21-40: Задаёт вопросы, но не готов к покупке
- 41-60: Проявляет интерес к конкретному товару/услуге
- 61-80: Обсуждает цены, условия, готов к покупке
- 81-100: Горячий лид, явно хочет купить, спрашивает как оплатить

Переписка:
{$messagesText}

ВАЖНО: Ответь СТРОГО в формате JSON:
{
    "summary": "Краткое резюме переписки на русском",
    "score": число от 1 до 100,
    "intent": "краткое описание намерения клиента"
}
PROMPT;
    }

    protected function formatMessagesForAi(Collection|array $messages): string
    {
        $formatted = [];
        foreach ($messages as $message) {
            $from = $message['from']['name'] ?? $message['from']['id'] ?? 'Пользователь';
            $text = $message['message'] ?? $message['text'] ?? '';
            if (!empty($text)) {
                $formatted[] = "[{$from}]: {$text}";
            }
        }
        return implode("\n", $formatted);
    }

    protected function parseJsonResponse(string $response): ?array
    {
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $json = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($json, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }
        
        return ['summary' => trim($response), 'score' => null];
    }

    protected function sendRequest(string $prompt): string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->apiUrl}?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 800,
                    'topP' => 0.9,
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

    public function analyzeSentiment(string $text): ?array
    {
        if (!$this->isAvailable()) return null;

        $prompt = <<<PROMPT
Проанализируй настроение текста и ответь JSON:
{"sentiment": "positive|neutral|negative", "confidence": 0.0-1.0}

Текст: {$text}
PROMPT;

        try {
            $response = $this->sendRequest($prompt);
            return $this->parseJsonResponse($response);
        } catch (Exception $e) {
            return null;
        }
    }

    public function generateReplyDraft(Collection|array $messages): ?string
    {
        if (!$this->isAvailable()) return null;

        $messagesText = $this->formatMessagesForAi($messages);
        $prompt = <<<PROMPT
Предложи вежливый ответ менеджера на русском языке.

Переписка:
{$messagesText}

Ответ менеджера:
PROMPT;

        try {
            return $this->sendRequest($prompt);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Отправить произвольный промпт и получить ответ.
     */
    public function sendRawPrompt(string $prompt): string
    {
        if (!$this->isAvailable()) {
            throw new Exception('AI сервис недоступен');
        }

        return $this->sendRequest($prompt);
    }

    /**
     * Проверить доступность API.
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API ключ не настроен',
            ];
        }

        try {
            $response = $this->sendRequest('Ответь одним словом: Работает');
            return [
                'success' => true,
                'message' => 'Gemini API работает корректно',
                'response' => $response,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ];
        }
    }
}
