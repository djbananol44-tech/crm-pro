<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogManagerActivity
{
    /**
     * Обновляет last_activity_at при каждом запросе авторизованного пользователя.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            // Обновляем время активности (не чаще чем раз в минуту для оптимизации)
            if (!$user->last_activity_at || $user->last_activity_at->diffInMinutes(now()) >= 1) {
                $user->update(['last_activity_at' => now()]);
            }
        }

        return $next($request);
    }
}
