<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Политика доступа к сделкам.
 *
 * Правила:
 * - Admin: полный доступ ко всем сделкам
 * - Manager: доступ только к своим сделкам (manager_id == user.id)
 * - Manager может взять неназначенную сделку (manager_id == null)
 */
class DealPolicy
{
    use HandlesAuthorization;

    /**
     * Админ имеет полный доступ ко всему.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Просмотр списка сделок.
     */
    public function viewAny(User $user): bool
    {
        return true; // Все авторизованные пользователи могут видеть список
    }

    /**
     * Просмотр конкретной сделки.
     *
     * Manager может видеть:
     * - Свою сделку (manager_id == user.id)
     * - Неназначенную сделку (manager_id == null)
     */
    public function view(User $user, Deal $deal): bool
    {
        if ($user->isManager()) {
            return $deal->manager_id === $user->id || $deal->manager_id === null;
        }

        return false;
    }

    /**
     * Создание новой сделки.
     */
    public function create(User $user): bool
    {
        return true; // Все могут создавать через систему
    }

    /**
     * Обновление сделки.
     *
     * Manager может обновлять только свою сделку.
     */
    public function update(User $user, Deal $deal): bool
    {
        if ($user->isManager()) {
            return $deal->manager_id === $user->id || $deal->manager_id === null;
        }

        return false;
    }

    /**
     * Закрытие сделки.
     *
     * Manager может закрыть только свою сделку.
     */
    public function close(User $user, Deal $deal): bool
    {
        if ($user->isManager()) {
            return $deal->manager_id === $user->id;
        }

        return false;
    }

    /**
     * Назначение менеджера на сделку.
     *
     * Только админ может переназначать менеджеров.
     */
    public function assign(User $user, Deal $deal): bool
    {
        // Admin обрабатывается в before()
        return false;
    }

    /**
     * Взять сделку себе (assignToMe).
     *
     * Manager может взять только неназначенную сделку.
     */
    public function assignToMe(User $user, Deal $deal): bool
    {
        if ($user->isManager()) {
            return $deal->manager_id === null;
        }

        return false;
    }

    /**
     * Удаление сделки.
     *
     * Только админ может удалять.
     */
    public function delete(User $user, Deal $deal): bool
    {
        return false; // Admin обрабатывается в before()
    }

    /**
     * Экспорт сделок.
     *
     * Все авторизованные пользователи могут экспортировать
     * (но получат только свои данные согласно фильтрам).
     */
    public function export(User $user): bool
    {
        return true;
    }

    /**
     * Запрос AI-анализа.
     */
    public function requestAiAnalysis(User $user, Deal $deal): bool
    {
        if ($user->isManager()) {
            return $deal->manager_id === $user->id || $deal->manager_id === null;
        }

        return false;
    }

    /**
     * Перевод сообщений.
     */
    public function translate(User $user, Deal $deal): bool
    {
        return $this->view($user, $deal);
    }
}
