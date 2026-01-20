<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Carbon\Carbon;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telegram_chat_id',
        'last_activity_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_activity_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Проверить, онлайн ли пользователь (активность < 5 минут).
     */
    public function isOnline(): bool
    {
        if (!$this->last_activity_at) {
            return false;
        }

        return $this->last_activity_at->diffInMinutes(now()) < 5;
    }

    /**
     * Получить статус присутствия.
     */
    public function getPresenceStatus(): string
    {
        if (!$this->last_activity_at) {
            return 'Никогда не входил';
        }

        if ($this->isOnline()) {
            return 'В сети';
        }

        return 'Был ' . $this->last_activity_at->diffForHumans();
    }

    /**
     * Получить цвет статуса для Filament.
     */
    public function getPresenceColor(): string
    {
        return $this->isOnline() ? 'success' : 'gray';
    }

    /**
     * Обновить время последней активности.
     */
    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Проверить, подключен ли Telegram.
     */
    public function hasTelegram(): bool
    {
        return !empty($this->telegram_chat_id);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'manager_id');
    }

    /**
     * Логи активности пользователя.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Получить количество активных сделок.
     */
    public function activeDealsCount(): int
    {
        return $this->deals()->whereIn('status', ['New', 'In Progress'])->count();
    }

    /**
     * Получить среднюю оценку менеджера.
     */
    public function getAverageRating(): ?float
    {
        $avg = $this->deals()
            ->whereNotNull('manager_rating')
            ->avg('manager_rating');

        return $avg ? round($avg, 1) : null;
    }

    /**
     * Получить статистику за сегодня.
     */
    public function getTodayStats(): array
    {
        $today = Carbon::today();

        return [
            'views' => ActivityLog::where('user_id', $this->id)
                ->where('action', ActivityLog::ACTION_VIEWED)
                ->whereDate('created_at', $today)
                ->count(),
            'status_changes' => ActivityLog::where('user_id', $this->id)
                ->where('action', ActivityLog::ACTION_STATUS_CHANGED)
                ->whereDate('created_at', $today)
                ->count(),
            'closed_deals' => $this->deals()
                ->where('status', 'Closed')
                ->whereDate('updated_at', $today)
                ->count(),
        ];
    }
}
