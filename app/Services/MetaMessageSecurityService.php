<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\SecurityViolationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MetaMessageSecurityService
{
    /**
     * Разрешённые теги для отправки вне 24-часового окна.
     */
    public const ALLOWED_TAGS = [
        'ACCOUNT_UPDATE' => 'Обновление аккаунта',
        'CONFIRMED_EVENT_UPDATE' => 'Подтверждение события',
        'POST_PURCHASE_UPDATE' => 'После покупки',
        'HUMAN_AGENT' => 'Живой оператор (7 дней)',
    ];

    /**
     * Стоп-слова для маркетинговых сообщений.
     */
    public const MARKETING_STOP_WORDS = [
        'скидка', 'акция', 'купить', 'купон', 'распродажа', 'sale',
        'цена снижена', 'специальное предложение', 'только сегодня',
        'бесплатно', 'подарок', 'бонус', 'промокод', 'promo',
        'ограниченное предложение', 'успей купить', 'горячая цена',
        'выгодно', 'дёшево', 'низкая цена', 'экономия', 'розыгрыш',
        '%', '₽', '$', '€', 'руб', 'рублей',
    ];

    protected AiAnalysisService $aiService;

    public function __construct(AiAnalysisService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Проверить, можно ли отправить сообщение.
     *
     * @return array{allowed: bool, reason: string|null, requires_tag: bool, suggested_tag: string|null}
     */
    public function canSendMessage(Deal $deal, string $messageText, ?string $tag = null): array
    {
        $result = [
            'allowed' => true,
            'reason' => null,
            'requires_tag' => false,
            'suggested_tag' => null,
            'risk_level' => 'low',
        ];

        // 1. Проверяем 24-часовое окно
        $windowCheck = $this->check24HourWindow($deal);
        if (!$windowCheck['in_window']) {
            $result['requires_tag'] = true;

            if (!$tag) {
                $result['allowed'] = false;
                $result['reason'] = "⚠️ 24-часовое окно истекло {$windowCheck['hours_ago']} ч. назад. ".
                    'Выберите Message Tag для отправки.';
                $result['risk_level'] = 'medium';

                return $result;
            }

            // Если тег указан, проверяем его валидность
            if (!isset(self::ALLOWED_TAGS[$tag])) {
                $result['allowed'] = false;
                $result['reason'] = "❌ Недопустимый Message Tag: {$tag}";
                $result['risk_level'] = 'high';

                return $result;
            }
        }

        // 2. Если используется тег — проверяем на маркетинг
        if ($tag && $tag !== 'HUMAN_AGENT') {
            $marketingCheck = $this->checkMarketingContent($messageText, $tag);
            if ($marketingCheck['is_marketing']) {
                $result['allowed'] = false;
                $result['reason'] = $marketingCheck['reason'];
                $result['risk_level'] = 'critical';

                // Логируем попытку нарушения
                $this->logSecurityViolation($deal, $messageText, $tag, $marketingCheck);

                return $result;
            }
        }

        // 3. AI-проверка на рекламный контент (если доступна)
        if ($tag && $this->aiService->isAvailable()) {
            $aiCheck = $this->aiCheckMarketing($messageText);
            if ($aiCheck['is_advertising']) {
                $result['allowed'] = false;
                $result['reason'] = "🤖 AI определил сообщение как рекламное: {$aiCheck['reason']}. ".
                    'Риск блокировки аккаунта Meta 100%.';
                $result['risk_level'] = 'critical';

                $this->logSecurityViolation($deal, $messageText, $tag, [
                    'type' => 'ai_detected',
                    'ai_reason' => $aiCheck['reason'],
                ]);

                return $result;
            }
        }

        return $result;
    }

    /**
     * Проверить 24-часовое окно.
     */
    public function check24HourWindow(Deal $deal): array
    {
        $lastClientMessage = $deal->last_client_message_at;

        if (!$lastClientMessage) {
            return [
                'in_window' => false,
                'hours_ago' => null,
                'expires_at' => null,
            ];
        }

        $hoursAgo = $lastClientMessage->diffInHours(now());
        $inWindow = $hoursAgo < 24;

        return [
            'in_window' => $inWindow,
            'hours_ago' => $hoursAgo,
            'expires_at' => $lastClientMessage->addHours(24),
            'remaining_hours' => $inWindow ? 24 - $hoursAgo : 0,
        ];
    }

    /**
     * Проверить текст на маркетинговый контент.
     */
    public function checkMarketingContent(string $text, string $tag): array
    {
        $textLower = mb_strtolower($text);
        $foundWords = [];

        foreach (self::MARKETING_STOP_WORDS as $word) {
            if (mb_strpos($textLower, mb_strtolower($word)) !== false) {
                $foundWords[] = $word;
            }
        }

        if (!empty($foundWords)) {
            return [
                'is_marketing' => true,
                'found_words' => $foundWords,
                'reason' => '🚫 Ошибка безопасности: Обнаружены маркетинговые слова ('.
                    implode(', ', array_slice($foundWords, 0, 3)).'). '.
                    "Тег '{$tag}' запрещено использовать для рекламных рассылок. ".
                    'Риск блокировки аккаунта Meta 100%.',
            ];
        }

        return ['is_marketing' => false, 'found_words' => []];
    }

    /**
     * AI-проверка на рекламный контент через Gemini.
     */
    public function aiCheckMarketing(string $text): array
    {
        if (!$this->aiService->isAvailable()) {
            return ['is_advertising' => false, 'reason' => null];
        }

        try {
            $prompt = <<<PROMPT
Проанализируй следующее сообщение и определи, является ли оно рекламным или маркетинговым.

Сообщение: "{$text}"

Критерии рекламного сообщения:
- Содержит призыв к покупке
- Упоминает скидки, акции, специальные предложения
- Содержит промокоды или купоны
- Явно продвигает товар или услугу
- Содержит ценовую информацию в рекламном контексте

ВАЖНО: Ответь СТРОГО в формате JSON:
{
    "is_advertising": true или false,
    "confidence": число от 0 до 100,
    "reason": "краткое объяснение на русском"
}
PROMPT;

            $response = $this->aiService->sendRawPrompt($prompt);
            $parsed = json_decode($response, true);

            if ($parsed && isset($parsed['is_advertising'])) {
                return [
                    'is_advertising' => $parsed['is_advertising'] && ($parsed['confidence'] ?? 0) > 70,
                    'reason' => $parsed['reason'] ?? 'Рекламный контент',
                    'confidence' => $parsed['confidence'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('MetaMessageSecurityService: AI проверка недоступна', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['is_advertising' => false, 'reason' => null];
    }

    /**
     * Логировать попытку нарушения безопасности.
     */
    protected function logSecurityViolation(Deal $deal, string $text, ?string $tag, array $details): void
    {
        Log::warning('MetaMessageSecurityService: Попытка нарушения политики Meta', [
            'deal_id' => $deal->id,
            'tag' => $tag,
            'text_preview' => mb_substr($text, 0, 100),
            'details' => $details,
        ]);

        // Записываем в activity_logs
        ActivityLog::create([
            'deal_id' => $deal->id,
            'user_id' => auth()->id(),
            'action' => 'security_violation',
            'description' => 'Попытка отправки маркетингового сообщения через Message Tag',
            'metadata' => [
                'tag' => $tag,
                'found_words' => $details['found_words'] ?? [],
                'ai_reason' => $details['ai_reason'] ?? null,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Уведомляем админов
        $this->notifyAdmins($deal, $details);
    }

    /**
     * Уведомить админов о нарушении.
     */
    protected function notifyAdmins(Deal $deal, array $details): void
    {
        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        $message = "⚠️ Попытка нарушения политики Meta!\n\n".
            "Сделка: #{$deal->id}\n".
            'Менеджер: '.(auth()->user()?->name ?? 'Неизвестно')."\n".
            'Причина: '.($details['reason'] ?? 'Маркетинговый контент');

        Notification::send($admins, new SecurityViolationNotification($message, $deal));
    }

    /**
     * Получить рекомендуемый тег для сообщения.
     */
    public function suggestTag(string $messageContext): ?string
    {
        $contextLower = mb_strtolower($messageContext);

        if (str_contains($contextLower, 'заказ') || str_contains($contextLower, 'доставк')) {
            return 'POST_PURCHASE_UPDATE';
        }

        if (str_contains($contextLower, 'аккаунт') || str_contains($contextLower, 'профиль')) {
            return 'ACCOUNT_UPDATE';
        }

        if (str_contains($contextLower, 'событи') || str_contains($contextLower, 'встреч')) {
            return 'CONFIRMED_EVENT_UPDATE';
        }

        // По умолчанию — живой оператор (7 дней)
        return 'HUMAN_AGENT';
    }

    /**
     * Получить статус 24-часового окна для UI.
     */
    public function getWindowStatus(Deal $deal): array
    {
        $window = $this->check24HourWindow($deal);

        if (!$deal->last_client_message_at) {
            return [
                'status' => 'unknown',
                'label' => 'Нет данных',
                'color' => 'gray',
                'icon' => 'question-mark-circle',
                'can_send_freely' => false,
            ];
        }

        if ($window['in_window']) {
            $remaining = $window['remaining_hours'];

            return [
                'status' => 'open',
                'label' => "Окно открыто ({$remaining}ч)",
                'color' => 'success',
                'icon' => 'check-circle',
                'can_send_freely' => true,
                'expires_at' => $window['expires_at']->format('d.m.Y H:i'),
            ];
        }

        return [
            'status' => 'closed',
            'label' => "Окно закрыто ({$window['hours_ago']}ч назад)",
            'color' => 'danger',
            'icon' => 'x-circle',
            'can_send_freely' => false,
            'requires_tag' => true,
        ];
    }
}
