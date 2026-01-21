<?php

namespace App\Providers;

use App\Models\Deal;
use App\Policies\DealPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Политики модели для приложения.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Deal::class => DealPolicy::class,
    ];

    /**
     * Регистрация любых сервисов аутентификации / авторизации.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Гейт для экспорта
        Gate::define('export-deals', function ($user) {
            return true; // Все авторизованные могут экспортировать
        });

        // Гейт для просмотра отчётов
        Gate::define('view-reports', function ($user) {
            return $user->isAdmin() || $user->isManager();
        });

        // Гейт для полного доступа к отчётам (без фильтрации по manager_id)
        Gate::define('view-all-reports', function ($user) {
            return $user->isAdmin();
        });
    }
}
