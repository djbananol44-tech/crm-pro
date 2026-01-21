<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\Navigation\NavigationGroup;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Премиальная цветовая схема Indigo
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            // Тёмная тема 
            ->darkMode(true)
            // Брендинг
            ->brandName('CRM Pro')
            ->favicon(asset('favicon.ico'))
            // Интерфейс
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            // Шрифт Inter
            ->font('Inter')
            // Database уведомления
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Ресурсы
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            // Виджеты
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\ControlCenterWidget::class,
                \App\Filament\Widgets\ApiStatusWidget::class,
                \App\Filament\Widgets\SystemHealthWidget::class,
                \App\Filament\Widgets\ManagerPresenceWidget::class,
                \App\Filament\Widgets\DealsStatsWidget::class,
                \App\Filament\Widgets\DealsByStatusChart::class,
                \App\Filament\Widgets\DealsByManagerChart::class,
                \App\Filament\Widgets\RecentDealsWidget::class,
                \App\Filament\Widgets\ManagerActivityWidget::class,
            ])
            // Навигация
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Управление')
                    ->icon('heroicon-o-cog-6-tooth'),
                NavigationGroup::make()
                    ->label('Данные')
                    ->icon('heroicon-o-circle-stack'),
                NavigationGroup::make()
                    ->label('Настройки')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsed(),
            ])
            // Глобальный поиск
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // SPA режим
            ->spa()
            // Middleware
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
