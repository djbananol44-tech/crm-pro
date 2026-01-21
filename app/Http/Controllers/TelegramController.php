<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\TelegramService;
use App\Services\AiAnalysisService;
use App\Services\MetaApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected TelegramService $telegram;
    protected AiAnalysisService $aiService;
    protected MetaApiService $metaApi;

    public function __construct(
        TelegramService $telegram,
        AiAnalysisService $aiService,
        MetaApiService $metaApi
    ) {
        $this->telegram = $telegram;
        $this->aiService = $aiService;
        $this->metaApi = $metaApi;
    }

    /**
     * Обработка Webhook от Telegram.
     */
    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();
        
        // Определяем тип события
        $eventType = match (true) {
            isset($update['callback_query']) => 'callback_query',
            isset($update['message']['text']) => 'message',
            default => 'unknown',
        };
        
        // Логируем входящий вебхук
        $webhookLog = WebhookLog::logIncoming(
            source: 'telegram',
            eventType: $eventType,
            payload: $update,
            ip: $request->ip()
        );

        Log::info('TelegramController: Webhook received', [
            'update_id' => $update['update_id'] ?? null,
            'log_id' => $webhookLog->id,
        ]);

        try {
            // Обработка callback_query (нажатие на inline кнопки)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
                $webhookLog->markProcessed(200, 'callback_query processed');
                return response()->json(['ok' => true]);
            }

            // Обработка текстовых команд
            if (isset($update['message']['text'])) {
                $this->handleMessage($update['message']);
                $webhookLog->markProcessed(200, 'message processed');
                return response()->json(['ok' => true]);
            }

            $webhookLog->markProcessed(200, 'ignored');
            return response()->json(['ok' => true]);
            
        } catch (\Exception $e) {
            Log::error('TelegramController: Error processing webhook', ['error' => $e->getMessage()]);
            $webhookLog->markProcessed(500, null, $e->getMessage());
            return response()->json(['ok' => true]);
        }
    }

    /**
     * Обработка callback_query (inline кнопки).
     */
    protected function handleCallbackQuery(array $callback): void
    {
        $callbackId = $callback['id'];
        $chatId = (string) $callback['from']['id'];
        $data = $callback['data'] ?? '';
        $messageId = $callback['message']['message_id'] ?? null;

        Log::info('TelegramController: Callback query', [
            'chat_id' => $chatId,
            'data' => $data,
        ]);

        // Проверка авторизации: chat_id должен быть привязан к пользователю
        $user = $this->telegram->findUserByChatId($chatId);

        if (!$user) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Вы не авторизованы в CRM', true);
            return;
        }

        // Парсим callback_data
        if (preg_match('/^(claim|ai_sync|close)_(\d+)$/', $data, $matches)) {
            $action = $matches[1];
            $dealId = (int) $matches[2];

            $deal = Deal::with(['contact', 'conversation'])->find($dealId);

            if (!$deal) {
                $this->telegram->answerCallbackQuery($callbackId, '❌ Сделка не найдена', true);
                return;
            }

            match ($action) {
                'claim' => $this->handleClaim($callbackId, $chatId, $messageId, $user, $deal),
                'ai_sync' => $this->handleAiSync($callbackId, $chatId, $user, $deal),
                'close' => $this->handleClose($callbackId, $chatId, $messageId, $user, $deal),
            };
        } else {
            $this->telegram->answerCallbackQuery($callbackId, '⚠️ Неизвестная команда');
        }
    }

    /**
     * Обработка команды "В работу" (claim).
     */
    protected function handleClaim(string $callbackId, string $chatId, ?int $messageId, User $user, Deal $deal): void
    {
        // Проверяем, есть ли уже менеджер
        if ($deal->manager_id !== null && $deal->manager_id !== $user->id) {
            $managerName = $deal->manager?->name ?? 'Другой менеджер';
            $this->telegram->answerCallbackQuery($callbackId, "❌ Сделка уже у: {$managerName}", true);
            return;
        }

        // Назначаем менеджера и меняем статус
        $deal->update([
            'manager_id' => $user->id,
            'status' => 'In Progress',
            'last_manager_response_at' => now(),
        ]);

        Log::info('TelegramController: Deal claimed', [
            'deal_id' => $deal->id,
            'user_id' => $user->id,
        ]);

        $this->telegram->answerCallbackQuery($callbackId, '✅ Вы взяли сделку в работу!');

        // Обновляем сообщение
        if ($messageId) {
            $clientName = $deal->contact?->name ?? 'Клиент';
            $newText = <<<MSG
✅ <b>Сделка #{$deal->id} в работе!</b>

👤 Клиент: <b>{$clientName}</b>
👨‍💼 Менеджер: {$user->name}
📊 Статус: В работе
MSG;

            $keyboard = [
                [
                    ['text' => '🤖 AI Анализ', 'callback_data' => "ai_sync_{$deal->id}"],
                    ['text' => '✅ Завершить', 'callback_data' => "close_{$deal->id}"],
                ],
                [
                    ['text' => '🔗 Открыть в CRM', 'url' => url("/deals/{$deal->id}")],
                ],
            ];

            $this->telegram->editMessage($chatId, $messageId, $newText, $keyboard);
        }
    }

    /**
     * Обработка команды "AI Анализ" (ai_sync).
     */
    protected function handleAiSync(string $callbackId, string $chatId, User $user, Deal $deal): void
    {
        $this->telegram->answerCallbackQuery($callbackId, '🤖 Анализирую...');

        if (!$this->aiService->isAvailable()) {
            $this->telegram->sendMessage($chatId, '❌ AI-сервис недоступен. Проверьте настройки Gemini API.');
            return;
        }

        try {
            // Получаем сообщения из Meta API
            $messages = [];
            if ($deal->conversation) {
                $messages = $this->metaApi->getMessages($deal->conversation->conversation_id, 20);
            }

            if (empty($messages)) {
                $this->telegram->sendMessage($chatId, "❌ Нет сообщений для анализа сделки #{$deal->id}");
                return;
            }

            // Получаем AI-анализ
            $analysis = $this->aiService->analyzeConversation(collect($messages));

            // Сохраняем в БД
            if ($analysis['summary']) {
                $deal->update([
                    'ai_summary' => $analysis['summary'],
                    'ai_score' => $analysis['score'],
                    'ai_summary_at' => now(),
                ]);
            }

            // Отправляем результат
            $this->telegram->sendAiAnalysis($chatId, $deal, $analysis['summary'], $analysis['score']);

            Log::info('TelegramController: AI analysis sent', [
                'deal_id' => $deal->id,
                'score' => $analysis['score'],
            ]);

        } catch (\Exception $e) {
            Log::error('TelegramController: AI error', ['error' => $e->getMessage()]);
            $this->telegram->sendMessage($chatId, "❌ Ошибка AI: {$e->getMessage()}");
        }
    }

    /**
     * Обработка команды "Завершить" (close).
     */
    protected function handleClose(string $callbackId, string $chatId, ?int $messageId, User $user, Deal $deal): void
    {
        // Проверяем права: только свои сделки или админ
        if ($deal->manager_id !== null && $deal->manager_id !== $user->id && !$user->isAdmin()) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Это не ваша сделка', true);
            return;
        }

        // Закрываем сделку
        $deal->update([
            'status' => 'Closed',
            'last_manager_response_at' => now(),
        ]);

        Log::info('TelegramController: Deal closed', [
            'deal_id' => $deal->id,
            'user_id' => $user->id,
        ]);

        $this->telegram->answerCallbackQuery($callbackId, '✅ Сделка завершена!');

        // Обновляем сообщение
        if ($messageId) {
            $clientName = $deal->contact?->name ?? 'Клиент';
            $newText = <<<MSG
🎉 <b>Сделка #{$deal->id} завершена!</b>

👤 Клиент: <b>{$clientName}</b>
👨‍💼 Завершил: {$user->name}
📊 Статус: Закрыта
MSG;

            $keyboard = [
                [
                    ['text' => '🔗 Открыть в CRM', 'url' => url("/deals/{$deal->id}")],
                ],
            ];

            $this->telegram->editMessage($chatId, $messageId, $newText, $keyboard);
        }
    }

    /**
     * Обработка текстовых сообщений и команд.
     */
    protected function handleMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        // Проверка авторизации
        $user = $this->telegram->findUserByChatId($chatId);

        // Команда /start — показываем приветствие и инструкцию
        if ($text === '/start') {
            $this->handleStart($chatId, $user);
            return;
        }

        // Проверка кода авторизации (6 символов)
        if (preg_match('/^[A-Z0-9]{6}$/', strtoupper($text))) {
            $this->handleAuthCode($chatId, strtoupper($text));
            return;
        }

        // Для остальных команд требуется авторизация
        if (!$user) {
            $this->telegram->sendMessage($chatId, <<<MSG
❌ <b>Вы не авторизованы</b>

Для авторизации:
1. Перейдите в ваш профиль в CRM
2. Нажмите «Получить код авторизации»
3. Отправьте полученный код сюда

Ваш Chat ID: <code>{$chatId}</code>
MSG);
            return;
        }

        // Команда /me — список активных сделок
        if ($text === '/me') {
            $this->telegram->sendMyDeals($user);
            return;
        }

        // Команда /help
        if ($text === '/help') {
            $this->handleHelp($chatId, $user);
            return;
        }

        // Неизвестная команда
        if (str_starts_with($text, '/')) {
            $this->telegram->sendMessage($chatId, "⚠️ Неизвестная команда. Введите /help для справки.");
        }
    }

    /**
     * Обработка команды /start.
     */
    protected function handleStart(string $chatId, ?User $user): void
    {
        if ($user) {
            $message = <<<MSG
👋 <b>Привет, {$user->name}!</b>

Вы авторизованы в CRM. Доступные команды:

/me — ваши активные сделки
/help — справка

Вы будете получать уведомления о новых сообщениях и сделках.
MSG;
        } else {
            $message = <<<MSG
👋 <b>Добро пожаловать!</b>

Это бот CRM-системы. Для работы необходимо связать ваш Telegram с аккаунтом CRM.

📱 Ваш Chat ID: <code>{$chatId}</code>

Скопируйте этот ID и попросите администратора добавить его в ваш профиль в CRM.
MSG;
        }

        $this->telegram->sendMessage($chatId, $message);
    }

    /**
     * Обработка команды /help.
     */
    protected function handleHelp(string $chatId, User $user): void
    {
        $adminHelp = $user->isAdmin() ? "\n\n<b>Админ-команды:</b>\nВ разработке..." : '';

        $message = <<<MSG
📚 <b>Справка по боту CRM</b>

<b>Команды:</b>
/me — список ваших активных сделок
/help — эта справка

<b>Кнопки в уведомлениях:</b>
🚀 В работу — взять сделку себе
🤖 AI Анализ — получить AI-анализ переписки
✅ Завершить — закрыть сделку
🔗 Открыть в CRM — перейти к сделке{$adminHelp}
MSG;

        $this->telegram->sendMessage($chatId, $message);
    }

    /**
     * Обработка кода авторизации.
     */
    protected function handleAuthCode(string $chatId, string $code): void
    {
        $user = $this->telegram->confirmAuthCode($code, $chatId);

        if ($user) {
            Log::info('TelegramController: User authorized via code', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
            ]);

            $this->telegram->sendMessage($chatId, <<<MSG
✅ <b>Авторизация успешна!</b>

Добро пожаловать, {$user->name}!

Теперь вы будете получать уведомления о новых сообщениях и сделках.

Доступные команды:
/me — ваши активные сделки
/help — справка
MSG);
        } else {
            $this->telegram->sendMessage($chatId, <<<MSG
❌ <b>Неверный или устаревший код</b>

Код действителен 10 минут. Попробуйте получить новый код в профиле CRM.
MSG);
        }
    }
}
