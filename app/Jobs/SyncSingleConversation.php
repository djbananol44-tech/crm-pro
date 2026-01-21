<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\MetaApiErrorNotification;
use App\Services\MetaApiService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SyncSingleConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    protected string $senderId;

    protected string $platform;

    protected ?string $messageText;

    public function __construct(string $senderId, string $platform = 'messenger', ?string $messageText = null)
    {
        $this->senderId = $senderId;
        $this->platform = $platform;
        $this->messageText = $messageText;
    }

    public function handle(MetaApiService $metaApi): void
    {
        Log::info('SyncSingleConversation: Начало синхронизации', [
            'sender_id' => $this->senderId,
            'platform' => $this->platform,
        ]);

        DB::beginTransaction();

        try {
            $contact = $this->syncContact($metaApi);

            if (!$contact) {
                DB::rollBack();

                return;
            }

            $conversations = $metaApi->getConversations($this->platform);
            $conversationData = $this->findConversationBySender($conversations, $metaApi);

            if (!$conversationData) {
                DB::commit();

                return;
            }

            $conversation = $this->syncConversation($conversationData, $metaApi);

            // Находим или создаём сделку
            $deal = Deal::where('contact_id', $contact->id)
                ->where('conversation_id', $conversation->id)
                ->first();

            $isNewDeal = false;
            if (!$deal) {
                $deal = $this->createDeal($contact, $conversation);
                $isNewDeal = true;

                // Логируем создание
                ActivityLog::logDealCreated($deal);
            }

            // Проверяем приоритетные ключевые слова
            $this->checkAndSetPriority($deal);

            // Обновляем время последнего сообщения клиента
            $deal->update([
                'last_client_message_at' => now(),
                'is_viewed' => false,
            ]);

            DB::commit();

            // Отправляем Telegram уведомление
            $this->sendTelegramNotification($deal, $contact, $isNewDeal);

            Log::info('SyncSingleConversation: Синхронизация завершена', [
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'deal_id' => $deal->id,
                'is_priority' => $deal->is_priority,
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'token')) {
                $this->notifyAdminsAboutTokenError($e);
            }

            Log::error('SyncSingleConversation: Ошибка', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Проверить и установить приоритет на основе ключевых слов.
     */
    protected function checkAndSetPriority(Deal $deal): void
    {
        if (!$this->messageText || $deal->is_priority) {
            return;
        }

        $keywords = Deal::findPriorityKeywords($this->messageText);

        if (!empty($keywords)) {
            $deal->update(['is_priority' => true]);

            $reason = implode(', ', array_slice($keywords, 0, 3));
            ActivityLog::logPrioritySet($deal, true, $reason);

            Log::info('SyncSingleConversation: Установлен приоритет', [
                'deal_id' => $deal->id,
                'keywords' => $keywords,
            ]);
        }
    }

    protected function sendTelegramNotification(Deal $deal, Contact $contact, bool $isNewDeal): void
    {
        try {
            $telegram = app(TelegramService::class);

            if (!$telegram->isAvailable()) {
                return;
            }

            $manager = $deal->manager;
            if (!$manager) {
                return;
            }

            $clientName = $contact->name ?? $contact->first_name ?? 'Клиент';

            if ($isNewDeal) {
                $telegram->notifyNewDeal($manager, $deal, $clientName);
            } else {
                $telegram->notifyNewMessage($manager, $deal, $clientName, $this->messageText);
            }

        } catch (Exception $e) {
            Log::warning('SyncSingleConversation: Ошибка Telegram', ['error' => $e->getMessage()]);
        }
    }

    protected function syncContact(MetaApiService $metaApi): ?Contact
    {
        $contact = Contact::where('psid', $this->senderId)->first();
        $isNew = !$contact;

        if ($isNew) {
            $contact = new Contact(['psid' => $this->senderId]);
        }

        try {
            $profile = $metaApi->getUserProfile($this->senderId);
            $contact->first_name = $profile['first_name'] ?? null;
            $contact->last_name = $profile['last_name'] ?? null;
            $contact->name = $profile['name'] ?? null;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), '401')) {
                $this->notifyAdminsAboutTokenError($e);

                throw $e;
            }
        }

        $contact->save();

        return $contact;
    }

    protected function findConversationBySender(array $conversations, MetaApiService $metaApi): ?array
    {
        foreach ($conversations as $conv) {
            $psid = $metaApi->extractParticipantPsid($conv);
            if ($psid === $this->senderId) {
                return $conv;
            }
        }

        return null;
    }

    protected function syncConversation(array $data, MetaApiService $metaApi): Conversation
    {
        $platform = $metaApi->detectPlatform($data);
        $pageId = $metaApi->getPageId();
        $labels = $metaApi->extractLabels($data);

        return Conversation::updateOrCreate(
            ['conversation_id' => $data['id']],
            [
                'updated_time' => Carbon::parse($data['updated_time']),
                'platform' => $platform,
                'page_id' => $pageId,
                'labels' => $labels,
                'link' => $metaApi->buildConversationLink($data['id'], $platform, $pageId),
            ]
        );
    }

    protected function createDeal(Contact $contact, Conversation $conversation): Deal
    {
        $managerId = $this->getNextManagerByRoundRobin();

        // Проверяем приоритет сразу при создании
        $isPriority = $this->messageText && Deal::containsPriorityKeywords($this->messageText);

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'manager_id' => $managerId,
            'status' => 'New',
            'comment' => 'Создано автоматически через Webhook',
            'last_client_message_at' => now(),
            'is_viewed' => false,
            'is_priority' => $isPriority,
        ]);

        Log::info('SyncSingleConversation: Создана сделка', [
            'deal_id' => $deal->id,
            'manager_id' => $managerId,
            'is_priority' => $isPriority,
        ]);

        return $deal;
    }

    protected function getNextManagerByRoundRobin(): ?int
    {
        $managers = User::where('role', 'manager')->get();

        if ($managers->isEmpty()) {
            return null;
        }

        $managerActiveDeals = Deal::whereIn('status', ['New', 'In Progress'])
            ->select('manager_id', DB::raw('count(*) as cnt'))
            ->groupBy('manager_id')
            ->pluck('cnt', 'manager_id');

        $minDeals = PHP_INT_MAX;
        $nextManagerId = $managers->first()->id;

        foreach ($managers as $manager) {
            $activeDeals = $managerActiveDeals->get($manager->id, 0);
            if ($activeDeals < $minDeals) {
                $minDeals = $activeDeals;
                $nextManagerId = $manager->id;
            }
        }

        return $nextManagerId;
    }

    protected function notifyAdminsAboutTokenError(Exception $e): void
    {
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new MetaApiErrorNotification($e->getMessage()));
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('SyncSingleConversation: Задача провалена', ['error' => $exception->getMessage()]);
    }
}
