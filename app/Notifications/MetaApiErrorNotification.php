<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MetaApiErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Сообщение об ошибке.
     */
    protected string $errorMessage;

    /**
     * Создать новый экземпляр уведомления.
     */
    public function __construct(string $errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * Каналы доставки уведомления.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Данные для уведомления в БД.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => '⚠️ Ошибка Meta API',
            'body' => $this->getNotificationBody(),
            'error' => $this->errorMessage,
            'type' => 'meta_api_error',
            'severity' => 'critical',
            'action_url' => '/admin/settings',
            'action_label' => 'Обновить токен',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Получить текст уведомления.
     */
    protected function getNotificationBody(): string
    {
        if (str_contains($this->errorMessage, '401')) {
            return 'Токен доступа Meta API истёк или недействителен. Пожалуйста, обновите токен в настройках системы.';
        }

        if (str_contains($this->errorMessage, '403')) {
            return 'Недостаточно прав для доступа к Meta API. Проверьте разрешения приложения.';
        }

        if (str_contains($this->errorMessage, '429')) {
            return 'Превышен лимит запросов к Meta API. Повторите попытку позже.';
        }

        return "Произошла ошибка при работе с Meta API: {$this->errorMessage}";
    }
}
