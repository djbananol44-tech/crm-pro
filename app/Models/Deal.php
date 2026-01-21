<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'conversation_id',
        'manager_id',
        'status',
        'comment',
        'reminder_at',
        'ai_summary',
        'ai_summary_at',
        'ai_score',
        'ai_intent',
        'ai_objections',
        'ai_next_action',
        'analysis_failed_at',
        'is_viewed',
        'is_priority',
        'last_client_message_at',
        'last_manager_response_at',
        'last_message_text',
        'manager_rating',
        'manager_review',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'reminder_at' => 'datetime',
            'ai_summary_at' => 'datetime',
            'analysis_failed_at' => 'datetime',
            'last_client_message_at' => 'datetime',
            'last_manager_response_at' => 'datetime',
            'rated_at' => 'datetime',
            'is_viewed' => 'boolean',
            'is_priority' => 'boolean',
            'ai_score' => 'integer',
            'ai_objections' => 'array',
            'manager_rating' => 'integer',
        ];
    }

    // === Ключевые слова для приоритизации ===
    const PRIORITY_KEYWORDS = [
        'цена', 'сколько', 'стоит', 'стоимость', 'купить', 'куплю',
        'прайс', 'доставка', 'оплата', 'заказать', 'заказ', 'оформить',
        'скидка', 'акция', 'срочно', 'быстро', 'сегодня',
        'price', 'buy', 'order', 'delivery', 'cost',
    ];

    /**
     * Проверить, содержит ли текст приоритетные ключевые слова.
     */
    public static function containsPriorityKeywords(string $text): bool
    {
        $textLower = mb_strtolower($text);
        foreach (self::PRIORITY_KEYWORDS as $keyword) {
            if (mb_strpos($textLower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Найти ключевые слова в тексте.
     */
    public static function findPriorityKeywords(string $text): array
    {
        $found = [];
        $textLower = mb_strtolower($text);
        foreach (self::PRIORITY_KEYWORDS as $keyword) {
            if (mb_strpos($textLower, $keyword) !== false) {
                $found[] = $keyword;
            }
        }

        return $found;
    }

    /**
     * Проверить, нужен ли новый AI-анализ.
     */
    public function needsAiAnalysis(): bool
    {
        if (empty($this->ai_summary)) {
            return true;
        }

        if ($this->ai_summary_at && $this->conversation) {
            $conversationTime = $this->conversation->updated_time;
            if ($conversationTime && $conversationTime->gt($this->ai_summary_at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверить, просрочен ли SLA (30 минут).
     */
    public function isSlaOverdue(): bool
    {
        if (!$this->last_client_message_at || $this->status === 'Closed') {
            return false;
        }

        if ($this->last_manager_response_at &&
            $this->last_manager_response_at->gte($this->last_client_message_at)) {
            return false;
        }

        return $this->last_client_message_at->diffInMinutes(now()) >= 30;
    }

    public function getSlaOverdueMinutes(): int
    {
        if (!$this->isSlaOverdue()) {
            return 0;
        }

        if ($this->last_manager_response_at &&
            $this->last_manager_response_at->gte($this->last_client_message_at)) {
            return 0;
        }

        return max(0, $this->last_client_message_at->diffInMinutes(now()) - 30);
    }

    public function isHotLead(): bool
    {
        return $this->ai_score !== null && $this->ai_score > 80;
    }

    public function markManagerResponse(): void
    {
        $this->update(['last_manager_response_at' => now()]);
    }

    /**
     * Проверить, нужна ли оценка работы менеджера.
     */
    public function needsRating(): bool
    {
        return $this->status === 'Closed' && $this->manager_rating === null;
    }

    // === Отношения ===

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class)->orderBy('created_at', 'desc');
    }

    public function canChangeManager(?User $user = null): bool
    {
        if ($this->manager_id === null) {
            return true;
        }
        if ($user && $user->isAdmin()) {
            return true;
        }
        if ($user && $user->isManager() && $this->manager_id !== null) {
            return false;
        }

        return false;
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($deal) {
            if ($deal->isDirty('manager_id') && $deal->getOriginal('manager_id') !== null) {
                $user = auth()->user();

                if ($user && $user->isAdmin()) {
                    return true;
                }

                if ($user && $user->isManager()) {
                    $deal->manager_id = $deal->getOriginal('manager_id');
                }
            }

            if ($deal->isDirty(['comment', 'status'])) {
                $deal->last_manager_response_at = now();
            }
        });
    }
}
