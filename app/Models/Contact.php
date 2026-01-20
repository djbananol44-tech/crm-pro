<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'psid',
        'first_name',
        'last_name',
        'name',
    ];

    /**
     * Отношение: контакт имеет много сделок.
     */
    public function deals()
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * Получить полное имя контакта.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $parts = array_filter([$this->first_name, $this->last_name]);
        return implode(' ', $parts) ?: 'Без имени';
    }
}
