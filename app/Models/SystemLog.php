<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'level',
        'action',
        'message',
        'context',
        'ip_address',
        'user_id',
        'deal_id',
    ];

    protected $casts = [
        'context' => 'json',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Static Helper Methods
    // ─────────────────────────────────────────────────────────────

    public static function log(string $service, string $level, string $message, array $context = [], ?int $userId = null, ?int $dealId = null): self
    {
        return static::create([
            'service' => $service,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip_address' => request()->ip(),
            'user_id' => $userId ?? auth()->id(),
            'deal_id' => $dealId,
        ]);
    }

    public static function info(string $service, string $message, array $context = []): self
    {
        return static::log($service, 'info', $message, $context);
    }

    public static function warning(string $service, string $message, array $context = []): self
    {
        return static::log($service, 'warning', $message, $context);
    }

    public static function error(string $service, string $message, array $context = []): self
    {
        return static::log($service, 'error', $message, $context);
    }

    public static function critical(string $service, string $message, array $context = []): self
    {
        return static::log($service, 'critical', $message, $context);
    }

    // Service-specific shortcuts
    public static function bot(string $level, string $message, array $context = []): self
    {
        return static::log('telegram', $level, $message, $context);
    }

    public static function meta(string $level, string $message, array $context = []): self
    {
        return static::log('meta', $level, $message, $context);
    }

    public static function api(string $level, string $message, array $context = []): self
    {
        return static::log('api', $level, $message, $context);
    }

    public static function queue(string $level, string $message, array $context = []): self
    {
        return static::log('queue', $level, $message, $context);
    }

    public static function ai(string $level, string $message, array $context = []): self
    {
        return static::log('ai', $level, $message, $context);
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }

    public function scopeLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeErrors($query)
    {
        return $query->whereIn('level', ['error', 'critical']);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    public function getLevelColorAttribute(): string
    {
        return match ($this->level) {
            'debug' => 'gray',
            'info' => 'primary',
            'warning' => 'warning',
            'error' => 'danger',
            'critical' => 'danger',
            default => 'gray',
        };
    }

    public function getServiceIconAttribute(): string
    {
        return match ($this->service) {
            'telegram' => '🤖',
            'meta' => '📘',
            'api' => '🔗',
            'queue' => '📨',
            'db' => '🗄️',
            default => '📝',
        };
    }
}
