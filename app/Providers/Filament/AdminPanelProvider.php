<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            // ═══════════════════════════════════════════════════════════════════
            // Brand Colors: Onyx Black + Orange
            // Unified with Manager UI (React)
            // ═══════════════════════════════════════════════════════════════════
            ->colors([
                'primary' => Color::Orange,
                'gray' => Color::Zinc,
                'danger' => Color::Rose,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            // Dark theme forced via JS
            ->darkMode()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        localStorage.setItem("theme", "dark");
                        document.documentElement.classList.add("dark");
                    </script>
                    <style>
                        /* ═══════════════════════════════════════════════════════════════
                           JGGL CRM — Filament Theme Overrides
                           Onyx Black + Orange — Unified with Manager UI
                           ═══════════════════════════════════════════════════════════════ */
                        
                        /* CSS Variables */
                        :root {
                            --onyx: #0b0f14;
                            --surface: #10151c;
                            --surface-2: #161c24;
                            --fg: #ebeef3;
                            --fg-muted: #9aa3af;
                            --line: rgba(255,255,255,0.08);
                            --line-subtle: rgba(255,255,255,0.04);
                            --orange: #ff7a00;
                            --orange-2: #ff9933;
                        }
                        
                        /* Global border reset */
                        .dark *, .dark *::before, .dark *::after {
                            border-color: var(--line);
                        }
                        
                        /* Main Layout */
                        .fi-body { background: var(--onyx) !important; color: var(--fg) !important; }
                        .fi-layout { background: var(--onyx) !important; }
                        .fi-main { background: var(--onyx) !important; }
                        .fi-main-ctn { background: var(--onyx) !important; }
                        
                        /* Sidebar */
                        .fi-sidebar {
                            background: var(--surface) !important;
                            border-right: 1px solid var(--line-subtle) !important;
                        }
                        .fi-sidebar-nav-groups { background: transparent !important; }
                        .fi-sidebar-item { color: var(--fg-muted) !important; }
                        .fi-sidebar-item:hover { background: rgba(255,255,255,0.05) !important; color: var(--fg) !important; }
                        .fi-sidebar-item-active, .fi-sidebar-item.fi-active {
                            background: rgba(255,122,0,0.15) !important;
                            color: var(--orange) !important;
                        }
                        
                        /* Topbar */
                        .fi-topbar {
                            background: var(--surface) !important;
                            backdrop-filter: blur(20px) !important;
                            border-bottom: 1px solid var(--line-subtle) !important;
                        }
                        
                        /* Cards & Sections */
                        .fi-section, .fi-section-content, .fi-card, .fi-wi, 
                        .fi-wi-stats-overview-stat, .fi-ta, .fi-fo, .fi-in {
                            background: var(--surface) !important;
                            border-color: var(--line) !important;
                            border-radius: 0.75rem !important;
                        }
                        
                        /* Table */
                        .fi-ta-header, .fi-ta-header-cell { 
                            background: var(--surface) !important; 
                            color: var(--fg-muted) !important;
                            border-color: var(--line) !important;
                        }
                        .fi-ta-row { background: var(--surface) !important; border-color: var(--line-subtle) !important; }
                        .fi-ta-row:hover { background: var(--surface-2) !important; }
                        .fi-ta-cell { color: var(--fg) !important; border-color: var(--line-subtle) !important; }
                        
                        /* Inputs */
                        .fi-input, .fi-select, .fi-textarea,
                        [class*="fi-fo-"] input, [class*="fi-fo-"] select, [class*="fi-fo-"] textarea,
                        input[type="text"], input[type="email"], input[type="password"], input[type="number"],
                        select, textarea {
                            min-height: 2.75rem !important;
                            background: var(--onyx) !important;
                            border: 1px solid var(--line) !important;
                            border-radius: 0.5rem !important;
                            color: var(--fg) !important;
                        }
                        .fi-input:focus, .fi-select:focus, .fi-textarea:focus,
                        [class*="fi-fo-"] input:focus, [class*="fi-fo-"] select:focus, [class*="fi-fo-"] textarea:focus,
                        input:focus, select:focus, textarea:focus {
                            background: var(--surface) !important;
                            border-color: var(--orange) !important;
                            box-shadow: 0 0 0 3px rgba(255,122,0,0.25) !important;
                            outline: none !important;
                        }
                        ::placeholder { color: var(--fg-muted) !important; }
                        
                        /* Buttons */
                        .fi-btn { border-radius: 0.5rem !important; min-height: 2.75rem !important; }
                        .fi-btn-color-primary, button[type="submit"].fi-btn {
                            background: var(--orange) !important;
                            color: var(--onyx) !important;
                            border-color: transparent !important;
                        }
                        .fi-btn-color-primary:hover, button[type="submit"].fi-btn:hover {
                            background: var(--orange-2) !important;
                        }
                        .fi-btn-color-gray { 
                            background: var(--surface) !important; 
                            color: var(--fg) !important;
                            border-color: var(--line) !important;
                        }
                        .fi-btn-color-gray:hover { background: var(--surface-2) !important; }
                        
                        /* Modal & Dropdown */
                        .fi-modal, .fi-modal-window { 
                            background: var(--surface) !important; 
                            border: 1px solid var(--line) !important;
                            border-radius: 0.75rem !important;
                        }
                        .fi-dropdown-panel, .fi-dropdown-list {
                            background: var(--surface) !important;
                            border: 1px solid var(--line) !important;
                            border-radius: 0.5rem !important;
                        }
                        .fi-dropdown-list-item { color: var(--fg) !important; }
                        .fi-dropdown-list-item:hover { background: var(--surface-2) !important; }
                        
                        /* Focus Ring */
                        *:focus-visible {
                            outline: none !important;
                            box-shadow: 0 0 0 2px var(--onyx), 0 0 0 4px var(--orange) !important;
                        }
                        
                        /* Pagination */
                        .fi-pagination-item { 
                            background: var(--surface) !important;
                            border-color: var(--line) !important;
                            color: var(--fg) !important;
                        }
                        .fi-pagination-item:hover { background: var(--surface-2) !important; }
                        .fi-pagination-item.fi-active { background: var(--orange) !important; color: var(--onyx) !important; }
                        
                        /* Badges */
                        .fi-badge { border-radius: 9999px !important; border-color: var(--line) !important; }
                        
                        /* Scrollbar */
                        ::-webkit-scrollbar { width: 6px; height: 6px; }
                        ::-webkit-scrollbar-track { background: transparent; }
                        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
                        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
                        
                        /* Selection */
                        ::selection { background: var(--orange) !important; color: var(--onyx) !important; }
                        
                        /* Typography */
                        h1, h2, h3, h4, h5, h6, .fi-header-heading { color: var(--fg) !important; }
                        .fi-header-subheading { color: var(--fg-muted) !important; }
                        
                        /* Notifications */
                        .fi-notification { background: var(--surface) !important; border-color: var(--line) !important; }
                    </style>
                    HTML
                )
            )
            // ═══════════════════════════════════════════════════════════════════
            // Branding
            // ═══════════════════════════════════════════════════════════════════
            ->brandName('JGGL CRM')
            ->favicon(asset('favicon.ico'))
            // Interface
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            // Font Inter
            ->font('Inter')
            // Database notifications
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Resources
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            // Widgets
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
            // Navigation
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
            // Global search
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // SPA mode
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
