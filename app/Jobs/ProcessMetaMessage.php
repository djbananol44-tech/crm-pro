<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\AiAnalysisService;
use App\Services\MetaApiService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Обработка входящих сообщений из Meta через Redis Queue
 * Meta → Redis → ProcessMetaMessage → Telegram
 */
class ProcessMetaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    public function __construct(
        public array $payload,
        public string $platform = 'messenger'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        MetaApiService $metaApi,
        TelegramService $telegram,
        AiAnalysisService $ai
    ): void {
        try {
            SystemLog::queue('info', 'Начало обработки Meta сообщения', [
                'platform' => $this->platform,
                'payload_size' => strlen(json_encode($this->payload)),
            ]);

            // Извлекаем данные из payload
            $messaging = $this->payload['messaging'][0] ?? $this->payload;
            $senderId = $messaging['sender']['id'] ?? null;
            $messageText = $messaging['message']['text'] ?? null;
            $timestamp = $messaging['timestamp'] ?? now()->timestamp * 1000;

            if (!$senderId) {
                SystemLog::queue('warning', 'Meta сообщение без sender ID', $this->payload);

                return;
            }

            // 1. Получаем или создаём контакт
            $contact = $this->getOrCreateContact($senderId, $metaApi);

            // 2. Получаем или создаём беседу
            $conversation = $this->getOrCreateConversation($contact, $senderId);

            // 3. Получаем или создаём сделку
            $deal = $this->getOrCreateDeal($contact, $conversation, $messageText);

            // 4. Обновляем время последнего сообщения клиента
            $deal->update([
                'last_client_message_at' => now(),
            ]);

            // 5. Проверяем приоритетные ключевые слова
            $this->checkPriorityKeywords($deal, $messageText);

            // 6. Отправляем уведомление в Telegram
            $this->notifyTelegram($telegram, $deal, $contact, $messageText);

            // 7. Запускаем AI анализ если включён
            if ($ai->isAvailable() && !$deal->ai_summary) {
                dispatch(new GenerateAiAnalysis($deal->id))->onQueue('ai');
            }

            SystemLog::queue('info', 'Meta сообщение успешно обработано', [
                'contact_id' => $contact->id,
                'deal_id' => $deal->id,
                'message_preview' => substr($messageText ?? '', 0, 50),
            ]);

        } catch (\Exception $e) {
            SystemLog::queue('error', 'Ошибка обработки Meta сообщения', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Получить или создать контакт
     */
    protected function getOrCreateContact(string $psid, MetaApiService $metaApi): Contact
    {
        $contact = Contact::where('psid', $psid)->first();

        if (!$contact) {
            // Пробуем получить профиль из Meta API
            $profile = $metaApi->getUserProfile($psid);

            $contact = Contact::create([
                'psid' => $psid,
                'first_name' => $profile['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? null,
                'name' => trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')) ?: "Клиент {$psid}",
            ]);

            SystemLog::queue('info', 'Создан новый контакт', [
                'contact_id' => $contact->id,
                'psid' => $psid,
            ]);
        }

        return $contact;
    }

    /**
     * Получить или создать беседу
     */
    protected function getOrCreateConversation(Contact $contact, string $psid): Conversation
    {
        $pageId = Setting::get('meta_page_id', '');
        $conversationId = "conv_{$psid}_{$pageId}";

        $conversation = Conversation::where('conversation_id', $conversationId)->first();

        if (!$conversation) {
            // Строим корректную ссылку на Meta Business Suite
            $link = $this->buildMetaBusinessSuiteLink($conversationId, $this->platform, $pageId);

            $conversation = Conversation::create([
                'conversation_id' => $conversationId,
                'contact_id' => $contact->id,
                'platform' => $this->platform,
                'page_id' => $pageId,
                'link' => $link,
                'updated_time' => now(),
            ]);
        } else {
            $conversation->update(['updated_time' => now()]);
        }

        return $conversation;
    }

    /**
     * Построить ссылку на Meta Business Suite
     */
    protected function buildMetaBusinessSuiteLink(string $conversationId, string $platform, ?string $pageId): string
    {
        if (empty($pageId)) {
            return "https://www.facebook.com/messages/t/{$conversationId}";
        }

        $baseUrl = 'https://business.facebook.com/latest/inbox/all';
        $params = [
            'asset_id' => $pageId,
            'selected_item_id' => $conversationId,
        ];

        if ($platform === 'instagram') {
            $params['mailbox_id'] = 'instagram';
        }

        return $baseUrl.'?'.http_build_query($params);
    }

    /**
     * Получить или создать сделку
     */
    protected function getOrCreateDeal(Contact $contact, Conversation $conversation, ?string $messageText): Deal
    {
        // Ищем существующую открытую сделку
        $deal = Deal::where('contact_id', $contact->id)
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', ['New', 'In Progress'])
            ->first();

        if (!$deal) {
            // Назначаем менеджера по Round Robin
            $managerId = $this->assignManagerRoundRobin();

            $deal = Deal::create([
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'manager_id' => $managerId,
                'status' => 'New',
                'last_client_message_at' => now(),
            ]);

            SystemLog::queue('info', 'Создана новая сделка', [
                'deal_id' => $deal->id,
                'manager_id' => $managerId,
            ]);
        }

        return $deal;
    }

    /**
     * Назначение менеджера по Round Robin
     */
    protected function assignManagerRoundRobin(): ?int
    {
        $manager = User::where('role', 'manager')
            ->withCount(['deals' => function ($query) {
                $query->where('status', 'In Progress');
            }])
            ->orderBy('deals_count', 'asc')
            ->first();

        return $manager?->id;
    }

    /**
     * Проверка приоритетных ключевых слов
     */
    protected function checkPriorityKeywords(Deal $deal, ?string $messageText): void
    {
        if (!$messageText) {
            return;
        }

        $keywords = ['цена', 'сколько', 'купить', 'прайс', 'доставка', 'оплата', 'заказ', 'срочно'];
        $messageLower = mb_strtolower($messageText);

        foreach ($keywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                $deal->update(['is_priority' => true]);

                SystemLog::queue('info', 'Сделка помечена как приоритетная', [
                    'deal_id' => $deal->id,
                    'keyword' => $keyword,
                ]);
                break;
            }
        }
    }

    /**
     * Отправка уведомления в Telegram
     */
    protected function notifyTelegram(TelegramService $telegram, Deal $deal, Contact $contact, ?string $messageText): void
    {
        if (!$telegram->isConfigured()) {
            return;
        }

        // Уведомляем назначенного менеджера
        if ($deal->manager && $deal->manager->telegram_chat_id) {
            $telegram->sendDealNotification($deal, '📩 Новое сообщение от клиента!');
        }

        // Если сделка новая и без менеджера — уведомляем всех
        if ($deal->status === 'New' && !$deal->manager_id) {
            $admins = User::where('role', 'admin')->whereNotNull('telegram_chat_id')->get();
            foreach ($admins as $admin) {
                $telegram->sendDealNotification($deal, '🆕 Новая заявка без менеджера!', $admin->telegram_chat_id);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Извлекаем контекст БЕЗ PII
        $messaging = $this->payload['messaging'][0] ?? $this->payload;
        $senderId = $messaging['sender']['id'] ?? 'unknown';

        // Пытаемся найти связанные ID
        $contact = Contact::where('psid', $senderId)->first();
        $deal = $contact ? Deal::where('contact_id', $contact->id)
            ->whereIn('status', ['New', 'In Progress'])
            ->first() : null;

        SystemLog::queue('critical', 'Job ProcessMetaMessage завершился с ошибкой', [
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'error_file' => $exception->getFile().':'.$exception->getLine(),
            // Контекст без PII
            'contact_id' => $contact?->id,
            'deal_id' => $deal?->id,
            'platform' => $this->platform,
            'attempt' => $this->attempts(),
            // НЕ логируем payload целиком — может содержать PII
            'payload_keys' => array_keys($this->payload),
        ]);

        Log::error('ProcessMetaMessage: Job failed', [
            'contact_id' => $contact?->id,
            'deal_id' => $deal?->id,
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts(),
        ]);
    }
}
