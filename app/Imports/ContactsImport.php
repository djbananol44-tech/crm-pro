<?php

namespace App\Imports;

use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithValidation;

class ContactsImport implements SkipsEmptyRows, ToModel, WithBatchInserts, WithHeadingRow, WithUpserts, WithValidation
{
    /**
     * Счётчики для отчёта.
     */
    protected int $created = 0;

    protected int $updated = 0;

    /**
     * Создание модели из строки CSV.
     */
    public function model(array $row): ?Contact
    {
        // Проверяем существование контакта
        $exists = Contact::where('psid', $row['psid'])->exists();

        if ($exists) {
            $this->updated++;
        } else {
            $this->created++;
        }

        Log::info('ContactsImport: Обработка строки', [
            'psid' => $row['psid'],
            'name' => $row['first_name'] ?? ''.' '.$row['last_name'] ?? '',
            'action' => $exists ? 'обновление' : 'создание',
        ]);

        return new Contact([
            'psid' => $row['psid'],
            'first_name' => $row['first_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: null,
        ]);
    }

    /**
     * Уникальный ключ для upsert.
     */
    public function uniqueBy(): string|array
    {
        return 'psid';
    }

    /**
     * Правила валидации.
     */
    public function rules(): array
    {
        return [
            'psid' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ];
    }

    /**
     * Кастомные сообщения валидации на русском.
     */
    public function customValidationMessages(): array
    {
        return [
            'psid.required' => 'Поле PSID обязательно для заполнения в строке :attribute.',
            'psid.string' => 'Поле PSID должно быть строкой в строке :attribute.',
            'psid.max' => 'Поле PSID не должно превышать 255 символов в строке :attribute.',
            'first_name.string' => 'Поле Имя должно быть строкой в строке :attribute.',
            'first_name.max' => 'Поле Имя не должно превышать 255 символов в строке :attribute.',
            'last_name.string' => 'Поле Фамилия должно быть строкой в строке :attribute.',
            'last_name.max' => 'Поле Фамилия не должно превышать 255 символов в строке :attribute.',
        ];
    }

    /**
     * Размер пакета для вставки.
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Получить количество созданных записей.
     */
    public function getCreatedCount(): int
    {
        return $this->created;
    }

    /**
     * Получить количество обновлённых записей.
     */
    public function getUpdatedCount(): int
    {
        return $this->updated;
    }
}
