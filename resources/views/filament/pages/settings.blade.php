<x-filament-panels::page>
    {{-- Статусы API --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Meta API Status --}}
        <div class="rounded-2xl bg-slate-800/50 border border-slate-700/50 p-5 shadow-lg backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                    <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 text-blue-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-slate-200">Meta Business Suite</h3>
                    @if(\App\Models\Setting::get('meta_access_token'))
                        <p class="text-xs text-emerald-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Настроен
                        </p>
                    @else
                        <p class="text-xs text-amber-400">Требуется настройка</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Telegram Status --}}
        <div class="rounded-2xl bg-slate-800/50 border border-slate-700/50 p-5 shadow-lg backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-sky-500/20 flex items-center justify-center">
                    <x-heroicon-o-paper-airplane class="w-6 h-6 text-sky-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-slate-200">Telegram Bot</h3>
                    @if(\App\Models\Setting::get('telegram_bot_token'))
                        <p class="text-xs text-emerald-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Настроен
                        </p>
                    @else
                        <p class="text-xs text-amber-400">Требуется настройка</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI Status --}}
        <div class="rounded-2xl bg-slate-800/50 border border-slate-700/50 p-5 shadow-lg backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-violet-500/20 flex items-center justify-center">
                    <x-heroicon-o-sparkles class="w-6 h-6 text-violet-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-slate-200">AI Gemini</h3>
                    @if(\App\Models\Setting::get('gemini_api_key') && \App\Models\Setting::get('ai_enabled'))
                        <p class="text-xs text-emerald-400 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Активен
                        </p>
                    @elseif(\App\Models\Setting::get('gemini_api_key'))
                        <p class="text-xs text-amber-400">Настроен, но выключен</p>
                    @else
                        <p class="text-xs text-slate-400">Не настроен</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Webhook URLs Info --}}
    <div class="rounded-2xl bg-gradient-to-r from-slate-800/80 to-slate-900/80 border border-slate-700/50 p-5 mb-6 backdrop-blur-sm">
        <h3 class="text-sm font-semibold text-slate-200 mb-3 flex items-center gap-2">
            <x-heroicon-o-link class="w-4 h-4 text-indigo-400" />
            Webhook URLs
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-slate-400">Meta Webhook:</span>
                <code class="ml-2 px-2 py-1 rounded bg-slate-900/80 text-indigo-300 font-mono select-all border border-slate-700/50">
                    {{ url('/api/webhooks/meta') }}
                </code>
            </div>
            <div>
                <span class="text-slate-400">Telegram Webhook:</span>
                <code class="ml-2 px-2 py-1 rounded bg-slate-900/80 text-indigo-300 font-mono select-all border border-slate-700/50">
                    {{ url('/api/webhooks/telegram') }}
                </code>
            </div>
        </div>
        <p class="text-[10px] text-slate-500 mt-3">
            ⚠️ Webhooks требуют HTTPS. Используйте ngrok для локальной разработки.
        </p>
    </div>

    {{-- Form --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
    </x-filament-panels::form>

    {{-- Security Warning --}}
    <div class="mt-6 rounded-2xl bg-amber-500/10 border border-amber-500/30 p-5 backdrop-blur-sm">
        <div class="flex items-start gap-3">
            <x-heroicon-o-shield-exclamation class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" />
            <div>
                <h4 class="text-sm font-semibold text-amber-300">Безопасность Meta API</h4>
                <ul class="mt-2 text-xs text-amber-200/80 space-y-1">
                    <li>• <strong>24-часовое окно:</strong> Сообщения можно отправлять только в течение 24ч после последнего сообщения клиента</li>
                    <li>• <strong>Message Tags:</strong> Для отправки вне окна необходимо использовать специальные теги</li>
                    <li>• <strong>Anti-Marketing:</strong> Маркетинговые сообщения через теги ЗАПРЕЩЕНЫ (риск бана 100%)</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
