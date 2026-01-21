<?php

namespace App\Observers;

use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Support\Facades\Log;

/**
 * Observer для Contact.
 *
 * При изменении имени контакта обновляет search_vector во всех связанных deals.
 * Это дополняет триггер БД, который обновляет search_vector только при изменении deal.
 */
class ContactObserver
{
    /**
     * Handle the Contact "updated" event.
     */
    public function updated(Contact $contact): void
    {
        // Проверяем, изменились ли поля, влияющие на поиск
        if (!$contact->wasChanged(['name', 'first_name', 'last_name', 'psid'])) {
            return;
        }

        // Обновляем search_vector во всех связанных deals
        // Используем UPDATE с подзапросом чтобы триггер сработал
        try {
            Deal::where('contact_id', $contact->id)
                ->update([
                    'updated_at' => now(), // Триггер сработает на UPDATE
                ]);

            Log::info('ContactObserver: Обновлен search_vector для deals контакта', [
                'contact_id' => $contact->id,
                'deals_count' => Deal::where('contact_id', $contact->id)->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('ContactObserver: Ошибка обновления search_vector', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
