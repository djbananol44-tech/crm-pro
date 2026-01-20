<?php

namespace App\Notifications;

use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SecurityViolationNotification extends Notification
{
    use Queueable;

    protected string $message;
    protected ?Deal $deal;

    public function __construct(string $message, ?Deal $deal = null)
    {
        $this->message = $message;
        $this->deal = $deal;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => '⚠️ Нарушение безопасности Meta',
            'message' => $this->message,
            'deal_id' => $this->deal?->id,
            'deal_url' => $this->deal 
                ? route('filament.admin.resources.deals.view', $this->deal) 
                : null,
            'severity' => 'critical',
            'icon' => 'heroicon-o-shield-exclamation',
            'color' => 'danger',
        ];
    }
}
