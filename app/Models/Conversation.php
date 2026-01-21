<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'updated_time',
        'platform',
        'page_id',
        'labels',
        'link',
    ];

    /**
     * Получить атрибуты, которые должны быть приведены.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'updated_time' => 'datetime',
            'labels' => 'array',
        ];
    }

    /**
     * Получить ссылку на Meta Business Suite.
     *
     * Корректная ссылка для FB Messenger и Instagram.
     */
    public function getMetaBusinessSuiteUrlAttribute(): ?string
    {
        if (!$this->page_id || !$this->conversation_id) {
            return $this->link; // Fallback на старую ссылку
        }

        $baseUrl = 'https://business.facebook.com/latest/inbox/all';
        $params = [
            'asset_id' => $this->page_id,
            'selected_item_id' => $this->conversation_id,
        ];

        if ($this->platform === 'instagram') {
            $params['mailbox_id'] = 'instagram';
        }

        return $baseUrl.'?'.http_build_query($params);
    }

    /**
     * Получить отформатированные лейблы для отображения.
     */
    public function getFormattedLabelsAttribute(): array
    {
        if (empty($this->labels)) {
            return [];
        }

        return collect($this->labels)->map(function ($label) {
            return [
                'name' => $label['name'] ?? $label,
                'color' => $this->getLabelColor($label['name'] ?? $label),
            ];
        })->toArray();
    }

    /**
     * Определить цвет лейбла по названию.
     */
    protected function getLabelColor(string $name): string
    {
        // Стандартные цвета Meta Business Suite
        $colors = [
            'Follow Up' => '#f59e0b',     // amber
            'Important' => '#ef4444',      // red
            'Spam' => '#6b7280',           // gray
            'New Customer' => '#22c55e',   // green
            'VIP' => '#a855f7',            // purple
            'Urgent' => '#f97316',         // orange
        ];

        return $colors[$name] ?? '#6366f1'; // indigo по умолчанию
    }

    /**
     * Отношение: беседа имеет много сделок.
     */
    public function deals()
    {
        return $this->hasMany(Deal::class);
    }
}
