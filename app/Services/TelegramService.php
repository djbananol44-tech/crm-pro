<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $botToken;

    protected string $apiUrl = 'https://api.telegram.org/bot';

    protected int $timeout = 10;

    public function __construct()
    {
        $this->botToken = Setting::get('telegram_bot_token');
    }

    public function isAvailable(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Отправить сообщение в Telegram.
     */
    public function sendMessage(string $chatId, string $message, array $options = []): ?array
    {
        if (!$this->isAvailable()) {
            Log::warning('TelegramService: Бот не настроен');

            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->apiUrl}{$this->botToken}/sendMessage", array_merge([
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ], $options));

            if ($response->successful()) {
                Log::info('TelegramService: Сообщение отправлено', ['chat_id' => $chatId]);

                return $response->json('result');
            }

            Log::error('TelegramService: Ошибка отправки', [
                'chat_id' => $chatId,
                'error' => $response->json('description') ?? 'Unknown error',
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('TelegramService: Exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Редактировать сообщение.
     */
    public function editMessage(string $chatId, int $messageId, string $text, ?array $keyboard = null): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            $params = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];

            if ($keyboard) {
                $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
            }

            $response = Http::timeout($this->timeout)
                ->post("{$this->apiUrl}{$this->botToken}/editMessageText", $params);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('TelegramService: Edit error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Ответить на callback query (убрать "часики").
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            $params = ['callback_query_id' => $callbackQueryId];
            if ($text) {
                $params['text'] = $text;
                $params['show_alert'] = $showAlert;
            }

            $response = Http::timeout($this->timeout)
                ->post("{$this->apiUrl}{$this->botToken}/answerCallbackQuery", $params);

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Построить Inline Keyboard для сделки.
     */
    public function buildDealKeyboard(Deal $deal): array
    {
        $url = url("/deals/{$deal->id}");

        return [
            // Первый ряд: действия
            [
                ['text' => '🚀 В работу', 'callback_data' => "claim_{$deal->id}"],
                ['text' => '🤖 AI Анализ', 'callback_data' => "ai_sync_{$deal->id}"],
            ],
            // Второй ряд: завершение и ссылка
            [
                ['text' => '✅ Завершить', 'callback_data' => "close_{$deal->id}"],
                ['text' => '🔗 Открыть в CRM', 'url' => $url],
            ],
        ];
    }

    /**
     * Уведомить менеджера о новом сообщении с Inline Keyboard.
     */
    public function notifyNewMessage(User $manager, Deal $deal, string $clientName, ?string $preview = null): bool
    {
        if (empty($manager->telegram_chat_id)) {
            return false;
        }

        $previewText = $preview ? "\n\n💬 <i>".mb_substr($preview, 0, 100).'...</i>' : '';
        $score = $deal->ai_score ? " | Score: {$deal->ai_score}" : '';

        $message = <<<MSG
🔔 <b>Новое сообщение!</b>

👤 Клиент: <b>{$clientName}</b>
📋 Сделка: #{$deal->id}{$score}{$previewText}
MSG;

        $keyboard = $this->buildDealKeyboard($deal);

        return $this->sendMessage($manager->telegram_chat_id, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]) !== null;
    }

    /**
     * Уведомить менеджера о новой сделке с Inline Keyboard.
     */
    public function notifyNewDeal(User $manager, Deal $deal, string $clientName): bool
    {
        if (empty($manager->telegram_chat_id)) {
            return false;
        }

        $message = <<<MSG
🆕 <b>Новая сделка!</b>

👤 Клиент: <b>{$clientName}</b>
📋 Сделка: #{$deal->id}
📊 Статус: Новая заявка

<i>Выберите действие:</i>
MSG;

        $keyboard = $this->buildDealKeyboard($deal);

        return $this->sendMessage($manager->telegram_chat_id, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]) !== null;
    }

    /**
     * Уведомить о просрочке SLA.
     */
    public function notifySlaWarning(User $manager, Deal $deal, int $minutesOverdue): bool
    {
        if (empty($manager->telegram_chat_id)) {
            return false;
        }

        $message = <<<MSG
⚠️ <b>Просрочка SLA!</b>

👤 Клиент: <b>{$deal->contact?->name}</b>
📋 Сделка: #{$deal->id}
⏱ Ожидание: {$minutesOverdue} мин.

<i>Срочно ответьте клиенту!</i>
MSG;

        $keyboard = $this->buildDealKeyboard($deal);

        return $this->sendMessage($manager->telegram_chat_id, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]) !== null;
    }

    /**
     * Отправить список активных сделок менеджера.
     */
    public function sendMyDeals(User $user): bool
    {
        if (empty($user->telegram_chat_id)) {
            return false;
        }

        $deals = Deal::with('contact')
            ->where('manager_id', $user->id)
            ->whereIn('status', ['New', 'In Progress'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        if ($deals->isEmpty()) {
            return $this->sendMessage($user->telegram_chat_id, '📭 У вас нет активных сделок.') !== null;
        }

        $message = "📋 <b>Ваши активные сделки:</b>\n\n";

        foreach ($deals as $deal) {
            $name = $deal->contact?->name ?? 'Без имени';
            $status = $deal->status === 'New' ? '🆕' : '🔄';
            $hot = $deal->ai_score > 80 ? '⚡' : '';
            $message .= "{$status}{$hot} #{$deal->id} — {$name}\n";
        }

        // Inline кнопки для каждой сделки
        $keyboard = [];
        foreach ($deals->take(5) as $deal) {
            $name = mb_substr($deal->contact?->name ?? 'Сделка', 0, 15);
            $keyboard[] = [
                ['text' => "#{$deal->id} {$name}", 'url' => url("/deals/{$deal->id}")],
            ];
        }

        return $this->sendMessage($user->telegram_chat_id, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]) !== null;
    }

    /**
     * Отправить результат AI-анализа.
     */
    public function sendAiAnalysis(string $chatId, Deal $deal, ?string $summary, ?int $score): bool
    {
        if (!$summary) {
            return $this->sendMessage($chatId, "❌ Не удалось получить AI-анализ для сделки #{$deal->id}") !== null;
        }

        $scoreText = $score ? "\n\n📊 <b>Lead Score:</b> {$score}/100" : '';
        $hot = $score && $score > 80 ? ' ⚡ HOT LEAD!' : '';

        $message = <<<MSG
🤖 <b>AI-Анализ сделки #{$deal->id}</b>{$hot}{$scoreText}

{$summary}
MSG;

        return $this->sendMessage($chatId, $message) !== null;
    }

    /**
     * Уведомить всех админов.
     */
    public function notifyAdmins(string $message): void
    {
        $admins = User::where('role', 'admin')
            ->whereNotNull('telegram_chat_id')
            ->get();

        foreach ($admins as $admin) {
            $this->sendMessage($admin->telegram_chat_id, $message);
        }
    }

    /**
     * Найти пользователя по chat_id.
     */
    public function findUserByChatId(string $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->first();
    }

    /**
     * Проверить статус API соединения.
     */
    public function testConnection(): array
    {
        if (empty($this->botToken)) {
            return [
                'success' => false,
                'message' => 'Токен бота не настроен',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->apiUrl}{$this->botToken}/getMe");

            if ($response->successful()) {
                $bot = $response->json('result');

                return [
                    'success' => true,
                    'message' => "Бот подключен: @{$bot['username']}",
                    'bot_username' => $bot['username'],
                    'bot_id' => $bot['id'],
                ];
            }

            return [
                'success' => false,
                'message' => 'Ошибка: '.($response->json('description') ?? 'Unknown'),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка подключения: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Установить Webhook с secret_token.
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'Бот не настроен'];
        }

        try {
            $params = [
                'url' => $url,
                'allowed_updates' => ['message', 'callback_query'],
            ];

            if ($secretToken) {
                $params['secret_token'] = $secretToken;
            }

            $response = Http::timeout(10)
                ->post("{$this->apiUrl}{$this->botToken}/setWebhook", $params);

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success' => true,
                    'message' => "Webhook установлен: {$url}",
                    'url' => $url,
                ];
            }

            return [
                'success' => false,
                'message' => 'Ошибка: '.($response->json('description') ?? 'Unknown'),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Получить информацию о текущем Webhook.
     */
    public function getWebhookInfo(): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'Бот не настроен'];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->apiUrl}{$this->botToken}/getWebhookInfo");

            if ($response->successful()) {
                $info = $response->json('result');

                return [
                    'success' => true,
                    'url' => $info['url'] ?? '',
                    'has_custom_certificate' => $info['has_custom_certificate'] ?? false,
                    'pending_update_count' => $info['pending_update_count'] ?? 0,
                    'last_error_date' => $info['last_error_date'] ?? null,
                    'last_error_message' => $info['last_error_message'] ?? null,
                ];
            }

            return ['success' => false, 'message' => 'Ошибка получения информации'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: '.$e->getMessage()];
        }
    }

    /**
     * Удалить Webhook.
     */
    public function deleteWebhook(): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'Бот не настроен'];
        }

        try {
            $response = Http::timeout(10)
                ->post("{$this->apiUrl}{$this->botToken}/deleteWebhook");

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Webhook удалён' : 'Ошибка удаления',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: '.$e->getMessage()];
        }
    }

    /**
     * Валидировать токен и автоматически настроить Telegram.
     * Вызывается при сохранении токена в Settings.
     */
    public static function validateAndSetup(string $token): array
    {
        $apiUrl = 'https://api.telegram.org/bot';

        // 1. Валидация токена через getMe
        try {
            $response = Http::timeout(10)->get("{$apiUrl}{$token}/getMe");

            if (!$response->successful() || !$response->json('ok')) {
                $error = $response->json('description') ?? 'Неверный токен';
                self::updateStatus('error', $error);

                return [
                    'success' => false,
                    'message' => "Ошибка валидации: {$error}",
                ];
            }

            $bot = $response->json('result');
            $botUsername = $bot['username'] ?? 'unknown';

        } catch (Exception $e) {
            self::updateStatus('error', 'Сетевая ошибка: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Ошибка подключения: '.$e->getMessage(),
            ];
        }

        // 2. Определяем режим (webhook/polling)
        $mode = Setting::get('telegram_mode', 'polling');
        $webhookUrl = '';

        if ($mode === 'webhook') {
            // Генерируем secret_token
            $secretToken = bin2hex(random_bytes(32));
            Setting::set('telegram_webhook_secret', $secretToken);

            // Формируем webhook URL
            $appUrl = rtrim(config('app.url'), '/');
            $webhookUrl = "{$appUrl}/api/webhooks/telegram";

            // 3. Устанавливаем webhook
            try {
                $webhookResponse = Http::timeout(10)
                    ->post("{$apiUrl}{$token}/setWebhook", [
                        'url' => $webhookUrl,
                        'secret_token' => $secretToken,
                        'allowed_updates' => ['message', 'callback_query'],
                    ]);

                if (!$webhookResponse->successful() || !$webhookResponse->json('ok')) {
                    $error = $webhookResponse->json('description') ?? 'Не удалось установить webhook';
                    self::updateStatus('error', $error);

                    return [
                        'success' => false,
                        'message' => "Токен валидный, но webhook не установлен: {$error}",
                        'bot_username' => $botUsername,
                    ];
                }

                Setting::set('telegram_webhook_url', $webhookUrl);

            } catch (Exception $e) {
                self::updateStatus('error', 'Ошибка установки webhook: '.$e->getMessage());

                return [
                    'success' => false,
                    'message' => 'Ошибка установки webhook: '.$e->getMessage(),
                    'bot_username' => $botUsername,
                ];
            }
        } else {
            // Polling mode — удаляем webhook если был
            try {
                Http::timeout(10)->post("{$apiUrl}{$token}/deleteWebhook");
            } catch (Exception $e) {
                // Игнорируем
            }
            Setting::set('telegram_webhook_url', '');
            Setting::set('telegram_webhook_secret', '');
        }

        // 4. Сохраняем успешный статус
        self::updateStatus('ok', null, $botUsername, $webhookUrl);

        return [
            'success' => true,
            'message' => $mode === 'webhook'
                ? "✅ Бот @{$botUsername} активирован (Webhook: {$webhookUrl})"
                : "✅ Бот @{$botUsername} активирован (Polling mode)",
            'bot_username' => $botUsername,
            'mode' => $mode,
            'webhook_url' => $webhookUrl,
        ];
    }

    /**
     * Обновить статус интеграции Telegram.
     */
    protected static function updateStatus(string $status, ?string $error = null, ?string $botUsername = null, ?string $webhookUrl = null): void
    {
        Setting::set('telegram_status', $status);
        Setting::set('telegram_last_check_at', now()->toISOString());
        Setting::set('telegram_last_error', $error);

        if ($botUsername) {
            Setting::set('telegram_bot_username', $botUsername);
        }
        if ($webhookUrl !== null) {
            Setting::set('telegram_webhook_url', $webhookUrl);
        }
    }

    /**
     * Получить текущий статус интеграции.
     */
    public static function getStatus(): array
    {
        return [
            'status' => Setting::get('telegram_status', 'disabled'),
            'last_check_at' => Setting::get('telegram_last_check_at'),
            'last_error' => Setting::get('telegram_last_error'),
            'bot_username' => Setting::get('telegram_bot_username'),
            'webhook_url' => Setting::get('telegram_webhook_url'),
            'mode' => Setting::get('telegram_mode', 'polling'),
        ];
    }

    /**
     * Проверить текущее соединение и обновить статус.
     */
    public function checkAndUpdateStatus(): array
    {
        $result = $this->testConnection();

        if ($result['success']) {
            $webhookInfo = $this->getWebhookInfo();
            $webhookUrl = $webhookInfo['url'] ?? '';

            self::updateStatus(
                'ok',
                null,
                $result['bot_username'] ?? null,
                $webhookUrl
            );

            $result['webhook_url'] = $webhookUrl;
            $result['mode'] = Setting::get('telegram_mode', 'polling');
        } else {
            self::updateStatus('error', $result['message']);
        }

        return $result;
    }

    /**
     * Проверить SLA и отправить пинги.
     */
    public function sendSlaPings(): int
    {
        $overdueDeals = Deal::with(['contact', 'manager'])
            ->whereNotNull('manager_id')
            ->whereNotNull('last_client_message_at')
            ->whereIn('status', ['New', 'In Progress'])
            ->where(function ($q) {
                $q->whereNull('last_manager_response_at')
                    ->orWhereColumn('last_client_message_at', '>', 'last_manager_response_at');
            })
            ->where('last_client_message_at', '<', now()->subMinutes(30))
            ->get();

        $sentCount = 0;

        foreach ($overdueDeals as $deal) {
            if (!$deal->manager || !$deal->manager->telegram_chat_id) {
                continue;
            }

            $minutesOverdue = $deal->last_client_message_at->diffInMinutes(now());

            // Пингуем менеджера
            $this->notifySlaWarning($deal->manager, $deal, $minutesOverdue);
            $sentCount++;

            // Если прошло больше часа — пингуем админов
            if ($minutesOverdue > 60) {
                $this->notifyAdmins(
                    "⚠️ Критическая просрочка!\n\n".
                    "Сделка #{$deal->id}\n".
                    "Менеджер: {$deal->manager->name}\n".
                    "Ожидание: {$minutesOverdue} мин."
                );
            }
        }

        return $sentCount;
    }

    /**
     * Генерация кода авторизации для привязки Telegram.
     */
    public function generateAuthCode(User $user): string
    {
        $code = strtoupper(substr(md5($user->id.time().rand()), 0, 6));

        // Сохраняем код в кэше на 10 минут
        cache()->put("telegram_auth_{$code}", $user->id, 600);

        return $code;
    }

    /**
     * Подтвердить код авторизации.
     */
    public function confirmAuthCode(string $code, string $chatId): ?User
    {
        $userId = cache()->pull("telegram_auth_{$code}");

        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        if ($user) {
            $user->update(['telegram_chat_id' => $chatId]);

            return $user;
        }

        return null;
    }
}
