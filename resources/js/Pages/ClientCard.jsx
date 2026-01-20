import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { formatDistanceToNow, differenceInMinutes } from 'date-fns';
import { ru } from 'date-fns/locale';
import { 
    ArrowLeft, Copy, Check, User, Clock, Bot, RefreshCw, ExternalLink, 
    MessageSquare, Instagram, CheckCircle, PhoneCall, Calendar, Save, 
    Trash2, Briefcase, Hourglass, Sparkles, Zap, Languages, Timer, AlertTriangle
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

// === Copy Button ===
function CopyButton({ text, label = 'Копировать', className = '' }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try { await navigator.clipboard.writeText(text); } 
        catch {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <button onClick={handleCopy} className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition-all duration-300
            ${copied ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'} ${className}`}>
            {copied ? <Check className="w-3.5 h-3.5" strokeWidth={1.5} /> : <Copy className="w-3.5 h-3.5" strokeWidth={1.5} />}
            {copied ? 'Скопировано!' : label}
        </button>
    );
}

// === Lead Score Badge ===
function LeadScoreBadge({ score }) {
    if (!score) return null;
    
    const isHot = score > 80;
    const isWarm = score > 60;
    
    return (
        <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm font-bold
            ${isHot ? 'bg-gradient-to-r from-orange-500 to-red-500 text-white shadow-lg shadow-orange-500/30' : 
              isWarm ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600'}`}>
            {isHot && <Zap className="w-4 h-4" strokeWidth={2} />}
            <span>Score: {score}</span>
            {isHot && <span className="text-xs opacity-80">HOT LEAD</span>}
        </div>
    );
}

// === SLA Warning ===
function SlaWarning({ isOverdue, minutes }) {
    if (!isOverdue) return null;
    
    return (
        <div className="flex items-center gap-3 px-4 py-3 mb-4 rounded-xl bg-orange-50 border border-orange-200 text-orange-700 animate-pulse">
            <AlertTriangle className="w-5 h-5" strokeWidth={1.5} />
            <div>
                <p className="font-semibold text-sm">⚠️ Просрочка SLA!</p>
                <p className="text-xs">Клиент ждёт ответа уже {30 + minutes} мин.</p>
            </div>
        </div>
    );
}

// === Contact Card ===
function ContactCard({ contact, conversation }) {
    const online = isOnline(conversation?.updated_time);

    return (
        <div className="divide-y divide-slate-100/80">
            <div className="p-6">
                <div className="flex items-center gap-4">
                    <div className="relative">
                        <div className="w-16 h-16 rounded-2xl gradient-indigo flex items-center justify-center shadow-lg shadow-indigo-500/30 animate-float">
                            <span className="text-white font-bold text-xl">{contact?.name?.charAt(0)?.toUpperCase() || '?'}</span>
                        </div>
                        {online && <span className="absolute -bottom-1 -right-1 online-dot" />}
                    </div>
                    <div className="flex-1 min-w-0">
                        <h3 className="text-lg font-bold text-slate-900 truncate mb-1">{contact?.name || 'Без имени'}</h3>
                        <div className="flex items-center gap-2 flex-wrap">
                            <code className="text-xs text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg font-mono">{contact?.psid}</code>
                            <CopyButton text={contact?.psid} label="PSID" />
                        </div>
                    </div>
                </div>
            </div>

            <div className="p-6 space-y-5">
                {(contact?.first_name || contact?.last_name) && (
                    <div className="flex items-center gap-4">
                        <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                            <User className="w-5 h-5 text-slate-500" strokeWidth={1.5} />
                        </div>
                        <div>
                            <p className="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Полное имя</p>
                            <p className="text-sm text-slate-700 font-medium">{contact.first_name} {contact.last_name}</p>
                        </div>
                    </div>
                )}

                {conversation && (
                    <>
                        <div className="flex items-center gap-4">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${conversation.platform === 'instagram' ? 'bg-gradient-to-br from-pink-100 to-purple-100' : 'bg-indigo-100'}`}>
                                {conversation.platform === 'instagram' 
                                    ? <Instagram className="w-5 h-5 text-pink-600" strokeWidth={1.5} />
                                    : <MessageSquare className="w-5 h-5 text-indigo-600" strokeWidth={1.5} />}
                            </div>
                            <div>
                                <p className="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Платформа</p>
                                <p className="text-sm text-slate-700 font-medium">{conversation.platform === 'instagram' ? 'Instagram Direct' : 'Facebook Messenger'}</p>
                            </div>
                        </div>
                    </>
                )}
            </div>

            {conversation?.link && (
                <div className="p-6">
                    <a href={conversation.link} target="_blank" rel="noopener noreferrer"
                        className="flex items-center justify-center gap-2.5 w-full px-5 py-3.5 text-sm font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-2xl transition-all duration-300">
                        <ExternalLink className="w-4 h-4" strokeWidth={1.5} /> Открыть в Meta
                    </a>
                </div>
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
        <div className={`ai-card ${summary ? 'ai-card-glow' : ''} overflow-hidden transition-all duration-500`}>
            <div className="p-5">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-3">
                        <div className={`w-10 h-10 rounded-xl flex items-center justify-center shadow-lg ${isHot ? 'bg-gradient-to-br from-orange-500 to-red-500 shadow-orange-500/30' : 'gradient-violet shadow-violet-500/30'}`}>
                            {isHot ? <Zap className="w-5 h-5 text-white" strokeWidth={1.5} /> : <Sparkles className="w-5 h-5 text-white" strokeWidth={1.5} />}
                        </div>
                        <div>
                            <h4 className="text-sm font-bold text-slate-900">AI-Анализ</h4>
                            {summaryAt && <p className="text-[10px] text-slate-400">{summaryAt}</p>}
                        </div>
                    </div>
                    <div className="flex items-center gap-1">
                        {summary && (
                            <button onClick={handleCopy} className={`p-2.5 rounded-xl transition-all ${copied ? 'bg-emerald-100 text-emerald-600' : 'hover:bg-white/80 text-slate-500'}`}>
                                {copied ? <Check className="w-4 h-4" strokeWidth={1.5} /> : <Copy className="w-4 h-4" strokeWidth={1.5} />}
                            </button>
                        )}
                        <button onClick={handleRefresh} disabled={refreshing} className="p-2.5 rounded-xl hover:bg-white/80 text-slate-500 disabled:opacity-50">
                            <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} strokeWidth={1.5} />
                        </button>
                    </div>
                </div>

                {score && <div className="mb-4"><LeadScoreBadge score={score} /></div>}

                <div className="bg-white/60 backdrop-blur rounded-xl p-4 border border-purple-100/50">
                    {refreshing ? (
                        <div className="space-y-3 animate-pulse">
                            <div className="h-4 bg-slate-200 rounded w-3/4"></div>
                            <div className="h-4 bg-slate-200 rounded w-full"></div>
                            <div className="h-4 bg-slate-200 rounded w-5/6"></div>
                        </div>
                    ) : summary ? (
                        <p className="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap">{summary}</p>
                    ) : (
                        <div className="text-center py-6">
                            <Sparkles className="w-8 h-8 text-violet-300 mx-auto mb-2" strokeWidth={1.5} />
                            <p className="text-sm text-slate-400">Нажмите ↻ для AI-анализа</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

// === Messages Feed ===
function MessagesFeed({ messages, contact, dealId, aiEnabled }) {
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
            if (data.translation) {
                setTranslation(data.translation);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setTranslating(false);
        }
    };

    if (!messages || messages.length === 0) {
        return (
            <div className="flex-1 flex items-center justify-center text-slate-400 p-10">
                <div className="text-center">
                    <MessageSquare className="w-16 h-16 text-slate-200 mx-auto mb-4" strokeWidth={1} />
                    <p className="text-sm font-medium text-slate-500">Сообщения не найдены</p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex-1 flex flex-col">
            {/* Translate Button */}
            {aiEnabled && (
                <div className="px-5 py-3 border-b border-slate-100/80">
                    <button onClick={handleTranslate} disabled={translating}
                        className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-violet-600 bg-violet-50 hover:bg-violet-100 rounded-xl transition-all disabled:opacity-50">
                        {translating ? <RefreshCw className="w-4 h-4 animate-spin" strokeWidth={1.5} /> : <Languages className="w-4 h-4" strokeWidth={1.5} />}
                        Перевести на русский
                    </button>
                </div>
            )}

            {/* Translation */}
            {translation && (
                <div className="px-5 py-4 bg-violet-50 border-b border-violet-100">
                    <div className="flex items-start gap-2 mb-2">
                        <Languages className="w-4 h-4 text-violet-600 mt-0.5" strokeWidth={1.5} />
                        <span className="text-xs font-semibold text-violet-700">Перевод:</span>
                        <button onClick={() => setTranslation(null)} className="ml-auto text-violet-400 hover:text-violet-600">✕</button>
                    </div>
                    <p className="text-sm text-slate-700 whitespace-pre-wrap">{translation}</p>
                </div>
            )}

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-5 space-y-4 scrollbar-thin">
                {messages.map((msg, i) => {
                    const isFromClient = msg.from?.id === contact?.psid;
                    return (
                        <div key={msg.id || i} className={`flex ${isFromClient ? 'justify-start' : 'justify-end'} animate-fade-in`} style={{ animationDelay: `${i * 30}ms` }}>
                            <div className={isFromClient ? 'bubble-incoming' : 'bubble-outgoing'}>
                                <p className="leading-relaxed">{msg.message || '(без текста)'}</p>
                                <p className={`text-[10px] mt-1.5 ${isFromClient ? 'text-slate-400' : 'text-white/60'}`}>
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

// === Activity Log ===
function ActivityLog({ logs }) {
    if (!logs || logs.length === 0) {
        return null;
    }

    return (
        <div className="p-6">
            <h4 className="label mb-4">История действий</h4>
            <div className="space-y-3 max-h-[300px] overflow-y-auto scrollbar-thin pr-2">
                {logs.map((log, i) => (
                    <div key={log.id} className="flex gap-3 text-sm animate-fade-in" style={{ animationDelay: `${i * 30}ms` }}>
                        <div className="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-base flex-shrink-0">
                            {log.icon}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-slate-700 font-medium text-sm">{log.description}</p>
                            <div className="flex items-center gap-2 mt-0.5">
                                <p className="text-xs text-slate-400">{log.created_at}</p>
                                {log.user && <span className="text-xs text-slate-400">• {log.user.name}</span>}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// === Manager Rating Display ===
function ManagerRatingCard({ rating, review }) {
    if (!rating) return null;

    const stars = [];
    for (let i = 1; i <= 5; i++) {
        stars.push(
            <span key={i} className={`text-lg ${i <= rating ? 'text-yellow-500' : 'text-slate-300'}`}>★</span>
        );
    }

    return (
        <div className="p-6 bg-gradient-to-br from-yellow-50 to-orange-50 border-t border-yellow-100">
            <h4 className="label mb-3 text-yellow-800">AI-Оценка работы менеджера</h4>
            <div className="flex items-center gap-2 mb-2">
                {stars}
                <span className="text-sm font-bold text-yellow-700 ml-1">{rating}/5</span>
            </div>
            {review && (
                <p className="text-sm text-slate-600 leading-relaxed italic">"{review}"</p>
            )}
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
        { id: 4, title: 'Ожидание', text: 'Благодарю за терпение! Уточняю информацию и скоро вернусь с ответом.' },
        { id: 5, title: 'Завершение', text: 'Спасибо за заказ! Если возникнут вопросы — пишите, всегда рады помочь.' },
    ];

    const handleCopy = async (template) => {
        try {
            await navigator.clipboard.writeText(template.text);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = template.text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        setCopied(template.id);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <div className="p-5">
            <h4 className="label mb-3">📝 Шаблоны ответов</h4>
            <div className="space-y-2">
                {templates.map((t) => (
                    <button
                        key={t.id}
                        onClick={() => handleCopy(t)}
                        className={`w-full text-left px-4 py-3 rounded-xl border transition-all duration-200 ${
                            copied === t.id 
                                ? 'bg-emerald-50 border-emerald-300 text-emerald-700' 
                                : 'bg-white border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/50'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">{t.title}</span>
                            {copied === t.id ? (
                                <Check className="w-4 h-4 text-emerald-500" strokeWidth={2} />
                            ) : (
                                <Copy className="w-4 h-4 text-slate-400" strokeWidth={1.5} />
                            )}
                        </div>
                        <p className="text-xs text-slate-500 mt-1 line-clamp-1">{t.text}</p>
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
        <div className="divide-y divide-slate-100/80">
            <ResponseTemplates />
            <div className="p-5">
                <h4 className="label mb-3">⚡ Быстрые действия</h4>
                <div className="grid grid-cols-2 gap-3">
                    {status !== 'Closed' && (
                        <button onClick={() => handleAction('close', { status: 'Closed' })} disabled={loading === 'close'}
                            className="btn btn-lg gradient-emerald text-white shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 disabled:opacity-50">
                            {loading === 'close' ? <RefreshCw className="w-5 h-5 animate-spin" strokeWidth={1.5} /> : <CheckCircle className="w-5 h-5" strokeWidth={1.5} />}
                            <span>Завершить</span>
                        </button>
                    )}
                    <button onClick={() => {
                        const tomorrow = new Date();
                        tomorrow.setDate(tomorrow.getDate() + 1);
                        tomorrow.setHours(10, 0, 0, 0);
                        handleAction('call', { reminder_at: tomorrow.toISOString().slice(0, 16), status: 'In Progress' });
                    }} disabled={loading === 'call'}
                        className={`btn btn-lg gradient-amber text-white shadow-lg shadow-amber-500/25 hover:-translate-y-0.5 disabled:opacity-50 ${status === 'Closed' ? 'col-span-2' : ''}`}>
                        {loading === 'call' ? <RefreshCw className="w-5 h-5 animate-spin" strokeWidth={1.5} /> : <PhoneCall className="w-5 h-5" strokeWidth={1.5} />}
                        <span>Напомнить завтра</span>
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

    const statusConfig = {
        'New': { icon: Briefcase, class: 'pill-new' },
        'In Progress': { icon: Hourglass, class: 'pill-progress' },
        'Closed': { icon: CheckCircle, class: 'pill-closed' },
    };

    return (
        <div className="divide-y divide-slate-100/80">
            <form onSubmit={handleSubmit}>
                <div className="p-6">
                    <h4 className="label mb-4">Статус сделки</h4>
                    <div className="flex flex-wrap gap-2">
                        {statuses.map((s) => {
                            const cfg = statusConfig[s.value];
                            const Icon = cfg?.icon || Briefcase;
                            const isActive = data.status === s.value;
                            return (
                                <button key={s.value} type="button" onClick={() => setData('status', s.value)}
                                    className={`pill ${cfg?.class || 'pill-new'} ${isActive ? 'ring-2 ring-offset-2 ring-current shadow-lg scale-105' : 'opacity-50 hover:opacity-80'}`}>
                                    <Icon className="w-4 h-4" strokeWidth={1.5} /> {s.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="p-6">
                    <h4 className="label mb-4">Ответственный</h4>
                    {canChangeManager ? (
                        <select className="input" value={data.manager_id} onChange={(e) => setData('manager_id', e.target.value)}>
                            <option value="">Не назначен</option>
                            {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </select>
                    ) : (
                        <div className="flex items-center gap-3 px-4 py-3 bg-slate-50 rounded-2xl">
                            <User className="w-4 h-4 text-slate-400" strokeWidth={1.5} />
                            <span className="text-sm font-medium text-slate-700">{deal.manager?.name || 'Не назначен'}</span>
                        </div>
                    )}
                </div>

                <div className="p-6">
                    <h4 className="label mb-4">Заметки</h4>
                    <textarea className="input resize-none" rows={4} value={data.comment} onChange={(e) => setData('comment', e.target.value)} placeholder="Добавьте заметки..." />
                </div>

                <div className="p-6">
                    <h4 className="label mb-4">Напоминание</h4>
                    <div className="flex items-center gap-3">
                        <div className="relative flex-1">
                            <Calendar className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" strokeWidth={1.5} />
                            <input type="datetime-local" className="input pl-11" value={data.reminder_at} onChange={(e) => setData('reminder_at', e.target.value)} />
                        </div>
                        {data.reminder_at && (
                            <button type="button" onClick={() => setData('reminder_at', '')} className="p-3 text-rose-500 hover:bg-rose-50 rounded-xl">
                                <Trash2 className="w-5 h-5" strokeWidth={1.5} />
                            </button>
                        )}
                    </div>
                </div>

                <div className="p-6">
                    <button type="submit" disabled={processing} className="btn-primary w-full btn-lg">
                        {processing ? <RefreshCw className="w-5 h-5 animate-spin" strokeWidth={1.5} /> : <Save className="w-5 h-5" strokeWidth={1.5} />}
                        Сохранить
                    </button>
                </div>
            </form>

            <div className="p-6 bg-slate-50/50">
                <div className="grid grid-cols-2 gap-5 text-xs">
                    <div>
                        <p className="text-slate-400 font-semibold uppercase tracking-wider mb-1">ID</p>
                        <p className="text-slate-700 font-bold text-base">#{deal.id}</p>
                    </div>
                    <div>
                        <p className="text-slate-400 font-semibold uppercase tracking-wider mb-1">Создана</p>
                        <p className="text-slate-700 font-medium">{deal.created_at}</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

// === Main ===
export default function ClientCard({ deal, contact, conversation, messages, managers, statuses, isAdmin, canChangeManager, aiEnabled, activityLogs }) {
    const statusConfig = {
        'New': { label: 'Новая', class: 'pill-new', icon: Briefcase },
        'In Progress': { label: 'В работе', class: 'pill-progress', icon: Hourglass },
        'Closed': { label: 'Закрыта', class: 'pill-closed', icon: CheckCircle },
    };

    const current = statusConfig[deal.status] || statusConfig['New'];
    const StatusIcon = current.icon;

    return (
        <MainLayout>
            <Head title={`Сделка #${deal.id}`} />

            {/* Header */}
            <div className="sticky top-0 z-30 -mx-6 -mt-6 px-6 py-5 mb-6 glass border-b border-slate-200/40">
                <div className="flex items-center gap-4">
                    <Link href="/deals" className="p-2.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-all">
                        <ArrowLeft className="w-5 h-5" strokeWidth={1.5} />
                    </Link>
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold text-slate-900 truncate">{contact?.name || 'Без имени'}</h1>
                            <span className={`pill ${current.class}`}><StatusIcon className="w-3.5 h-3.5" strokeWidth={1.5} /> {current.label}</span>
                        </div>
                        <p className="text-sm text-slate-500 mt-0.5">Сделка #{deal.id}</p>
                    </div>
                </div>
            </div>

            {/* SLA Warning */}
            <SlaWarning isOverdue={deal.is_sla_overdue} minutes={deal.sla_overdue_minutes} />

            {/* Layout */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6" style={{ minHeight: 'calc(100vh - 200px)' }}>
                {/* Left: Contact */}
                <div className="lg:col-span-3">
                    <div className="lg:sticky lg:top-28">
                        <div className="card overflow-hidden animate-fade-in-up">
                            <ContactCard contact={contact} conversation={conversation} />
                        </div>
                    </div>
                </div>

                {/* Center: Messages */}
                <div className="lg:col-span-5 flex flex-col">
                    <div className="card flex-1 flex flex-col overflow-hidden min-h-[500px] animate-fade-in-up" style={{ animationDelay: '100ms' }}>
                        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100/80">
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center">
                                    <MessageSquare className="w-4 h-4 text-slate-500" strokeWidth={1.5} />
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-slate-700">Переписка</h3>
                                    <p className="text-xs text-slate-400">{messages?.length || 0} сообщений</p>
                                </div>
                            </div>
                        </div>
                        <MessagesFeed messages={messages} contact={contact} dealId={deal.id} aiEnabled={aiEnabled} />
                    </div>
                </div>

                {/* Right: Actions */}
                <div className="lg:col-span-4">
                    <div className="lg:sticky lg:top-28 space-y-5">
                        {aiEnabled && (
                            <div className="animate-fade-in-up" style={{ animationDelay: '150ms' }}>
                                <AiCard summary={deal.ai_summary} score={deal.ai_score} summaryAt={deal.ai_summary_at} dealId={deal.id} aiEnabled={aiEnabled} />
                            </div>
                        )}
                        <div className="card overflow-hidden animate-fade-in-up" style={{ animationDelay: '200ms' }}>
                            <QuickActions dealId={deal.id} status={deal.status} />
                        </div>
                        <div className="card overflow-hidden animate-fade-in-up" style={{ animationDelay: '250ms' }}>
                            <DealForm deal={deal} managers={managers} statuses={statuses} canChangeManager={canChangeManager} />
                            <ManagerRatingCard rating={deal.manager_rating} review={deal.manager_review} />
                        </div>
                        
                        {/* Activity Log */}
                        {activityLogs && activityLogs.length > 0 && (
                            <div className="card overflow-hidden animate-fade-in-up" style={{ animationDelay: '300ms' }}>
                                <ActivityLog logs={activityLogs} />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
