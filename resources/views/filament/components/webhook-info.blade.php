<div class="space-y-4">
    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-200/60 dark:border-slate-700/60">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
                <x-heroicon-o-link class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
            </div>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Webhook URL</span>
        </div>
        <code class="block w-full px-3 py-2.5 bg-white dark:bg-slate-900 rounded-lg text-sm font-mono text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 select-all">
            {{ url('/api/webhooks/meta') }}
        </code>
    </div>
    
    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-200/60 dark:border-slate-700/60">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center">
                <x-heroicon-o-bell class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Подписка на события</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">messages</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">messaging_postbacks</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">messaging_optins</span>
        </div>
    </div>
</div>
