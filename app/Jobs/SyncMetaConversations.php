<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\User;
use App\Services\MetaApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class SyncMetaConversations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Количество попыток выполнения задачи.
     */
    public int $tries = 3;

    /**
     * Таймаут выполнения задачи в секундах.
     */
    public int $timeout = 300;

    /**
     * Платформа для синхронизации (null = все).
     */
    protected ?string $platform;

    /**
     * Создать новый экземпляр задачи.
     */
    public function __construct(?string $platform = null)
    {
        $this->platform = $platform;
    }

    /**
     * Выполнить задачу.
     */
    public function handle(MetaApiService $metaApi): void
    {
        Log::info('SyncMetaConversations: Начало синхронизации с Meta API', [
            'platform' => $this->platform ?? 'all',
            'started_at' => now()->toDateTimeString(),
        ]);

        $stats = [
            'conversations_processed' => 0,
            'contacts_created' => 0,
            'contacts_updated' => 0,
            'deals_created' => 0,
            'errors' => 0,
        ];

        try {
            // Получаем список бесед
            $conversations = $metaApi->getConversations($this->platform);

            Log::info('SyncMetaConversations: Получено бесед для обработки', [
                'count' => count($conversations),
            ]);

            foreach ($conversations as $conversationData) {
                try {
                    $this->processConversation($conversationData, $metaApi, $stats);
                } catch (Exception $e) {
                    $stats['errors']++;
                    Log::error('SyncMetaConversations: Ошибка обработки беседы', [
                        'conversation_id' => $conversationData['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (Exception $e) {
            Log::error('SyncMetaConversations: Критическая ошибка синхронизации', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::info('SyncMetaConversations: Синхронизация завершена', [
            'stats' => $stats,
            'finished_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Обработать одну беседу.
     */
    protected function processConversation(array $conversationData, MetaApiService $metaApi, array &$stats): void
    {
        $conversationId = $conversationData['id'];
        $updatedTime = Carbon::parse($conversationData['updated_time']);
        $platform = $metaApi->detectPlatform($conversationData);

        Log::info('SyncMetaConversations: Обработка беседы', [
            'conversation_id' => $conversationId,
            'platform' => $platform,
            'updated_time' => $updatedTime->toDateTimeString(),
        ]);

        DB::beginTransaction();

        try {
            // Извлекаем PSID участника
            $psid = $metaApi->extractParticipantPsid($conversationData);

            if (!$psid) {
                Log::warning('SyncMetaConversations: Не удалось определить PSID участника', [
                    'conversation_id' => $conversationId,
                ]);
                DB::rollBack();
                return;
            }

            // Создаём или обновляем контакт
            $contact = $this->syncContact($psid, $metaApi, $stats);

            if (!$contact) {
                Log::warning('SyncMetaConversations: Не удалось создать/обновить контакт', [
                    'psid' => $psid,
                ]);
                DB::rollBack();
                return;
            }

            // Создаём или обновляем беседу
            $conversation = $this->syncConversation(
                $conversationId,
                $updatedTime,
                $platform,
                $metaApi->buildConversationLink($conversationId)
            );

            $isNewConversation = $conversation->wasRecentlyCreated;

            // Создаём сделку для новой беседы
            if ($isNewConversation) {
                $this->createDeal($contact, $conversation, $stats);
            }

            $stats['conversations_processed']++;

            DB::commit();

            Log::info('SyncMetaConversations: Беседа обработана успешно', [
                'conversation_id' => $conversationId,
                'contact_id' => $contact->id,
                'is_new' => $isNewConversation,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Синхронизировать контакт.
     */
    protected function syncContact(string $psid, MetaApiService $metaApi, array &$stats): ?Contact
    {
        // Проверяем, существует ли контакт
        $contact = Contact::where('psid', $psid)->first();
        $isNew = !$contact;

        if ($isNew) {
            $contact = new Contact(['psid' => $psid]);
        }

        // Получаем профиль пользователя из Meta API
        try {
            $profile = $metaApi->getUserProfile($psid);

            $contact->first_name = $profile['first_name'] ?? null;
            $contact->last_name = $profile['last_name'] ?? null;
            $contact->name = $profile['name'] ?? null;

            Log::info('SyncMetaConversations: Профиль пользователя получен', [
                'psid' => $psid,
                'name' => $contact->name,
            ]);

        } catch (Exception $e) {
            Log::warning('SyncMetaConversations: Не удалось получить профиль пользователя', [
                'psid' => $psid,
                'error' => $e->getMessage(),
            ]);

            // Если профиль не получен, но контакт новый — создаём с минимальными данными
            if (!$isNew) {
                return $contact;
            }
        }

        $contact->save();

        if ($isNew) {
            $stats['contacts_created']++;
            Log::info('SyncMetaConversations: Создан новый контакт', [
                'contact_id' => $contact->id,
                'psid' => $psid,
            ]);
        } else {
            $stats['contacts_updated']++;
            Log::info('SyncMetaConversations: Контакт обновлён', [
                'contact_id' => $contact->id,
                'psid' => $psid,
            ]);
        }

        return $contact;
    }

    /**
     * Синхронизировать беседу.
     */
    protected function syncConversation(
        string $conversationId,
        Carbon $updatedTime,
        string $platform,
        string $link
    ): Conversation {
        $conversation = Conversation::updateOrCreate(
            ['conversation_id' => $conversationId],
            [
                'updated_time' => $updatedTime,
                'platform' => $platform,
                'link' => $link,
            ]
        );

        if ($conversation->wasRecentlyCreated) {
            Log::info('SyncMetaConversations: Создана новая беседа', [
                'id' => $conversation->id,
                'conversation_id' => $conversationId,
                'platform' => $platform,
            ]);
        } else {
            Log::info('SyncMetaConversations: Беседа обновлена', [
                'id' => $conversation->id,
                'conversation_id' => $conversationId,
                'updated_time' => $updatedTime->toDateTimeString(),
            ]);
        }

        return $conversation;
    }

    /**
     * Создать сделку для новой беседы.
     */
    protected function createDeal(Contact $contact, Conversation $conversation, array &$stats): Deal
    {
        // Проверяем, нет ли уже сделки для этого контакта и беседы
        $existingDeal = Deal::where('contact_id', $contact->id)
            ->where('conversation_id', $conversation->id)
            ->first();

        if ($existingDeal) {
            Log::info('SyncMetaConversations: Сделка уже существует', [
                'deal_id' => $existingDeal->id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
            ]);
            return $existingDeal;
        }

        // Round Robin: назначаем менеджера с наименьшим количеством активных сделок
        $managerId = $this->getNextManagerByRoundRobin();

        $deal = Deal::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'manager_id' => $managerId,
            'status' => 'New',
            'comment' => 'Автоматически создано при синхронизации с Meta API',
            'is_viewed' => false,
        ]);

        $stats['deals_created']++;

        Log::info('SyncMetaConversations: Создана новая сделка', [
            'deal_id' => $deal->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'manager_id' => $managerId,
            'status' => 'New',
        ]);

        return $deal;
    }

    /**
     * Round Robin: получить менеджера с наименьшим количеством активных сделок.
     */
    protected function getNextManagerByRoundRobin(): ?int
    {
        // Получаем всех активных менеджеров
        $managers = User::where('role', 'manager')
            ->get(['id', 'name']);

        if ($managers->isEmpty()) {
            Log::warning('SyncMetaConversations: Нет доступных менеджеров для назначения');
            return null;
        }

        // Подсчитываем количество активных сделок (статус "В работе") для каждого менеджера
        $managersWithCounts = $managers->map(function ($manager) {
            $activeDealsCount = Deal::where('manager_id', $manager->id)
                ->where('status', 'In Progress')
                ->count();

            return [
                'id' => $manager->id,
                'name' => $manager->name,
                'active_deals' => $activeDealsCount,
            ];
        });

        // Сортируем по количеству активных сделок (меньше — выше приоритет)
        $sortedManagers = $managersWithCounts->sortBy('active_deals');

        // Выбираем менеджера с наименьшим количеством сделок
        $selectedManager = $sortedManagers->first();

        Log::info('SyncMetaConversations: Round Robin — выбран менеджер', [
            'manager_id' => $selectedManager['id'],
            'manager_name' => $selectedManager['name'],
            'active_deals' => $selectedManager['active_deals'],
        ]);

        return $selectedManager['id'];
    }

    /**
     * Обработка неудачного выполнения задачи.
     */
    public function failed(Exception $exception): void
    {
        Log::error('SyncMetaConversations: Задача завершилась с ошибкой', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
