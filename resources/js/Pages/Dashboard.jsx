import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import { formatDistanceToNow, isToday, parseISO, differenceInMinutes } from 'date-fns';
import { ru } from 'date-fns/locale';
import { 
    Search, Filter, Bell, BellOff, Clock, User, Flame, TrendingUp, Zap,
    MessageSquare, Instagram, ChevronRight, RefreshCw, AlertTriangle,
    FileText, Eye, Briefcase, CheckCircle, Hourglass, Timer, Star, AlertCircle
} from 'lucide-react';
import MainLayout from '../Layouts/MainLayout';

// === Utilities ===
function playNotificationSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 880;
        osc.type = 'sine';
        gain.gain.value = 0.2;
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
        osc.stop(ctx.currentTime + 0.2);
    } catch (e) {}
}

function showBrowserNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, { body, icon: '/favicon.ico' });
    }
}

function formatRelativeTime(dateString) {
    if (!dateString) return '—';
    try {
        const date = typeof dateString === 'string' ? parseISO(dateString) : new Date(dateString);
        return formatDistanceToNow(date, { addSuffix: true, locale: ru });
    } catch { return '—'; }
}

function isOnline(updatedAt) {
    if (!updatedAt) return false;
    try {
        return differenceInMinutes(new Date(), parseISO(updatedAt)) < 10;
    } catch { return false; }
}

function isSlaOverdue(deal) {
    if (!deal.last_client_message_at || deal.status === 'Closed') return false;
    if (deal.last_manager_response_at) {
        const clientTime = new Date(deal.last_client_message_at);
        const managerTime = new Date(deal.last_manager_response_at);
        if (managerTime >= clientTime) return false;
    }
    const clientTime = new Date(deal.last_client_message_at);
    return differenceInMinutes(new Date(), clientTime) >= 30;
}

// === Components ===
function StatCard({ icon: Icon, label, value, color = 'indigo', delay = 0 }) {
    const gradients = {
        indigo: 'gradient-indigo',
        emerald: 'gradient-emerald',
        amber: 'gradient-amber',
        rose: 'gradient-rose',
        violet: 'gradient-violet',
    };

    return (
        <div className="card p-6 hover-lift animate-fade-in-up" style={{ animationDelay: `${delay}ms` }}>
            <div className="flex items-center gap-5">
                <div className={`stat-icon ${gradients[color]} shadow-lg`}>
                    <Icon className="w-6 h-6 text-white" strokeWidth={1.5} />
                </div>
                <div className="flex-1">
                    <p className="text-sm text-slate-500 font-medium mb-0.5">{label}</p>
                    <p className="text-3xl font-bold text-slate-900 tracking-tight">{value}</p>
                </div>
            </div>
        </div>
    );
}

function LeadScore({ score }) {
    if (!score) return null;
    
    const isHot = score > 80;
    const isWarm = score > 60;
    
    return (
        <div className={`
            inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-bold
            ${isHot ? 'bg-orange-100 text-orange-700 animate-pulse' : 
              isWarm ? 'bg-yellow-100 text-yellow-700' : 
              'bg-slate-100 text-slate-600'}
        `}>
            {isHot && <Zap className="w-3 h-3" strokeWidth={2} />}
            {score}
        </div>
    );
}

function ManagerRating({ rating }) {
    if (!rating) return null;
    
    const stars = [];
    for (let i = 1; i <= 5; i++) {
        stars.push(
            <Star 
                key={i} 
                className={`w-3 h-3 ${i <= rating ? 'text-yellow-500 fill-yellow-500' : 'text-slate-300'}`} 
                strokeWidth={1.5} 
            />
        );
    }
    
    return (
        <div className="inline-flex items-center gap-0.5" title={`Оценка: ${rating}/5`}>
            {stars}
        </div>
    );
}

function StatusPill({ status, dealId }) {
    const [isOpen, setIsOpen] = useState(false);
    const [updating, setUpdating] = useState(false);
    const ref = useRef(null);

    const config = {
        'New': { label: 'Новая', class: 'pill-new', icon: Briefcase },
        'In Progress': { label: 'В работе', class: 'pill-progress', icon: Hourglass },
        'Closed': { label: 'Закрыта', class: 'pill-closed', icon: CheckCircle },
    };

    const current = config[status] || config['New'];
    const Icon = current.icon;

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setIsOpen(false);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleChange = (newStatus) => {
        if (newStatus === status) return setIsOpen(false);
        setUpdating(true);
        router.patch(`/deals/${dealId}`, { status: newStatus }, {
            preserveScroll: true,
            onFinish: () => { setIsOpen(false); setUpdating(false); },
        });
    };

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setIsOpen(!isOpen)} disabled={updating} className={`pill ${current.class}`}>
                {updating ? <RefreshCw className="w-3.5 h-3.5 animate-spin" strokeWidth={1.5} /> : <Icon className="w-3.5 h-3.5" strokeWidth={1.5} />}
                <span>{current.label}</span>
            </button>
            {isOpen && (
                <div className="absolute z-50 mt-2 w-40 bg-white rounded-2xl border border-slate-200/50 py-2 left-0 animate-scale-in shadow-xl">
                    {Object.entries(config).map(([key, cfg]) => {
                        const ItemIcon = cfg.icon;
                        return (
                            <button key={key} onClick={() => handleChange(key)}
                                className={`flex items-center gap-2.5 w-full px-4 py-2.5 text-xs font-medium transition-all ${key === status ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'}`}>
                                <ItemIcon className="w-4 h-4" strokeWidth={1.5} />
                                {cfg.label}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function Filters({ filters, statuses, managers, isAdmin, onFilter }) {
    const [local, setLocal] = useState(filters);

    return (
        <div className="card-glass p-5 mb-6 animate-fade-in">
            <div className="flex flex-wrap items-center gap-4">
                <div className="flex-1 min-w-[240px]">
                    <div className="relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" strokeWidth={1.5} />
                        <input type="text" className="input pl-11" placeholder="Поиск..."
                            value={local.search || ''} onChange={(e) => setLocal({ ...local, search: e.target.value })}
                            onKeyDown={(e) => e.key === 'Enter' && onFilter(local)} />
                    </div>
                </div>
                <select className="input w-auto min-w-[150px]" value={local.status || ''} onChange={(e) => setLocal({ ...local, status: e.target.value })}>
                    <option value="">Все статусы</option>
                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
                {isAdmin && (
                    <select className="input w-auto min-w-[170px]" value={local.manager_id || ''} onChange={(e) => setLocal({ ...local, manager_id: e.target.value })}>
                        <option value="">Все менеджеры</option>
                        {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                    </select>
                )}
                <button onClick={() => onFilter(local)} className="btn-primary">
                    <Filter className="w-4 h-4" strokeWidth={1.5} /> Найти
                </button>
                <button onClick={() => { setLocal({}); onFilter({}); }} className="btn-ghost">Сбросить</button>
            </div>
        </div>
    );
}

function DealRow({ deal, index }) {
    const now = new Date();
    const isReminderDue = deal.reminder_at && new Date(deal.reminder_at) <= now;
    const isUnviewed = !deal.is_viewed;
    const online = isOnline(deal.conversation?.updated_time || deal.updated_at);
    const reminderToday = deal.reminder_at && isToday(parseISO(deal.reminder_at));
    const slaOverdue = isSlaOverdue(deal);
    const isHotLead = deal.ai_score && deal.ai_score > 80;
    const isPriority = deal.is_priority && deal.status !== 'Closed';

    return (
        <div className={`
            card-glass p-5 group animate-fade-in-up transition-all duration-300
            hover:-translate-y-0.5 hover:shadow-xl
            ${isPriority ? 'priority-pulse ring-2 ring-red-500 bg-red-50/30' : ''}
            ${slaOverdue && !isPriority ? 'sla-alert ring-2 ring-orange-400' : ''}
            ${isReminderDue && !slaOverdue && !isPriority ? 'reminder-alert' : ''}
            ${isUnviewed ? 'border-l-3 border-l-indigo-500' : ''}
        `} style={{ animationDelay: `${250 + index * 40}ms` }}>
            <div className="flex items-center gap-5">
                {/* Avatar */}
                <div className="relative flex-shrink-0">
                    <div className={`w-14 h-14 rounded-2xl flex items-center justify-center text-white font-semibold text-lg transition-transform duration-300 group-hover:scale-105
                        ${isPriority ? 'bg-gradient-to-br from-red-500 to-rose-600 shadow-lg shadow-red-500/30 animate-pulse' :
                          isHotLead ? 'bg-gradient-to-br from-orange-500 to-red-500 shadow-lg shadow-orange-500/30' :
                          isUnviewed ? 'gradient-indigo shadow-lg shadow-indigo-500/30' : 'bg-slate-200 text-slate-600'}`}>
                        {deal.contact?.name?.charAt(0)?.toUpperCase() || '?'}
                    </div>
                    {online && <span className="absolute -bottom-0.5 -right-0.5 online-dot" />}
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2.5 mb-1 flex-wrap">
                        <h3 className={`text-base truncate ${isUnviewed ? 'font-bold text-slate-900' : 'font-semibold text-slate-700'}`}>
                            {deal.contact?.name || 'Без имени'}
                        </h3>
                        {isPriority && (
                            <div className="flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-600 rounded-full text-xs font-bold animate-pulse">
                                <AlertCircle className="w-3 h-3" strokeWidth={2} /> ПРИОРИТЕТ
                            </div>
                        )}
                        {isHotLead && !isPriority && (
                            <div className="flex items-center gap-1 px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full text-xs font-bold animate-pulse">
                                <Zap className="w-3 h-3" strokeWidth={2} /> HOT
                            </div>
                        )}
                        {reminderToday && !slaOverdue && !isPriority && (
                            <div className="flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-600 rounded-full text-xs font-medium">
                                <Flame className="w-3 h-3" strokeWidth={1.5} /> Сегодня
                            </div>
                        )}
                        {slaOverdue && !isPriority && (
                            <div className="flex items-center gap-1 px-2 py-0.5 bg-orange-100 text-orange-600 rounded-full text-xs font-bold animate-pulse">
                                <Timer className="w-3 h-3" strokeWidth={2} /> SLA
                            </div>
                        )}
                        {isUnviewed && (
                            <span className="badge-indigo text-[10px]">NEW</span>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <p className="text-xs text-slate-400 font-mono">{deal.contact?.psid}</p>
                        {deal.ai_score && <LeadScore score={deal.ai_score} />}
                        {deal.manager_rating && <ManagerRating rating={deal.manager_rating} />}
                    </div>
                </div>

                {/* Platform */}
                <div className="hidden sm:flex items-center gap-2">
                    <span className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-medium
                        ${deal.conversation?.platform === 'instagram' ? 'bg-gradient-to-r from-pink-50 to-purple-50 text-pink-600' : 'bg-indigo-50 text-indigo-600'}`}>
                        {deal.conversation?.platform === 'instagram' 
                            ? <Instagram className="w-4 h-4" strokeWidth={1.5} /> 
                            : <MessageSquare className="w-4 h-4" strokeWidth={1.5} />}
                        {deal.conversation?.platform === 'instagram' ? 'IG' : 'FB'}
                    </span>
                </div>

                {/* Status */}
                <StatusPill status={deal.status} dealId={deal.id} />

                {/* Manager */}
                <div className="hidden lg:flex items-center gap-3 min-w-[110px]">
                    <div className="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center">
                        <User className="w-4 h-4 text-slate-400" strokeWidth={1.5} />
                    </div>
                    <span className="text-sm text-slate-600 truncate">{deal.manager?.name || '—'}</span>
                </div>

                {/* Time */}
                <div className="hidden xl:flex items-center gap-2 text-sm text-slate-400 min-w-[100px]">
                    <Clock className="w-4 h-4" strokeWidth={1.5} />
                    {formatRelativeTime(deal.updated_at)}
                </div>

                {/* Action */}
                <Link href={`/deals/${deal.id}`}
                    className="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-xl transition-all duration-300 opacity-0 group-hover:opacity-100">
                    Открыть <ChevronRight className="w-4 h-4" strokeWidth={1.5} />
                </Link>
            </div>
        </div>
    );
}

export default function Dashboard({ deals, managers, filters, statuses, isAdmin }) {
    const prevCount = useRef(null);
    const [notifEnabled, setNotifEnabled] = useState(false);

    useEffect(() => {
        if ('Notification' in window && Notification.permission === 'granted') {
            setNotifEnabled(true);
        }
    }, []);

    useEffect(() => {
        if (prevCount.current !== null && deals.total > prevCount.current) {
            playNotificationSound();
            showBrowserNotification('🔔 Новая сделка!', `Поступила новая сделка`);
        }
        prevCount.current = deals.total || 0;
    }, [deals.total]);

    useEffect(() => {
        const interval = setInterval(() => router.reload({ only: ['deals'], preserveScroll: true }), 30000);
        return () => clearInterval(interval);
    }, []);

    const stats = useMemo(() => {
        const all = deals.total || 0;
        const inProgress = deals.data?.filter(d => d.status === 'In Progress').length || 0;
        const priority = deals.data?.filter(d => d.is_priority && d.status !== 'Closed').length || 0;
        const slaOverdue = deals.data?.filter(d => isSlaOverdue(d)).length || 0;
        return { all, inProgress, priority, slaOverdue };
    }, [deals]);

    const requestNotificationPermission = () => {
        if ('Notification' in window) {
            Notification.requestPermission().then(p => setNotifEnabled(p === 'granted'));
        }
    };

    return (
        <MainLayout title="Сделки">
            <Head title="Сделки" />

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <StatCard icon={Briefcase} label="Всего сделок" value={stats.all} color="indigo" delay={0} />
                <StatCard icon={Hourglass} label="В работе" value={stats.inProgress} color="amber" delay={60} />
                <StatCard icon={AlertCircle} label="Приоритетных" value={stats.priority} color="rose" delay={120} />
                <StatCard icon={Timer} label="Просрочено SLA" value={stats.slaOverdue} color="violet" delay={180} />
            </div>

            {/* Filters */}
            <Filters filters={filters} statuses={statuses} managers={managers} isAdmin={isAdmin}
                onFilter={(f) => router.get('/deals', f, { preserveState: true, preserveScroll: true })} />

            {/* Header */}
            <div className="flex items-center justify-between mb-5">
                <p className="text-sm text-slate-500">
                    Показано <span className="font-semibold text-slate-700">{deals.data?.length || 0}</span> из{' '}
                    <span className="font-semibold text-slate-700">{deals.total || 0}</span>
                </p>
                <button onClick={requestNotificationPermission}
                    className={`inline-flex items-center gap-2 px-4 py-2 text-xs font-medium rounded-xl transition-all duration-300
                        ${notifEnabled ? 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/20' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'}`}>
                    {notifEnabled ? <Bell className="w-4 h-4" strokeWidth={1.5} /> : <BellOff className="w-4 h-4" strokeWidth={1.5} />}
                    {notifEnabled ? 'Уведомления вкл.' : 'Включить'}
                </button>
            </div>

            {/* Deals List */}
            <div className="space-y-4">
                {deals.data?.length === 0 ? (
                    <div className="card p-16 text-center animate-fade-in">
                        <div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-100 flex items-center justify-center">
                            <FileText className="w-8 h-8 text-slate-300" strokeWidth={1.5} />
                        </div>
                        <h3 className="text-lg font-semibold text-slate-700 mb-2">Сделки не найдены</h3>
                        <p className="text-sm text-slate-500">Попробуйте изменить параметры фильтрации</p>
                    </div>
                ) : (
                    deals.data?.map((deal, i) => <DealRow key={deal.id} deal={deal} index={i} />)
                )}
            </div>

            {/* Pagination */}
            {deals.last_page > 1 && (
                <div className="mt-8 flex items-center justify-center gap-2">
                    {deals.links.map((link, i) => (
                        <Link key={i} href={link.url || '#'} preserveScroll
                            className={`px-4 py-2.5 text-sm font-medium rounded-xl transition-all duration-300
                                ${link.active ? 'gradient-indigo text-white shadow-lg shadow-indigo-500/30' : 'text-slate-600 hover:bg-slate-100'}
                                ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            )}
        </MainLayout>
    );
}
