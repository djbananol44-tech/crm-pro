<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogManagerActivity
{
    /**
     * Обновляет last_activity_at при каждом запросе авторизованного пользователя.
     * Исключает Livewire запросы для совместимости с Filament.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Пропускаем Livewire запросы (Filament использует Livewire)
        if ($request->hasHeader('X-Livewire') || $request->is('livewire/*')) {
            return $next($request);
        }

        try {
            if ($user = $request->user()) {
                // Обновляем время активности (не чаще чем раз в минуту для оптимизации)
                if (!$user->last_activity_at || $user->last_activity_at->diffInMinutes(now()) >= 1) {
                    $user->updateQuietly(['last_activity_at' => now()]);
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки — не критично для основного функционала
        }

        return $next($request);
    }
}
