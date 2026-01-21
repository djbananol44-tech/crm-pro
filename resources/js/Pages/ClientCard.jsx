import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import { formatDistanceToNow, differenceInMinutes } from 'date-fns';
import { ru } from 'date-fns/locale';
import { 
    ArrowLeft, Copy, Check, User, Clock, RefreshCw, ExternalLink, 
    MessageSquare, CheckCircle, PhoneCall, Calendar, Save, 
    Trash2, Briefcase, Hourglass, Sparkles, Zap, Languages, Timer, AlertTriangle,
    ChevronRight, X, Send
} from 'lucide-react';
import MainLayout from '../Layouts/MainLayout';

// === Utilities ===
function formatRelativeTime(dateString) {
    if (!dateString) return '';
    try {
        return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale: ru });
    } catch { return ''; }
}

function isOnline(updatedAt) {
    if (!updatedAt) return false;
    try {
        return differenceInMinutes(new Date(), new Date(updatedAt)) < 10;
    } catch { return false; }
}

// === Hook for responsive detection ===
function useIsMobile() {
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const checkMobile = () => setIsMobile(window.innerWidth < 768);
        checkMobile();
        window.addEventListener('resize', checkMobile);
        return () => window.removeEventListener('resize', checkMobile);
    }, []);

    return isMobile;
}

// === Copy Button ===
function CopyButton({ text, className = '' }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try { await navigator.clipboard.writeText(text); } catch {}
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <button onClick={handleCopy} className={`p-2 rounded-lg transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center ${copied ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-zinc-500 hover:text-white hover:bg-white/10'} ${className}`}>
            {copied ? <Check className="w-4 h-4" strokeWidth={1.5} /> : <Copy className="w-4 h-4" strokeWidth={1.5} />}
        </button>
    );
}

// === Lead Score Badge ===
function LeadScoreBadge({ score }) {
    if (!score) return null;
    const isHot = score > 80;
    
    return (
        <div className={`ai-score ${isHot ? 'ai-score-hot' : ''}`}>
            {isHot && <Zap className="w-4 h-4 text-amber-400" strokeWidth={2} />}
            <span className={isHot ? 'text-amber-400' : 'text-violet-400'}>{score}</span>
        </div>
    );
}

// === SLA Warning ===
function SlaWarning({ isOverdue, minutes }) {
    if (!isOverdue) return null;
    
    return (
        <div className="flex items-center gap-3 px-4 md:px-5 py-3 md:py-4 mb-4 md:mb-6 rounded-xl md:rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 animate-in">
            <AlertTriangle className="w-5 h-5 flex-shrink-0" strokeWidth={1.5} />
            <div className="min-w-0">
                <p className="font-semibold text-sm">⚠️ Просрочка SLA!</p>
                <p className="text-xs text-amber-400/70 truncate">Клиент ждёт ответа уже {30 + (minutes || 0)} мин.</p>
            </div>
        </div>
    );
}

// === Contact Card ===
function ContactCard({ contact, conversation }) {
    const online = isOnline(conversation?.updated_time);

    return (
        <div className="glass-card-static overflow-hidden">
            <div className="p-4 md:p-6">
                <div className="flex items-center gap-3 md:gap-4">
                    <div className="relative flex-shrink-0">
                        <div className="w-12 h-12 md:w-16 md:h-16 rounded-xl md:rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                            <span className="text-white font-bold text-lg md:text-xl">{contact?.name?.charAt(0)?.toUpperCase() || '?'}</span>
                        </div>
                        {online && <div className="online-dot absolute -bottom-0.5 -right-0.5" />}
                    </div>
                    <div className="flex-1 min-w-0">
                        <h3 className="text-base md:text-lg font-bold text-white truncate">{contact?.name || 'Без имени'}</h3>
                        <div className="flex items-center gap-2 mt-1">
                            <code className="text-xs text-zinc-400 bg-white/5 px-2 py-1 rounded-lg font-mono truncate max-w-[120px] md:max-w-none">{contact?.psid}</code>
                            <CopyButton text={contact?.psid} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="divider" />

            <div className="p-4 md:p-6 space-y-3 md:space-y-4">
                {(contact?.first_name || contact?.last_name) && (
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-white/5 flex items-center justify-center flex-shrink-0">
                            <User className="w-4 h-4 md:w-5 md:h-5 text-zinc-400" strokeWidth={1.5} />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] md:text-xs text-zinc-500 font-medium uppercase tracking-wider">Полное имя</p>
                            <p className="text-sm text-zinc-200 truncate">{contact.first_name} {contact.last_name}</p>
                        </div>
                    </div>
                )}

                {conversation && (
                    <div className="flex items-center gap-3">
                        <div className={`w-9 h-9 md:w-10 md:h-10 rounded-lg md:rounded-xl flex items-center justify-center flex-shrink-0 ${conversation.platform === 'instagram' ? 'bg-gradient-to-br from-pink-500/20 to-violet-500/20' : 'bg-indigo-500/20'}`}>
                            <MessageSquare className="w-4 h-4 md:w-5 md:h-5 text-indigo-400" strokeWidth={1.5} />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] md:text-xs text-zinc-500 font-medium uppercase tracking-wider">Платформа</p>
                            <p className="text-sm text-zinc-200">{conversation.platform === 'instagram' ? 'Instagram' : 'Messenger'}</p>
                        </div>
                    </div>
                )}
            </div>

            {conversation?.link && (
                <>
                    <div className="divider" />
                    <div className="p-4 md:p-6">
                        <a href={conversation.link} target="_blank" rel="noopener noreferrer" className="btn-premium w-full justify-center">
                            <ExternalLink className="w-4 h-4" strokeWidth={1.5} /> Открыть в Meta
                        </a>
                    </div>
                </>
            )}
        </div>
    );
}

// === AI Card ===
function AiCard({ summary, score, summaryAt, dealId, aiEnabled }) {
    const [refreshing, setRefreshing] = useState(false);
    const [copied, setCopied] = useState(false);

    const handleRefresh = () => {
        setRefreshing(true);
        router.post(`/deals/${dealId}/refresh-ai`, {}, {
            preserveScroll: true,
            onFinish: () => setRefreshing(false),
        });
    };

    const handleCopy = async () => {
        if (!summary) return;
        try { await navigator.clipboard.writeText(summary); } catch {}
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    if (!aiEnabled) return null;

    const isHot = score && score > 80;

    return (
        <div className={`glass-card-static overflow-hidden ${summary ? 'ring-1 ring-violet-500/30' : ''}`}>
            <div className="p-4 md:p-6">
                <div className="flex items-center justify-between mb-3 md:mb-4">
                    <div className="flex items-center gap-2 md:gap-3">
                        <div className={`w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0 ${isHot ? 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/30' : 'bg-gradient-to-br from-violet-500 to-purple-600 shadow-violet-500/30'}`}>
                            {isHot ? <Zap className="w-5 h-5 md:w-6 md:h-6 text-white" strokeWidth={1.5} /> : <Sparkles className="w-5 h-5 md:w-6 md:h-6 text-white" strokeWidth={1.5} />}
                        </div>
                        <div className="min-w-0">
                            <h4 className="text-sm md:text-base font-bold text-white">AI-Анализ</h4>
                            {summaryAt && <p className="text-[10px] md:text-xs text-zinc-500 truncate">{summaryAt}</p>}
                        </div>
                    </div>
                    <div className="flex items-center gap-1">
                        {summary && (
                            <button onClick={handleCopy} className={`p-2 md:p-2.5 rounded-lg md:rounded-xl transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center ${copied ? 'bg-emerald-500/20 text-emerald-400' : 'hover:bg-white/5 text-zinc-500 hover:text-white'}`}>
                                {copied ? <Check className="w-4 h-4" strokeWidth={1.5} /> : <Copy className="w-4 h-4" strokeWidth={1.5} />}
                            </button>
                        )}
                        <button onClick={handleRefresh} disabled={refreshing} className="p-2 md:p-2.5 rounded-lg md:rounded-xl hover:bg-white/5 text-zinc-500 hover:text-white disabled:opacity-50 transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                            <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} strokeWidth={1.5} />
                        </button>
                    </div>
                </div>

                {score && <div className="mb-3 md:mb-4"><LeadScoreBadge score={score} /></div>}

                <div className="rounded-lg md:rounded-xl bg-white/5 border border-white/10 p-3 md:p-4">
                    {refreshing ? (
                        <div className="space-y-2 md:space-y-3">
                            <div className="skeleton h-3 md:h-4 w-3/4" />
                            <div className="skeleton h-3 md:h-4 w-full" />
                            <div className="skeleton h-3 md:h-4 w-5/6" />
                        </div>
                    ) : summary ? (
                        <p className="text-xs md:text-sm text-zinc-300 leading-relaxed whitespace-pre-wrap">{summary}</p>
                    ) : (
                        <div className="text-center py-4 md:py-6">
                            <Sparkles className="w-6 h-6 md:w-8 md:h-8 text-violet-500/50 mx-auto mb-2" strokeWidth={1.5} />
                            <p className="text-xs md:text-sm text-zinc-500">Нажмите ↻ для анализа</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

// === Messages Feed ===
function MessagesFeed({ messages, contact, dealId, aiEnabled, isMobile }) {
    const messagesEndRef = useRef(null);
    const [translating, setTranslating] = useState(false);
    const [translation, setTranslation] = useState(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const handleTranslate = async () => {
        setTranslating(true);
        try {
            const response = await fetch(`/deals/${dealId}/translate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            });
            const data = await response.json();
            if (data.translation) setTranslation(data.translation);
        } catch (e) {
            console.error(e);
        } finally {
            setTranslating(false);
        }
    };

    if (!messages || messages.length === 0) {
        return (
            <div className="flex-1 flex items-center justify-center p-6 md:p-10">
                <div className="text-center">
                    <MessageSquare className="w-12 h-12 md:w-16 md:h-16 text-zinc-700 mx-auto mb-3 md:mb-4" strokeWidth={1} />
                    <p className="text-sm text-zinc-500">Сообщения не найдены</p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex-1 flex flex-col min-h-0">
            {/* Translate Button */}
            {aiEnabled && (
                <div className="px-3 md:px-5 py-2 md:py-3 border-b border-white/5">
                    <button onClick={handleTranslate} disabled={translating} className="btn-ghost text-xs">
                        {translating ? <RefreshCw className="w-4 h-4 animate-spin" strokeWidth={1.5} /> : <Languages className="w-4 h-4" strokeWidth={1.5} />}
                        <span className="hidden sm:inline">Перевести на русский</span>
                        <span className="sm:hidden">Перевод</span>
                    </button>
                </div>
            )}

            {/* Translation */}
            {translation && (
                <div className="px-3 md:px-5 py-3 md:py-4 bg-violet-500/10 border-b border-violet-500/20">
                    <div className="flex items-start gap-2 mb-2">
                        <Languages className="w-4 h-4 text-violet-400 mt-0.5 flex-shrink-0" strokeWidth={1.5} />
                        <span className="text-xs font-semibold text-violet-300">Перевод:</span>
                        <button onClick={() => setTranslation(null)} className="ml-auto text-violet-400 hover:text-violet-300 p-1 min-w-[2rem] min-h-[2rem] flex items-center justify-center">✕</button>
                    </div>
                    <p className="text-xs md:text-sm text-zinc-300 whitespace-pre-wrap">{translation}</p>
                </div>
            )}

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-3 md:p-5 space-y-2 md:space-y-3 scrollbar-hide md:scrollbar-thin">
                {messages.map((msg, i) => {
                    const isFromClient = msg.from?.id === contact?.psid;
                    return (
                        <div key={msg.id || i} className={`flex ${isFromClient ? 'justify-start' : 'justify-end'} animate-in`} style={{ animationDelay: `${i * 30}ms` }}>
                            <div className={`chat-bubble ${isFromClient ? 'chat-bubble-client' : 'chat-bubble-page'}`}>
                                <p className="text-xs md:text-sm leading-relaxed text-white">{msg.message || '(без текста)'}</p>
                                <p className="text-[9px] md:text-[10px] mt-1 md:mt-1.5 text-white/50">
                                    {msg.created_time ? formatRelativeTime(msg.created_time) : ''}
                                </p>
                            </div>
                        </div>
                    );
                })}
                <div ref={messagesEndRef} />
            </div>
        </div>
    );
}

// === Response Templates ===
function ResponseTemplates() {
    const [copied, setCopied] = useState(null);

    const templates = [
        { id: 1, title: 'Приветствие', text: 'Здравствуйте! Спасибо за обращение. Чем могу помочь?' },
        { id: 2, title: 'Уточнение', text: 'Подскажите, пожалуйста, какой именно товар вас интересует?' },
        { id: 3, title: 'Цена', text: 'Стоимость и условия доставки отправлю вам в ближайшее время.' },
        { id: 4, title: 'Ожидание', text: 'Благодарю за терпение! Уточняю информацию и скоро вернусь.' },
        { id: 5, title: 'Завершение', text: 'Спасибо за заказ! Если возникнут вопросы — пишите!' },
    ];

    const handleCopy = async (template) => {
        try { await navigator.clipboard.writeText(template.text); } catch {}
        setCopied(template.id);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <div className="p-3 md:p-5">
            <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2 md:mb-3">📝 Шаблоны</h4>
            <div className="space-y-1.5 md:space-y-2">
                {templates.map((t) => (
                    <button key={t.id} onClick={() => handleCopy(t)}
                        className={`w-full text-left px-3 md:px-4 py-2.5 md:py-3 rounded-lg md:rounded-xl border transition-all min-h-[2.75rem] ${
                            copied === t.id ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-white/5 border-white/10 hover:border-indigo-500/30 hover:bg-indigo-500/10 text-zinc-300'
                        }`}>
                        <div className="flex items-center justify-between">
                            <span className="text-xs md:text-sm font-medium">{t.title}</span>
                            {copied === t.id ? <Check className="w-4 h-4 text-emerald-400 flex-shrink-0" strokeWidth={2} /> : <Copy className="w-4 h-4 text-zinc-500 flex-shrink-0" strokeWidth={1.5} />}
                        </div>
                        <p className="text-[10px] md:text-xs text-zinc-500 mt-0.5 md:mt-1 line-clamp-1">{t.text}</p>
                    </button>
                ))}
            </div>
        </div>
    );
}

// === Quick Actions ===
function QuickActions({ dealId, status }) {
    const [loading, setLoading] = useState(null);

    const handleAction = (action, data) => {
        setLoading(action);
        router.patch(`/deals/${dealId}`, data, {
            preserveScroll: true,
            onFinish: () => setLoading(null),
        });
    };

    return (
        <div className="glass-card-static">
            <ResponseTemplates />
            <div className="divider" />
            <div className="p-3 md:p-5">
                <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2 md:mb-3">⚡ Действия</h4>
                <div className="grid grid-cols-2 gap-2 md:gap-3">
                    {status !== 'Closed' && (
                        <button onClick={() => handleAction('close', { status: 'Closed' })} disabled={loading === 'close'}
                            className="flex items-center justify-center gap-1.5 md:gap-2 px-3 md:px-4 py-3 md:py-3 rounded-lg md:rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium text-xs md:text-sm shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 transition-all disabled:opacity-50 min-h-[2.75rem]">
                            {loading === 'close' ? <RefreshCw className="w-4 h-4 md:w-5 md:h-5 animate-spin" strokeWidth={1.5} /> : <CheckCircle className="w-4 h-4 md:w-5 md:h-5" strokeWidth={1.5} />}
                            <span className="hidden sm:inline">Завершить</span>
                            <span className="sm:hidden">✓</span>
                        </button>
                    )}
                    <button onClick={() => {
                        const tomorrow = new Date();
                        tomorrow.setDate(tomorrow.getDate() + 1);
                        tomorrow.setHours(10, 0, 0, 0);
                        handleAction('call', { reminder_at: tomorrow.toISOString().slice(0, 16), status: 'In Progress' });
                    }} disabled={loading === 'call'}
                        className={`flex items-center justify-center gap-1.5 md:gap-2 px-3 md:px-4 py-3 md:py-3 rounded-lg md:rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white font-medium text-xs md:text-sm shadow-lg shadow-amber-500/25 hover:-translate-y-0.5 transition-all disabled:opacity-50 min-h-[2.75rem] ${status === 'Closed' ? 'col-span-2' : ''}`}>
                        {loading === 'call' ? <RefreshCw className="w-4 h-4 md:w-5 md:h-5 animate-spin" strokeWidth={1.5} /> : <PhoneCall className="w-4 h-4 md:w-5 md:h-5" strokeWidth={1.5} />}
                        <span className="hidden sm:inline">Напомнить</span>
                        <span className="sm:hidden">🔔</span>
                    </button>
                </div>
            </div>
        </div>
    );
}

// === Deal Form ===
function DealForm({ deal, managers, statuses, canChangeManager }) {
    const { data, setData, patch, processing } = useForm({
        status: deal.status || 'New',
        comment: deal.comment || '',
        reminder_at: deal.reminder_at || '',
        manager_id: deal.manager_id || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(`/deals/${deal.id}`, { preserveScroll: true });
    };

    return (
        <div className="glass-card-static">
            <form onSubmit={handleSubmit}>
                <div className="p-3 md:p-5">
                    <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2 md:mb-3">Статус</h4>
                    <div className="flex flex-wrap gap-1.5 md:gap-2">
                        {statuses.map((s) => {
                            const isActive = data.status === s.value;
                            return (
                                <button key={s.value} type="button" onClick={() => setData('status', s.value)}
                                    className={`badge min-h-[2.75rem] px-3 md:px-4 ${s.value === 'New' ? 'badge-new' : s.value === 'In Progress' ? 'badge-progress' : 'badge-closed'} 
                                    ${isActive ? 'ring-2 ring-offset-2 ring-offset-[#0a0a0b] ring-current' : 'opacity-50 hover:opacity-80'}`}>
                                    {s.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="divider" />

                <div className="p-3 md:p-5">
                    <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2 md:mb-3">Ответственный</h4>
                    {canChangeManager ? (
                        <select className="input-premium" value={data.manager_id} onChange={(e) => setData('manager_id', e.target.value)}>
                            <option value="">Не назначен</option>
                            {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </select>
                    ) : (
                        <div className="flex items-center gap-3 px-3 md:px-4 py-2.5 md:py-3 bg-white/5 rounded-lg md:rounded-xl min-h-[2.75rem]">
                            <User className="w-4 h-4 text-zinc-500 flex-shrink-0" strokeWidth={1.5} />
                            <span className="text-sm text-zinc-300 truncate">{deal.manager?.name || 'Не назначен'}</span>
                        </div>
                    )}
                </div>

                <div className="divider" />

                <div className="p-3 md:p-5">
                    <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2 md:mb-3">Заметки</h4>
                    <textarea className="input-premium resize-none" rows={3} value={data.comment} onChange={(e) => setData('comment', e.target.value)} placeholder="Добавьте заметки..." />
                </div>

                <div className="divider" />

                <div className="p-3 md:p-5">
                    <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2 md:mb-3">Напоминание</h4>
                    <div className="flex items-center gap-2">
                        <div className="relative flex-1">
                            <Calendar className="absolute left-3 md:left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500 pointer-events-none" strokeWidth={1.5} />
                            <input type="datetime-local" className="input-premium pl-10 md:pl-11" value={data.reminder_at} onChange={(e) => setData('reminder_at', e.target.value)} />
                        </div>
                        {data.reminder_at && (
                            <button type="button" onClick={() => setData('reminder_at', '')} className="p-2.5 md:p-3 text-rose-400 hover:bg-rose-500/10 rounded-lg md:rounded-xl transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                                <Trash2 className="w-4 h-4 md:w-5 md:h-5" strokeWidth={1.5} />
                            </button>
                        )}
                    </div>
                </div>

                <div className="divider" />

                <div className="p-3 md:p-5">
                    <button type="submit" disabled={processing} className="btn-premium w-full justify-center">
                        {processing ? <RefreshCw className="w-4 h-4 md:w-5 md:h-5 animate-spin" strokeWidth={1.5} /> : <Save className="w-4 h-4 md:w-5 md:h-5" strokeWidth={1.5} />}
                        Сохранить
                    </button>
                </div>

                <div className="divider" />

                <div className="p-3 md:p-5">
                    <div className="grid grid-cols-2 gap-3 md:gap-4 text-xs">
                        <div>
                            <p className="text-zinc-500 font-medium uppercase tracking-wider mb-0.5 md:mb-1 text-[10px] md:text-xs">ID</p>
                            <p className="text-zinc-200 font-bold text-base md:text-lg">#{deal.id}</p>
                        </div>
                        <div>
                            <p className="text-zinc-500 font-medium uppercase tracking-wider mb-0.5 md:mb-1 text-[10px] md:text-xs">Создана</p>
                            <p className="text-zinc-300 text-xs md:text-sm truncate">{deal.created_at}</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    );
}

// === Activity Log ===
function ActivityLog({ logs }) {
    if (!logs || logs.length === 0) return null;

    return (
        <div className="glass-card-static">
            <div className="p-3 md:p-5">
                <h4 className="text-[10px] md:text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3 md:mb-4">📋 История</h4>
                <div className="space-y-2 md:space-y-3 max-h-[200px] md:max-h-[250px] overflow-y-auto scrollbar-hide md:scrollbar-thin">
                    {logs.map((log, i) => (
                        <div key={log.id} className="flex gap-2 md:gap-3 text-sm animate-in" style={{ animationDelay: `${i * 30}ms` }}>
                            <div className="w-7 h-7 md:w-8 md:h-8 rounded-lg bg-white/5 flex items-center justify-center text-xs md:text-sm flex-shrink-0">
                                {log.icon}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-zinc-300 text-xs md:text-sm line-clamp-2">{log.description}</p>
                                <div className="flex items-center gap-2 mt-0.5">
                                    <p className="text-[10px] md:text-xs text-zinc-500">{log.created_at}</p>
                                    {log.user && <span className="text-[10px] md:text-xs text-zinc-500 truncate">• {log.user.name}</span>}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

// === Mobile Chat View (Full Screen) ===
function MobileChatView({ deal, contact, conversation, messages, aiEnabled, onBack }) {
    return (
        <div className="mobile-chat-fullscreen open">
            {/* Header */}
            <div className="mobile-chat-header bg-[#0a0a0b]">
                <button onClick={onBack} className="p-2 text-zinc-400 hover:text-white transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center -ml-2">
                    <ArrowLeft className="w-5 h-5" strokeWidth={1.5} />
                </button>
                <div className="flex items-center gap-3 flex-1 min-w-0">
                    <div className="relative flex-shrink-0">
                        <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                            <span className="text-white font-bold">{contact?.name?.charAt(0)?.toUpperCase() || '?'}</span>
                        </div>
                        {isOnline(conversation?.updated_time) && <div className="online-dot absolute -bottom-0.5 -right-0.5" />}
                    </div>
                    <div className="min-w-0">
                        <h3 className="text-sm font-semibold text-white truncate">{contact?.name || 'Без имени'}</h3>
                        <p className="text-[10px] text-zinc-500">{conversation?.platform === 'instagram' ? 'Instagram' : 'Messenger'}</p>
                    </div>
                </div>
                {conversation?.link && (
                    <a href={conversation.link} target="_blank" rel="noopener noreferrer" className="p-2 text-zinc-400 hover:text-white transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                        <ExternalLink className="w-5 h-5" strokeWidth={1.5} />
                    </a>
                )}
            </div>

            {/* Messages */}
            <div className="flex-1 overflow-hidden bg-[#0a0a0b]">
                <MessagesFeed messages={messages} contact={contact} dealId={deal.id} aiEnabled={aiEnabled} isMobile={true} />
            </div>

            {/* Mobile Input Area - Sticky to keyboard */}
            <div className="mobile-chat-input">
                <div className="flex items-center gap-2">
                    <a href={conversation?.link} target="_blank" rel="noopener noreferrer" className="btn-premium flex-1 justify-center text-sm">
                        <Send className="w-4 h-4" strokeWidth={1.5} />
                        Ответить в Meta
                    </a>
                </div>
            </div>
        </div>
    );
}

// === Main ===
export default function ClientCard({ deal, contact, conversation, messages, managers, statuses, isAdmin, canChangeManager, aiEnabled, activityLogs }) {
    const isMobile = useIsMobile();
    const [showMobileChat, setShowMobileChat] = useState(false);

    const statusConfig = {
        'New': { label: 'Новая', class: 'badge-new' },
        'In Progress': { label: 'В работе', class: 'badge-progress' },
        'Closed': { label: 'Закрыта', class: 'badge-closed' },
    };

    const current = statusConfig[deal.status] || statusConfig['New'];

    // Mobile Full-Screen Chat
    if (isMobile && showMobileChat) {
        return (
            <MobileChatView 
                deal={deal}
                contact={contact}
                conversation={conversation}
                messages={messages}
                aiEnabled={aiEnabled}
                onBack={() => setShowMobileChat(false)}
            />
        );
    }

    return (
        <MainLayout title={`Сделка #${deal.id}`}>
            <Head title={`Сделка #${deal.id}`} />

            {/* Header */}
            <div className="flex items-center gap-3 md:gap-4 mb-4 md:mb-6">
                <Link href="/deals" className="p-2 md:p-2.5 text-zinc-500 hover:text-white hover:bg-white/5 rounded-lg md:rounded-xl transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                    <ArrowLeft className="w-5 h-5" strokeWidth={1.5} />
                </Link>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 md:gap-3 flex-wrap">
                        <h1 className="text-lg md:text-xl font-bold text-white truncate">{contact?.name || 'Без имени'}</h1>
                        <span className={`badge ${current.class}`}>{current.label}</span>
                    </div>
                </div>
            </div>

            {/* SLA Warning */}
            <SlaWarning isOverdue={deal.is_sla_overdue} minutes={deal.sla_overdue_minutes} />

            {/* Mobile Layout */}
            {isMobile ? (
                <div className="space-y-4">
                    {/* Contact Info */}
                    <ContactCard contact={contact} conversation={conversation} />

                    {/* Open Chat Button */}
                    <button 
                        onClick={() => setShowMobileChat(true)}
                        className="w-full glass-card-static p-4 flex items-center justify-between"
                    >
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center">
                                <MessageSquare className="w-5 h-5 text-indigo-400" strokeWidth={1.5} />
                            </div>
                            <div className="text-left">
                                <p className="text-sm font-semibold text-white">Открыть переписку</p>
                                <p className="text-xs text-zinc-500">{messages?.length || 0} сообщений</p>
                            </div>
                        </div>
                        <ChevronRight className="w-5 h-5 text-zinc-500" strokeWidth={1.5} />
                    </button>

                    {/* AI Card */}
                    {aiEnabled && <AiCard summary={deal.ai_summary} score={deal.ai_score} summaryAt={deal.ai_summary_at} dealId={deal.id} aiEnabled={aiEnabled} />}

                    {/* Quick Actions */}
                    <QuickActions dealId={deal.id} status={deal.status} />

                    {/* Deal Form */}
                    <DealForm deal={deal} managers={managers} statuses={statuses} canChangeManager={canChangeManager} />

                    {/* Activity Log */}
                    {activityLogs && activityLogs.length > 0 && <ActivityLog logs={activityLogs} />}
                </div>
            ) : (
                /* Desktop Layout */
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-6" style={{ minHeight: 'calc(100vh - 200px)' }}>
                    {/* Left: Contact */}
                    <div className="lg:col-span-3">
                        <div className="lg:sticky lg:top-24 space-y-4">
                            <ContactCard contact={contact} conversation={conversation} />
                        </div>
                    </div>

                    {/* Center: Messages */}
                    <div className="lg:col-span-5">
                        <div className="glass-card-static flex flex-col min-h-[500px] lg:h-[calc(100vh-180px)] overflow-hidden">
                            <div className="flex items-center gap-3 px-4 md:px-5 py-3 md:py-4 border-b border-white/5">
                                <div className="w-9 h-9 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <MessageSquare className="w-4 h-4 md:w-5 md:h-5 text-zinc-400" strokeWidth={1.5} />
                                </div>
                                <div className="min-w-0">
                                    <h3 className="text-sm font-semibold text-white">Переписка</h3>
                                    <p className="text-xs text-zinc-500">{messages?.length || 0} сообщений</p>
                                </div>
                            </div>
                            <MessagesFeed messages={messages} contact={contact} dealId={deal.id} aiEnabled={aiEnabled} isMobile={false} />
                        </div>
                    </div>

                    {/* Right: Actions */}
                    <div className="lg:col-span-4">
                        <div className="lg:sticky lg:top-24 space-y-4">
                            {aiEnabled && <AiCard summary={deal.ai_summary} score={deal.ai_score} summaryAt={deal.ai_summary_at} dealId={deal.id} aiEnabled={aiEnabled} />}
                            <QuickActions dealId={deal.id} status={deal.status} />
                            <DealForm deal={deal} managers={managers} statuses={statuses} canChangeManager={canChangeManager} />
                            {activityLogs && activityLogs.length > 0 && <ActivityLog logs={activityLogs} />}
                        </div>
                    </div>
                </div>
            )}
        </MainLayout>
    );
}
