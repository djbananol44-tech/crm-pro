<div class="rounded-xl bg-slate-900/50 border border-sky-500/20 p-5 space-y-5">
    {{-- Как подключить --}}
    <div>
        <h4 class="text-sm font-bold text-sky-300 mb-3 flex items-center gap-2">
            <span>📱</span> Как подключить Telegram
        </h4>
        <ol class="text-sm text-slate-300 space-y-2">
            <li class="flex items-start gap-2">
                <span class="w-5 h-5 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                <span>Создайте бота через <a href="https://t.me/BotFather" target="_blank" class="text-sky-400 hover:text-sky-300 underline">@BotFather</a></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="w-5 h-5 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                <span>Скопируйте токен и вставьте в поле выше</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="w-5 h-5 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                <span>Нажмите кнопку <b class="text-sky-300">"Webhook TG"</b></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="w-5 h-5 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>
                <span>Укажите <code class="px-1.5 py-0.5 bg-slate-800 rounded text-sky-300 text-xs">telegram_chat_id</code> в профиле менеджера</span>
            </li>
        </ol>
    </div>

    {{-- Команды --}}
    <div class="pt-4 border-t border-slate-700/50">
        <h4 class="text-sm font-bold text-sky-300 mb-3 flex items-center gap-2">
            <span>🤖</span> Команды бота
        </h4>
        <div class="grid grid-cols-1 gap-2">
            <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/50">
                <code class="px-2 py-1 bg-slate-700 rounded text-sky-300 text-xs font-mono">/start</code>
                <span class="text-xs text-slate-400">— приветствие и Chat ID</span>
            </div>
            <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/50">
                <code class="px-2 py-1 bg-slate-700 rounded text-sky-300 text-xs font-mono">/me</code>
                <span class="text-xs text-slate-400">— активные сделки</span>
            </div>
            <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/50">
                <code class="px-2 py-1 bg-slate-700 rounded text-sky-300 text-xs font-mono">/help</code>
                <span class="text-xs text-slate-400">— справка</span>
            </div>
        </div>
    </div>

    {{-- Inline кнопки --}}
    <div class="pt-4 border-t border-slate-700/50">
        <h4 class="text-sm font-bold text-sky-300 mb-3 flex items-center gap-2">
            <span>🔘</span> Inline-кнопки
        </h4>
        <div class="flex flex-wrap gap-2">
            <span class="px-3 py-1.5 rounded-lg bg-indigo-500/20 text-indigo-300 text-xs font-medium">🚀 В работу</span>
            <span class="px-3 py-1.5 rounded-lg bg-violet-500/20 text-violet-300 text-xs font-medium">🤖 AI Анализ</span>
            <span class="px-3 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-300 text-xs font-medium">✅ Завершить</span>
            <span class="px-3 py-1.5 rounded-lg bg-slate-500/20 text-slate-300 text-xs font-medium">🔗 Открыть</span>
        </div>
    </div>
</div>
