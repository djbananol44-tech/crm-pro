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
        ];
    }

    /**
     * Отношение: беседа имеет много сделок.
     */
    public function deals()
    {
        return $this->hasMany(Deal::class);
    }
}
