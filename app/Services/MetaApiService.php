<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\MetaApiErrorNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Client\RequestException;
use Exception;

class MetaApiService
{
    /**
     * Базовый URL Graph API
     */
    protected string $baseUrl = 'https://graph.facebook.com/v18.0';

    /**
     * ID страницы Facebook
     */
    protected string $pageId;

    /**
     * Токен доступа страницы
     */
    protected string $accessToken;

    /**
     * Таймаут запросов в секундах
     */
    protected int $timeout = 30;

    /**
     * Количество повторных попыток
     */
    protected int $retries = 3;

    public function __construct()
    {
        // Все настройки берём ТОЛЬКО из БД (Zero-Config)
        $this->pageId = Setting::get('meta_page_id', '');
        $this->accessToken = Setting::get('meta_access_token', '');
    }

    /**
     * Получить список бесед страницы.
     *
     * @param string|null $platform Платформа: 'messenger' или 'instagram' (null = все)
     * @return array
     * @throws Exception
     */
    public function getConversations(?string $platform = null): array
    {
        $this->validateCredentials();

        Log::info('MetaApiService: Запрос списка бесед', [
            'page_id' => $this->pageId,
            'platform' => $platform ?? 'all',
        ]);

        try {
            $endpoint = "{$this->baseUrl}/{$this->pageId}/conversations";
            
            $params = [
                'fields' => 'id,updated_time,participants,link',
                'access_token' => $this->accessToken,
            ];

            // Добавляем фильтр по платформе, если указан
            if ($platform) {
                $params['platform'] = $platform;
            }

            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 1000)
                ->get($endpoint, $params);

            if ($response->failed()) {
                $this->handleApiError($response, 'getConversations');
            }

            $data = $response->json();
            
            Log::info('MetaApiService: Получены беседы', [
                'count' => count($data['data'] ?? []),
            ]);

            return $data['data'] ?? [];

        } catch (RequestException $e) {
            Log::error('MetaApiService: Ошибка HTTP запроса getConversations', [
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Ошибка при получении списка бесед: ' . $e->getMessage());
        }
    }

    /**
     * Получить сообщения беседы.
     *
     * @param string $conversationId ID беседы
     * @param int $limit Количество сообщений (по умолчанию 20)
     * @return array
     * @throws Exception
     */
    public function getMessages(string $conversationId, int $limit = 20): array
    {
        $this->validateCredentials();

        Log::info('MetaApiService: Запрос сообщений беседы', [
            'conversation_id' => $conversationId,
            'limit' => $limit,
        ]);

        try {
            $endpoint = "{$this->baseUrl}/{$conversationId}/messages";
            
            $params = [
                'fields' => 'id,created_time,from,to,message',
                'limit' => $limit,
                'access_token' => $this->accessToken,
            ];

            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 1000)
                ->get($endpoint, $params);

            if ($response->failed()) {
                $this->handleApiError($response, 'getMessages');
            }

            $data = $response->json();

            Log::info('MetaApiService: Получены сообщения', [
                'conversation_id' => $conversationId,
                'count' => count($data['data'] ?? []),
            ]);

            return $data['data'] ?? [];

        } catch (RequestException $e) {
            Log::error('MetaApiService: Ошибка HTTP запроса getMessages', [
                'conversation_id' => $conversationId,
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Ошибка при получении сообщений: ' . $e->getMessage());
        }
    }

    /**
     * Получить профиль пользователя по PSID.
     *
     * @param string $psid Page Scoped User ID
     * @return array
     * @throws Exception
     */
    public function getUserProfile(string $psid): array
    {
        $this->validateCredentials();

        Log::info('MetaApiService: Запрос профиля пользователя', [
            'psid' => $psid,
        ]);

        try {
            $endpoint = "{$this->baseUrl}/{$psid}";
            
            $params = [
                'fields' => 'first_name,last_name,name,profile_pic',
                'access_token' => $this->accessToken,
            ];

            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 1000)
                ->get($endpoint, $params);

            if ($response->failed()) {
                $this->handleApiError($response, 'getUserProfile');
            }

            $data = $response->json();

            Log::info('MetaApiService: Получен профиль пользователя', [
                'psid' => $psid,
                'name' => $data['name'] ?? 'Не указано',
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('MetaApiService: Ошибка HTTP запроса getUserProfile', [
                'psid' => $psid,
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Ошибка при получении профиля пользователя: ' . $e->getMessage());
        }
    }

    /**
     * Отправить сообщение пользователю.
     *
     * @param string $psid Page Scoped User ID
     * @param string $message Текст сообщения
     * @param string|null $tag Message Tag для отправки вне 24-часового окна
     * @return array
     * @throws Exception
     */
    public function sendMessage(string $psid, string $message, ?string $tag = null): array
    {
        $this->validateCredentials();

        Log::info('MetaApiService: Отправка сообщения', [
            'psid' => $psid,
            'tag' => $tag,
        ]);

        try {
            $endpoint = "{$this->baseUrl}/{$this->pageId}/messages";

            $payload = [
                'recipient' => ['id' => $psid],
                'message' => ['text' => $message],
                'access_token' => $this->accessToken,
            ];

            // Если указан тег — используем MESSAGE_TAG, иначе RESPONSE
            if ($tag) {
                $payload['messaging_type'] = 'MESSAGE_TAG';
                $payload['tag'] = $tag;
            } else {
                $payload['messaging_type'] = 'RESPONSE';
            }

            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 1000)
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $this->handleApiError($response, 'sendMessage');
            }

            $data = $response->json();

            Log::info('MetaApiService: Сообщение отправлено', [
                'psid' => $psid,
                'message_id' => $data['message_id'] ?? null,
                'tag' => $tag,
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('MetaApiService: Ошибка HTTP запроса sendMessage', [
                'psid' => $psid,
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Ошибка при отправке сообщения: ' . $e->getMessage());
        }
    }

    /**
     * Проверить статус API соединения.
     */
    public function testConnection(): array
    {
        if (empty($this->pageId) || empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => 'Настройки Meta API не заполнены',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/{$this->pageId}", [
                    'fields' => 'id,name',
                    'access_token' => $this->accessToken,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => "Подключено к странице: {$data['name']}",
                    'page_id' => $data['id'],
                    'page_name' => $data['name'],
                ];
            }

            $error = $response->json('error.message') ?? 'Неизвестная ошибка';
            return [
                'success' => false,
                'message' => "Ошибка: {$error}",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Проверка наличия учётных данных.
     *
     * @throws Exception
     */
    protected function validateCredentials(): void
    {
        if (empty($this->pageId)) {
            throw new Exception('META_PAGE_ID не настроен. Укажите значение в настройках системы.');
        }

        if (empty($this->accessToken)) {
            throw new Exception('META_ACCESS_TOKEN не настроен. Укажите значение в настройках системы.');
        }
    }

    /**
     * Обработка ошибок API.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param string $method
     * @throws Exception
     */
    protected function handleApiError($response, string $method): void
    {
        $error = $response->json('error') ?? [];
        $message = $error['message'] ?? 'Неизвестная ошибка';
        $code = $error['code'] ?? $response->status();
        $httpStatus = $response->status();

        Log::error("MetaApiService: Ошибка API в методе {$method}", [
            'status' => $httpStatus,
            'error_code' => $code,
            'error_message' => $message,
        ]);

        // При ошибке 401 (токен истёк) уведомляем администраторов
        if ($httpStatus === 401 || $code === 190 || str_contains(strtolower($message), 'token')) {
            $this->notifyAdminsAboutAuthError($message, $code);
        }

        throw new Exception("Meta API Error [{$code}]: {$message} (HTTP {$httpStatus})");
    }

    /**
     * Уведомить администраторов об ошибке авторизации.
     */
    protected function notifyAdminsAboutAuthError(string $message, $code): void
    {
        Log::error('MetaApiService: Ошибка авторизации, уведомление администраторов', [
            'error_code' => $code,
            'message' => $message,
        ]);

        try {
            $admins = User::where('role', 'admin')->get();

            if ($admins->isEmpty()) {
                Log::warning('MetaApiService: Нет администраторов для уведомления');
                return;
            }

            $errorText = "Ошибка авторизации Meta API [{$code}]: {$message}";
            Notification::send($admins, new MetaApiErrorNotification($errorText));

            Log::info('MetaApiService: Уведомления отправлены администраторам', [
                'admins_count' => $admins->count(),
            ]);

        } catch (Exception $e) {
            Log::error('MetaApiService: Не удалось отправить уведомление', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Определить платформу по данным беседы.
     *
     * @param array $conversation
     * @return string
     */
    public function detectPlatform(array $conversation): string
    {
        // Если в данных есть явное указание платформы
        if (isset($conversation['platform'])) {
            return strtolower($conversation['platform']);
        }

        // По умолчанию Messenger
        return 'messenger';
    }

    /**
     * Извлечь PSID участника (не страницы) из беседы.
     *
     * @param array $conversation
     * @return string|null
     */
    public function extractParticipantPsid(array $conversation): ?string
    {
        $participants = $conversation['participants']['data'] ?? [];
        
        foreach ($participants as $participant) {
            // Участник, который не является страницей
            if (isset($participant['id']) && $participant['id'] !== $this->pageId) {
                return $participant['id'];
            }
        }

        return null;
    }

    /**
     * Сформировать ссылку на беседу.
     *
     * @param string $conversationId
     * @return string
     */
    public function buildConversationLink(string $conversationId): string
    {
        return "https://www.facebook.com/messages/t/{$conversationId}";
    }

    /**
     * Получить текущий Page ID.
     */
    public function getPageId(): string
    {
        return $this->pageId;
    }

    /**
     * Проверить, настроен ли сервис.
     */
    public function isConfigured(): bool
    {
        return !empty($this->pageId) && !empty($this->accessToken);
    }
}
