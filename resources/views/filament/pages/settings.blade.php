<x-filament-panels::page>
    {{-- Статусы интеграций --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        {{-- Meta API Status --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 border border-slate-700/50 p-6 shadow-xl">
            <div class="absolute top-0 right-0 w-20 h-20 bg-blue-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                    <x-heroicon-o-chat-bubble-left-right class="w-7 h-7 text-white" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-white truncate">Meta Business</h3>
                    @if(\App\Models\Setting::get('meta_access_token'))
                        <div class="flex items-center gap-2 mt-1">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <span class="text-sm text-emerald-400 font-medium">Подключено</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 mt-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            <span class="text-sm text-amber-400">Настройте</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Telegram Status --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 border border-slate-700/50 p-6 shadow-xl">
            <div class="absolute top-0 right-0 w-20 h-20 bg-sky-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-sky-500 to-sky-600 flex items-center justify-center shadow-lg shadow-sky-500/25">
                    <x-heroicon-o-paper-airplane class="w-7 h-7 text-white" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-white truncate">Telegram Bot</h3>
                    @if(\App\Models\Setting::get('telegram_bot_token'))
                        <div class="flex items-center gap-2 mt-1">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <span class="text-sm text-emerald-400 font-medium">Подключено</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 mt-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            <span class="text-sm text-amber-400">Настройте</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI Status --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 border border-slate-700/50 p-6 shadow-xl">
            <div class="absolute top-0 right-0 w-20 h-20 bg-violet-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-500/25">
                    <x-heroicon-o-sparkles class="w-7 h-7 text-white" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-white truncate">AI Gemini</h3>
                    @php
                        $aiKey = \App\Models\Setting::get('gemini_api_key');
                        $aiEnabled = \App\Models\Setting::get('ai_enabled');
                        $aiEnabled = $aiEnabled === true || $aiEnabled === 'true' || $aiEnabled === '1';
                    @endphp
                    @if($aiKey && $aiEnabled)
                        <div class="flex items-center gap-2 mt-1">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <span class="text-sm text-emerald-400 font-medium">Активен</span>
                        </div>
                    @elseif($aiKey)
                        <div class="flex items-center gap-2 mt-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            <span class="text-sm text-amber-400">Выключен</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 mt-1">
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span>
                            <span class="text-sm text-slate-400">Не настроен</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Webhook URLs --}}
    <div class="rounded-2xl bg-gradient-to-r from-indigo-900/30 to-slate-900/50 border border-indigo-500/20 p-6 mb-8">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center">
                <x-heroicon-o-link class="w-5 h-5 text-indigo-400" />
            </div>
            <div>
                <h3 class="text-base font-bold text-white">Webhook URLs</h3>
                <p class="text-xs text-slate-400">Используйте эти адреса для настройки вебхуков</p>
            </div>
        </div>
        
        <div class="space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 p-3 rounded-xl bg-slate-900/50 border border-slate-700/50">
                <span class="text-sm text-slate-400 sm:w-32 flex-shrink-0">Meta:</span>
                <code class="flex-1 px-3 py-2 rounded-lg bg-slate-800 text-indigo-300 font-mono text-sm select-all break-all">{{ url('/api/webhooks/meta') }}</code>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 p-3 rounded-xl bg-slate-900/50 border border-slate-700/50">
                <span class="text-sm text-slate-400 sm:w-32 flex-shrink-0">Telegram:</span>
                <code class="flex-1 px-3 py-2 rounded-lg bg-slate-800 text-indigo-300 font-mono text-sm select-all break-all">{{ url('/api/webhooks/telegram') }}</code>
            </div>
        </div>
        
        <p class="text-xs text-slate-500 mt-4 flex items-center gap-2">
            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500" />
            Webhooks требуют HTTPS. Используйте ngrok для локальной разработки.
        </p>
    </div>

    {{-- Форма настроек --}}
    <div class="rounded-2xl bg-slate-800/50 border border-slate-700/50 p-6 mb-8">
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    {{-- Security Warning --}}
    <div class="rounded-2xl bg-gradient-to-r from-amber-900/20 to-orange-900/20 border border-amber-500/30 p-6">
        <div class="flex gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center flex-shrink-0 shadow-lg shadow-amber-500/25">
                <x-heroicon-o-shield-exclamation class="w-6 h-6 text-white" />
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-base font-bold text-amber-300 mb-3">Безопасность Meta API</h4>
                <div class="space-y-2">
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-amber-500/5 border border-amber-500/10">
                        <span class="text-amber-400 font-bold">⏰</span>
                        <div>
                            <span class="text-sm font-semibold text-amber-200">24-часовое окно</span>
                            <p class="text-xs text-amber-200/70 mt-0.5">Сообщения можно отправлять только в течение 24ч после последнего сообщения клиента</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-amber-500/5 border border-amber-500/10">
                        <span class="text-amber-400 font-bold">🏷️</span>
                        <div>
                            <span class="text-sm font-semibold text-amber-200">Message Tags</span>
                            <p class="text-xs text-amber-200/70 mt-0.5">Для отправки вне окна необходимо использовать специальные теги</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-red-500/10 border border-red-500/20">
                        <span class="text-red-400 font-bold">⛔</span>
                        <div>
                            <span class="text-sm font-semibold text-red-300">Anti-Marketing</span>
                            <p class="text-xs text-red-200/70 mt-0.5">Маркетинговые сообщения через теги ЗАПРЕЩЕНЫ — риск бана 100%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
