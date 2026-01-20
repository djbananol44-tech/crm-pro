<div class="p-4 bg-sky-50 dark:bg-sky-900/20 rounded-xl border border-sky-200 dark:border-sky-800 space-y-4">
    <div>
        <h4 class="text-sm font-semibold text-sky-800 dark:text-sky-200 mb-2">📱 Как подключить Telegram</h4>
        <ol class="text-xs text-sky-700 dark:text-sky-300 space-y-1.5 list-decimal list-inside">
            <li>Создайте бота через <a href="https://t.me/BotFather" target="_blank" class="underline hover:text-sky-900">@BotFather</a></li>
            <li>Скопируйте токен и вставьте выше</li>
            <li>Нажмите кнопку <b>"Webhook TG"</b> для установки webhook</li>
            <li>У каждого менеджера в профиле должен быть указан <code class="px-1 py-0.5 bg-sky-100 dark:bg-sky-800 rounded">telegram_chat_id</code></li>
        </ol>
    </div>

    <div>
        <h4 class="text-sm font-semibold text-sky-800 dark:text-sky-200 mb-2">🤖 Команды бота</h4>
        <ul class="text-xs text-sky-700 dark:text-sky-300 space-y-1">
            <li><code class="px-1 py-0.5 bg-sky-100 dark:bg-sky-800 rounded">/start</code> — приветствие и получение Chat ID</li>
            <li><code class="px-1 py-0.5 bg-sky-100 dark:bg-sky-800 rounded">/me</code> — список активных сделок менеджера</li>
            <li><code class="px-1 py-0.5 bg-sky-100 dark:bg-sky-800 rounded">/help</code> — справка по боту</li>
        </ul>
    </div>

    <div>
        <h4 class="text-sm font-semibold text-sky-800 dark:text-sky-200 mb-2">🔘 Inline-кнопки в уведомлениях</h4>
        <ul class="text-xs text-sky-700 dark:text-sky-300 space-y-1">
            <li><b>🚀 В работу</b> — взять сделку себе</li>
            <li><b>🤖 AI Анализ</b> — получить анализ переписки</li>
            <li><b>✅ Завершить</b> — закрыть сделку</li>
            <li><b>🔗 Открыть в CRM</b> — ссылка на сделку</li>
        </ul>
    </div>

    @php
        $webhookUrl = url('/api/webhooks/telegram');
    @endphp
    <div class="pt-2 border-t border-sky-200 dark:border-sky-700">
        <p class="text-xs text-sky-600 dark:text-sky-400">
            <b>Webhook URL:</b> <code class="px-1 py-0.5 bg-sky-100 dark:bg-sky-800 rounded text-[10px]">{{ $webhookUrl }}</code>
        </p>
    </div>
</div>
