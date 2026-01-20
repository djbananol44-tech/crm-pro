<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'user_id',
        'action',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'duration_seconds' => 'integer',
        ];
    }

    // === Константы действий ===
    const ACTION_CREATED = 'created';
    const ACTION_STATUS_CHANGED = 'status_changed';
    const ACTION_MANAGER_ASSIGNED = 'manager_assigned';
    const ACTION_VIEWED = 'viewed';
    const ACTION_COMMENT_ADDED = 'comment_added';
    const ACTION_REMINDER_SET = 'reminder_set';
    const ACTION_AI_ANALYZED = 'ai_analyzed';
    const ACTION_PRIORITY_SET = 'priority_set';
    const ACTION_RATED = 'rated';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';

    // === Отношения ===
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // === Скоупы ===
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    // === Хелперы для создания логов ===
    protected static function createWithRequest(array $data, ?Request $request = null): self
    {
        if ($request) {
            $data['ip_address'] = $request->ip();
            $data['user_agent'] = substr($request->userAgent() ?? '', 0, 255);
        }

        return self::create($data);
    }

    public static function logDealCreated(Deal $deal, ?User $user = null): self
    {
        return self::createWithRequest([
            'deal_id' => $deal->id,
            'user_id' => $user?->id,
            'action' => self::ACTION_CREATED,
            'description' => 'Сделка создана',
            'metadata' => ['status' => $deal->status],
        ], request());
    }

    public static function logStatusChanged(Deal $deal, string $oldStatus, string $newStatus, ?User $user = null): self
    {
        $statusLabels = [
            'New' => 'Новая',
            'In Progress' => 'В работе',
            'Closed' => 'Закрыта',
        ];

        return self::createWithRequest([
            'deal_id' => $deal->id,
            'user_id' => $user?->id,
            'action' => self::ACTION_STATUS_CHANGED,
            'description' => "Статус: {$statusLabels[$oldStatus]} → {$statusLabels[$newStatus]}",
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
        ], request());
    }

    public static function logManagerAssigned(Deal $deal, ?User $oldManager, User $newManager, ?User $actor = null): self
    {
        $oldName = $oldManager?->name ?? 'Не назначен';
        return self::createWithRequest([
            'deal_id' => $deal->id,
            'user_id' => $actor?->id,
            'action' => self::ACTION_MANAGER_ASSIGNED,
            'description' => "Назначен: {$newManager->name}" . ($oldManager ? " (был: {$oldName})" : ''),
            'metadata' => [
                'old_manager_id' => $oldManager?->id,
                'new_manager_id' => $newManager->id,
            ],
        ], request());
    }

    public static function logViewed(Deal $deal, User $user): self
    {
        return self::createWithRequest([
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'action' => self::ACTION_VIEWED,
            'description' => "Просмотр: {$user->name}",
        ], request());
    }

    public static function logCommentAdded(Deal $deal, User $user, ?string $preview = null): self
    {
        $desc = 'Комментарий добавлен';
        if ($preview) {
            $desc .= ': "' . mb_substr($preview, 0, 50) . '..."';
        }

        return self::createWithRequest([
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'action' => self::ACTION_COMMENT_ADDED,
            'description' => $desc,
            'metadata' => ['preview' => $preview],
        ], request());
    }

    public static function logReminderSet(Deal $deal, User $user, string $reminderAt): self
    {
        return self::createWithRequest([
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'action' => self::ACTION_REMINDER_SET,
            'description' => "Напоминание: {$reminderAt}",
            'metadata' => ['reminder_at' => $reminderAt],
        ], request());
    }

    public static function logAiAnalyzed(Deal $deal, ?int $score = null): self
    {
        $desc = 'AI-анализ';
        if ($score) {
            $desc .= " (Score: {$score})";
        }

        return self::create([
            'deal_id' => $deal->id,
            'user_id' => null,
            'action' => self::ACTION_AI_ANALYZED,
            'description' => $desc,
            'metadata' => ['score' => $score],
        ]);
    }

    public static function logPrioritySet(Deal $deal, bool $isPriority, ?string $reason = null): self
    {
        $desc = $isPriority ? 'Приоритет установлен' : 'Приоритет снят';
        if ($reason) {
            $desc .= " ({$reason})";
        }

        return self::create([
            'deal_id' => $deal->id,
            'user_id' => null,
            'action' => self::ACTION_PRIORITY_SET,
            'description' => $desc,
            'metadata' => ['is_priority' => $isPriority, 'reason' => $reason],
        ]);
    }

    public static function logRated(Deal $deal, int $rating, string $review): self
    {
        return self::create([
            'deal_id' => $deal->id,
            'user_id' => null,
            'action' => self::ACTION_RATED,
            'description' => "AI-оценка: {$rating}/5",
            'metadata' => ['rating' => $rating, 'review' => $review],
        ]);
    }

    public static function logLogin(User $user): self
    {
        return self::createWithRequest([
            'deal_id' => null,
            'user_id' => $user->id,
            'action' => self::ACTION_LOGIN,
            'description' => "Вход в систему: {$user->name}",
        ], request());
    }

    // === Форматирование ===
    public function getIconAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => '🆕',
            self::ACTION_STATUS_CHANGED => '🔄',
            self::ACTION_MANAGER_ASSIGNED => '👨‍💼',
            self::ACTION_VIEWED => '👁️',
            self::ACTION_COMMENT_ADDED => '💬',
            self::ACTION_REMINDER_SET => '⏰',
            self::ACTION_AI_ANALYZED => '🤖',
            self::ACTION_PRIORITY_SET => '🔥',
            self::ACTION_RATED => '⭐',
            self::ACTION_LOGIN => '🔑',
            self::ACTION_LOGOUT => '🚪',
            default => '📝',
        };
    }

    /**
     * Получить цвет для Filament.
     */
    public function getColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_STATUS_CHANGED => 'warning',
            self::ACTION_MANAGER_ASSIGNED => 'info',
            self::ACTION_VIEWED => 'gray',
            self::ACTION_COMMENT_ADDED => 'primary',
            self::ACTION_RATED => 'success',
            self::ACTION_PRIORITY_SET => 'danger',
            default => 'gray',
        };
    }

    /**
     * Получить короткое описание для виджета.
     */
    public function getShortDescriptionAttribute(): string
    {
        $userName = $this->user?->name ?? 'Система';
        $dealInfo = $this->deal ? "#{$this->deal->id}" : '';
        $clientName = $this->deal?->contact?->name ?? '';

        return match ($this->action) {
            self::ACTION_VIEWED => "{$userName} открыл {$dealInfo} ({$clientName})",
            self::ACTION_STATUS_CHANGED => "{$userName} изменил статус {$dealInfo}",
            self::ACTION_MANAGER_ASSIGNED => "{$userName} назначен на {$dealInfo}",
            self::ACTION_COMMENT_ADDED => "{$userName} добавил комментарий к {$dealInfo}",
            self::ACTION_CREATED => "Создана сделка {$dealInfo} ({$clientName})",
            self::ACTION_RATED => "AI оценил {$dealInfo}",
            self::ACTION_LOGIN => "{$userName} вошёл в систему",
            default => $this->description,
        };
    }
}
