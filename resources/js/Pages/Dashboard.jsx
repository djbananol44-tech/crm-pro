import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import { formatDistanceToNow, isToday, parseISO, differenceInMinutes } from 'date-fns';
import { ru } from 'date-fns/locale';
import { 
    Search, Filter, Bell, BellOff, Clock, User, Flame, Zap,
    MessageSquare, ChevronRight, RefreshCw, 
    FileText, Briefcase, CheckCircle, Hourglass, Timer, Star, AlertCircle, TrendingUp, X
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

// === Bento Stats Card ===
function BentoStat({ icon: Icon, label, value, trend, color, size = 'normal' }) {
    const colors = {
        indigo: { bg: 'from-indigo-500/20 to-violet-500/10', icon: 'from-indigo-500 to-violet-600', text: 'text-indigo-400' },
        emerald: { bg: 'from-emerald-500/20 to-teal-500/10', icon: 'from-emerald-500 to-teal-600', text: 'text-emerald-400' },
        amber: { bg: 'from-amber-500/20 to-orange-500/10', icon: 'from-amber-500 to-orange-600', text: 'text-amber-400' },
        rose: { bg: 'from-rose-500/20 to-pink-500/10', icon: 'from-rose-500 to-pink-600', text: 'text-rose-400' },
    };
    const c = colors[color] || colors.indigo;

    return (
        <div className={`glass-card p-4 md:p-6 ${size === 'large' ? 'bento-item-large' : ''}`}>
            <div className="flex items-start justify-between mb-2 md:mb-4">
                <div className={`w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl bg-gradient-to-br ${c.icon} flex items-center justify-center shadow-lg`}>
                    <Icon className="w-5 h-5 md:w-6 md:h-6 text-white" strokeWidth={1.5} />
                </div>
                {trend && (
                    <span className={`hidden md:flex items-center gap-1 text-xs font-medium ${c.text}`}>
                        <TrendingUp className="w-3 h-3" strokeWidth={2} />
                        {trend}
                    </span>
                )}
            </div>
            <p className="text-2xl md:text-3xl font-bold text-white mb-0.5 md:mb-1">{value}</p>
            <p className="text-xs md:text-sm text-zinc-500 truncate">{label}</p>
        </div>
    );
}

// === Lead Score Badge ===
function LeadScore({ score }) {
    if (!score) return null;
    const isHot = score > 80;
    
    return (
        <div className={`ai-score ${isHot ? 'ai-score-hot' : ''}`}>
            {isHot && <Zap className="w-4 h-4 text-amber-400 absolute -top-1 -right-1" strokeWidth={2} />}
            <span className={isHot ? 'text-amber-400' : 'text-violet-400'}>{score}</span>
        </div>
    );
}

// === Manager Rating ===
function ManagerRating({ rating }) {
    if (!rating) return null;
    return (
        <div className="hidden md:flex items-center gap-0.5" title={`Оценка: ${rating}/5`}>
            {[1, 2, 3, 4, 5].map(i => (
                <Star key={i} className={`w-3 h-3 ${i <= rating ? 'text-amber-400 fill-amber-400' : 'text-zinc-600'}`} strokeWidth={1.5} />
            ))}
        </div>
    );
}

// === Status Pill ===
function StatusPill({ status, dealId }) {
    const [isOpen, setIsOpen] = useState(false);
    const [updating, setUpdating] = useState(false);
    const ref = useRef(null);

    const config = {
        'New': { label: 'Новая', class: 'badge badge-new' },
        'In Progress': { label: 'В работе', class: 'badge badge-progress' },
        'Closed': { label: 'Закрыта', class: 'badge badge-closed' },
    };

    const current = config[status] || config['New'];

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
            <button onClick={() => setIsOpen(!isOpen)} disabled={updating} className={`${current.class} min-h-[2.75rem] px-3`}>
                {updating && <RefreshCw className="w-3 h-3 animate-spin" strokeWidth={1.5} />}
                <span className="hidden sm:inline">{current.label}</span>
                <span className="sm:hidden">{current.label.slice(0, 3)}</span>
            </button>
            {isOpen && (
                <div className="absolute z-50 mt-2 w-36 bg-zinc-900/95 backdrop-blur-xl rounded-xl border border-white/10 py-1 right-0 shadow-2xl animate-in">
                    {Object.entries(config).map(([key, cfg]) => (
                        <button key={key} onClick={() => handleChange(key)}
                            className={`w-full px-3 py-2.5 text-xs text-left transition-all min-h-[2.75rem] ${key === status ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white hover:bg-white/5'}`}>
                            {cfg.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// === Mobile Filters (Sheet) ===
function MobileFilters({ isOpen, onClose, filters, statuses, managers, isAdmin, onFilter }) {
    const [local, setLocal] = useState(filters);

    const handleApply = () => {
        onFilter(local);
        onClose();
    };

    const handleReset = () => {
        setLocal({});
        onFilter({});
        onClose();
    };

    if (!isOpen) return null;

    return (
        <>
            <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50" onClick={onClose} />
            <div className="fixed bottom-0 left-0 right-0 z-50 bg-zinc-900/95 backdrop-blur-xl rounded-t-3xl border-t border-white/10 p-4 pb-8 animate-in safe-bottom">
                <div className="w-10 h-1 bg-zinc-700 rounded-full mx-auto mb-4" />
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-white">Фильтры</h3>
                    <button onClick={onClose} className="p-2 text-zinc-400 hover:text-white min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                        <X className="w-5 h-5" strokeWidth={1.5} />
                    </button>
                </div>
                
                <div className="space-y-4">
                    <div>
                        <label className="block text-xs text-zinc-500 uppercase tracking-wider mb-2">Поиск</label>
                        <input type="text" className="input-premium" placeholder="Имя, PSID..."
                            value={local.search || ''} onChange={(e) => setLocal({ ...local, search: e.target.value })} />
                    </div>
                    <div>
                        <label className="block text-xs text-zinc-500 uppercase tracking-wider mb-2">Статус</label>
                        <select className="input-premium" value={local.status || ''} onChange={(e) => setLocal({ ...local, status: e.target.value })}>
                            <option value="">Все статусы</option>
                            {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                    </div>
                    {isAdmin && (
                        <div>
                            <label className="block text-xs text-zinc-500 uppercase tracking-wider mb-2">Менеджер</label>
                            <select className="input-premium" value={local.manager_id || ''} onChange={(e) => setLocal({ ...local, manager_id: e.target.value })}>
                                <option value="">Все менеджеры</option>
                                {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </div>
                    )}
                </div>

                <div className="flex gap-3 mt-6">
                    <button onClick={handleReset} className="btn-ghost flex-1">Сбросить</button>
                    <button onClick={handleApply} className="btn-premium flex-1">Применить</button>
                </div>
            </div>
        </>
    );
}

// === Desktop Filters ===
function DesktopFilters({ filters, statuses, managers, isAdmin, onFilter }) {
    const [local, setLocal] = useState(filters);

    return (
        <div className="hidden md:block glass-card-static p-4 md:p-5 mb-4 md:mb-6">
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex-1 min-w-[200px]">
                    <div className="relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500" strokeWidth={1.5} />
                        <input type="text" className="input-premium pl-11" placeholder="Поиск по имени, PSID..."
                            value={local.search || ''} onChange={(e) => setLocal({ ...local, search: e.target.value })}
                            onKeyDown={(e) => e.key === 'Enter' && onFilter(local)} />
                    </div>
                </div>
                <select className="input-premium w-auto" value={local.status || ''} onChange={(e) => setLocal({ ...local, status: e.target.value })}>
                    <option value="">Все статусы</option>
                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
                {isAdmin && (
                    <select className="input-premium w-auto" value={local.manager_id || ''} onChange={(e) => setLocal({ ...local, manager_id: e.target.value })}>
                        <option value="">Все менеджеры</option>
                        {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                    </select>
                )}
                <button onClick={() => onFilter(local)} className="btn-premium">
                    <Filter className="w-4 h-4" strokeWidth={1.5} /> Найти
                </button>
                <button onClick={() => { setLocal({}); onFilter({}); }} className="btn-ghost">Сбросить</button>
            </div>
        </div>
    );
}

// === Deal Card ===
function DealCard({ deal, index }) {
    const now = new Date();
    const isReminderDue = deal.reminder_at && new Date(deal.reminder_at) <= now;
    const isUnviewed = !deal.is_viewed;
    const online = isOnline(deal.conversation?.updated_time || deal.updated_at);
    const reminderToday = deal.reminder_at && isToday(parseISO(deal.reminder_at));
    const slaOverdue = isSlaOverdue(deal);
    const isHotLead = deal.ai_score && deal.ai_score > 80;
    const isPriority = deal.is_priority && deal.status !== 'Closed';

    return (
        <Link href={`/deals/${deal.id}`} className="block">
            <div 
                className={`
                    glass-card p-3 md:p-5 animate-in transition-all duration-300 cursor-pointer
                    ${isPriority ? 'ring-1 ring-rose-500/50 badge-priority' : ''}
                    ${slaOverdue && !isPriority ? 'ring-1 ring-amber-500/50' : ''}
                `}
                style={{ animationDelay: `${index * 50}ms` }}
            >
                {/* SLA Indicator */}
                {slaOverdue && (
                    <div className="sla-indicator sla-indicator-warning">
                        <div className="sla-indicator-bar" style={{ width: '100%' }} />
                    </div>
                )}

                <div className="flex items-center gap-3 md:gap-4">
                    {/* Avatar */}
                    <div className="relative flex-shrink-0">
                        <div className={`
                            w-11 h-11 md:w-14 md:h-14 rounded-xl md:rounded-2xl flex items-center justify-center text-white font-semibold text-base md:text-lg
                            ${isPriority ? 'bg-gradient-to-br from-rose-500 to-pink-600' :
                              isHotLead ? 'bg-gradient-to-br from-amber-500 to-orange-600' :
                              isUnviewed ? 'bg-gradient-to-br from-indigo-500 to-violet-600' : 
                              'bg-gradient-to-br from-zinc-600 to-zinc-700'}
                        `}>
                            {deal.contact?.name?.charAt(0)?.toUpperCase() || '?'}
                        </div>
                        {online && <div className="online-dot absolute -bottom-0.5 -right-0.5" />}
                    </div>

                    {/* Info */}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-1.5 md:gap-2 mb-0.5 md:mb-1 flex-wrap">
                            <h3 className={`text-sm md:text-base truncate ${isUnviewed ? 'font-bold text-white' : 'font-medium text-zinc-200'}`}>
                                {deal.contact?.name || 'Без имени'}
                            </h3>
                            {/* Mobile: Show only one badge */}
                            <div className="hidden md:flex items-center gap-1.5 flex-wrap">
                                {isPriority && (
                                    <span className="badge badge-priority">
                                        <AlertCircle className="w-3 h-3" strokeWidth={2} /> ПРИОРИТЕТ
                                    </span>
                                )}
                                {isHotLead && !isPriority && (
                                    <span className="badge" style={{ background: 'rgba(245,158,11,0.15)', color: '#fcd34d', border: '1px solid rgba(245,158,11,0.3)' }}>
                                        <Zap className="w-3 h-3" strokeWidth={2} /> HOT
                                    </span>
                                )}
                                {reminderToday && !slaOverdue && !isPriority && (
                                    <span className="badge badge-progress">
                                        <Flame className="w-3 h-3" strokeWidth={1.5} /> Сегодня
                                    </span>
                                )}
                                {slaOverdue && !isPriority && (
                                    <span className="badge" style={{ background: 'rgba(251,146,60,0.15)', color: '#fdba74', border: '1px solid rgba(251,146,60,0.3)', animation: 'priority-pulse 1.5s infinite' }}>
                                        <Timer className="w-3 h-3" strokeWidth={2} /> SLA
                                    </span>
                                )}
                            </div>
                            {/* Mobile: Single icon indicator */}
                            <div className="md:hidden flex items-center gap-1">
                                {isPriority && <AlertCircle className="w-4 h-4 text-rose-400" strokeWidth={2} />}
                                {isHotLead && !isPriority && <Zap className="w-4 h-4 text-amber-400" strokeWidth={2} />}
                                {slaOverdue && !isPriority && !isHotLead && <Timer className="w-4 h-4 text-orange-400" strokeWidth={2} />}
                                {reminderToday && !slaOverdue && !isPriority && !isHotLead && <Flame className="w-4 h-4 text-amber-400" strokeWidth={1.5} />}
                            </div>
                        </div>
                        <div className="flex items-center gap-2 md:gap-3">
                            <span className="text-[10px] md:text-xs text-zinc-500 font-mono truncate max-w-[80px] md:max-w-none">{deal.contact?.psid}</span>
                            <ManagerRating rating={deal.manager_rating} />
                            {/* Mobile: Show time here */}
                            <span className="md:hidden text-[10px] text-zinc-600">{formatRelativeTime(deal.updated_at)}</span>
                        </div>
                    </div>

                    {/* AI Score - Hidden on mobile */}
                    <div className="hidden md:block">
                        {deal.ai_score && <LeadScore score={deal.ai_score} />}
                    </div>

                    {/* Status */}
                    <div onClick={(e) => e.preventDefault()}>
                        <StatusPill status={deal.status} dealId={deal.id} />
                    </div>

                    {/* Manager - Hidden on mobile */}
                    <div className="hidden lg:flex items-center gap-2 min-w-[100px]">
                        <div className="w-8 h-8 rounded-lg bg-zinc-800 flex items-center justify-center">
                            <User className="w-4 h-4 text-zinc-500" strokeWidth={1.5} />
                        </div>
                        <span className="text-sm text-zinc-400 truncate">{deal.manager?.name || '—'}</span>
                    </div>

                    {/* Time - Hidden on mobile */}
                    <div className="hidden xl:flex items-center gap-2 text-sm text-zinc-500 min-w-[100px]">
                        <Clock className="w-4 h-4" strokeWidth={1.5} />
                        {formatRelativeTime(deal.updated_at)}
                    </div>

                    {/* Arrow */}
                    <ChevronRight className="w-5 h-5 text-zinc-600 flex-shrink-0" strokeWidth={1.5} />
                </div>
            </div>
        </Link>
    );
}

// === Main Component ===
export default function Dashboard({ deals, managers, filters, statuses, isAdmin }) {
    const prevCount = useRef(null);
    const [notifEnabled, setNotifEnabled] = useState(false);
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

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

    const handleFilter = (f) => router.get('/deals', f, { preserveState: true, preserveScroll: true });

    return (
        <MainLayout title="Сделки">
            <Head title="Сделки" />

            {/* Bento Stats Grid */}
            <div className="bento-grid mb-4 md:mb-8">
                <BentoStat icon={Briefcase} label="Всего сделок" value={stats.all} color="indigo" />
                <BentoStat icon={Hourglass} label="В работе" value={stats.inProgress} color="amber" />
                <BentoStat icon={AlertCircle} label="Приоритетных" value={stats.priority} color="rose" />
                <BentoStat icon={Timer} label="Просрочено SLA" value={stats.slaOverdue} color="emerald" />
            </div>

            {/* Mobile Filter Button */}
            <div className="flex md:hidden items-center gap-3 mb-4">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500" strokeWidth={1.5} />
                    <input type="text" className="input-premium pl-10 pr-4" placeholder="Поиск..."
                        value={filters.search || ''} 
                        onChange={(e) => handleFilter({ ...filters, search: e.target.value })} />
                </div>
                <button onClick={() => setMobileFiltersOpen(true)} className="btn-ghost px-3">
                    <Filter className="w-5 h-5" strokeWidth={1.5} />
                </button>
            </div>

            {/* Desktop Filters */}
            <DesktopFilters filters={filters} statuses={statuses} managers={managers} isAdmin={isAdmin} onFilter={handleFilter} />

            {/* Mobile Filters Sheet */}
            <MobileFilters 
                isOpen={mobileFiltersOpen} 
                onClose={() => setMobileFiltersOpen(false)}
                filters={filters} 
                statuses={statuses} 
                managers={managers} 
                isAdmin={isAdmin} 
                onFilter={handleFilter} 
            />

            {/* Header */}
            <div className="flex items-center justify-between mb-3 md:mb-5">
                <p className="text-xs md:text-sm text-zinc-500">
                    Показано <span className="font-medium text-zinc-300">{deals.data?.length || 0}</span> из{' '}
                    <span className="font-medium text-zinc-300">{deals.total || 0}</span>
                </p>
                <button onClick={requestNotificationPermission}
                    className={`btn-ghost text-xs ${notifEnabled ? 'text-emerald-400' : ''}`}>
                    {notifEnabled ? <Bell className="w-4 h-4" strokeWidth={1.5} /> : <BellOff className="w-4 h-4" strokeWidth={1.5} />}
                    <span className="hidden sm:inline">{notifEnabled ? 'Уведомления вкл.' : 'Включить'}</span>
                </button>
            </div>

            {/* Deals List */}
            <div className="space-y-2 md:space-y-3">
                {deals.data?.length === 0 ? (
                    <div className="glass-card-static p-8 md:p-16 text-center">
                        <div className="w-12 h-12 md:w-16 md:h-16 mx-auto mb-3 md:mb-4 rounded-xl md:rounded-2xl bg-zinc-800 flex items-center justify-center">
                            <FileText className="w-6 h-6 md:w-8 md:h-8 text-zinc-600" strokeWidth={1.5} />
                        </div>
                        <h3 className="text-base md:text-lg font-semibold text-zinc-300 mb-1 md:mb-2">Сделки не найдены</h3>
                        <p className="text-xs md:text-sm text-zinc-500">Попробуйте изменить параметры фильтрации</p>
                    </div>
                ) : (
                    deals.data?.map((deal, i) => <DealCard key={deal.id} deal={deal} index={i} />)
                )}
            </div>

            {/* Pagination */}
            {deals.last_page > 1 && (
                <div className="mt-6 md:mt-8 flex items-center justify-center gap-1 md:gap-2 flex-wrap">
                    {deals.links.map((link, i) => (
                        <Link key={i} href={link.url || '#'} preserveScroll
                            className={`px-3 md:px-4 py-2 md:py-2.5 text-xs md:text-sm font-medium rounded-lg md:rounded-xl transition-all duration-300 min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center
                                ${link.active ? 'bg-gradient-to-r from-indigo-500 to-violet-600 text-white shadow-lg' : 'text-zinc-400 hover:bg-white/5 hover:text-white'}
                                ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            )}
        </MainLayout>
    );
}
