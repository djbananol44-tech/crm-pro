import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { 
    LayoutDashboard, Settings, LogOut, Bell, 
    ChevronDown, X, CheckCircle, AlertCircle, Menu, Briefcase, Sparkles
} from 'lucide-react';

// Sidebar Link Component
function SidebarLink({ href, icon: Icon, children, active }) {
    return (
        <Link
            href={href}
            className={`
                flex items-center gap-3 px-4 py-3.5 text-sm font-medium rounded-xl mx-3 transition-all duration-300
                ${active 
                    ? 'bg-gradient-to-r from-indigo-500/20 to-violet-500/10 text-white border-l-3 border-indigo-400 shadow-lg shadow-indigo-500/10' 
                    : 'text-slate-400 hover:bg-white/5 hover:text-white'
                }
            `}
        >
            <Icon className="w-5 h-5" strokeWidth={1.5} />
            <span>{children}</span>
        </Link>
    );
}

// User Menu Component
function UserMenu({ user, onLogout }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <div className="relative">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-3 w-full px-4 py-3.5 text-left hover:bg-white/5 rounded-xl mx-3 transition-all duration-300"
            >
                <div className="w-11 h-11 rounded-xl gradient-indigo flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <span className="text-white font-semibold">
                        {user?.name?.charAt(0).toUpperCase()}
                    </span>
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-white truncate">{user?.name}</p>
                    <p className="text-xs text-slate-400 truncate">
                        {user?.role === 'admin' ? 'Администратор' : 'Менеджер'}
                    </p>
                </div>
                <ChevronDown className={`w-4 h-4 text-slate-400 transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`} strokeWidth={1.5} />
            </button>

            {isOpen && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setIsOpen(false)} />
                    <div className="absolute bottom-full left-3 right-3 mb-2 bg-slate-800/95 backdrop-blur-xl rounded-2xl border border-slate-700/50 py-2 z-50 animate-scale-in shadow-xl">
                        <div className="px-4 py-3 border-b border-slate-700/50">
                            <p className="text-xs text-slate-400">{user?.email}</p>
                        </div>
                        <button
                            onClick={onLogout}
                            className="flex items-center gap-2.5 w-full px-4 py-3 text-sm text-rose-400 hover:bg-rose-500/10 transition-all duration-300"
                        >
                            <LogOut className="w-4 h-4" strokeWidth={1.5} />
                            Выйти из системы
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}

// Flash Message Component
function FlashMessage({ type, message, onClose }) {
    const isSuccess = type === 'success';
    
    return (
        <div className={`
            fixed top-6 right-6 z-50 flex items-center gap-3 px-6 py-4 rounded-2xl animate-slide-in
            ${isSuccess 
                ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white shadow-2xl shadow-emerald-500/30' 
                : 'bg-gradient-to-r from-rose-500 to-rose-600 text-white shadow-2xl shadow-rose-500/30'
            }
        `}>
            {isSuccess ? (
                <CheckCircle className="w-5 h-5 flex-shrink-0" strokeWidth={1.5} />
            ) : (
                <AlertCircle className="w-5 h-5 flex-shrink-0" strokeWidth={1.5} />
            )}
            <p className="text-sm font-medium">{message}</p>
            <button onClick={onClose} className="p-1.5 hover:bg-white/20 rounded-xl transition-colors ml-2">
                <X className="w-4 h-4" strokeWidth={1.5} />
            </button>
        </div>
    );
}

export default function MainLayout({ children, title }) {
    const { auth, flash } = usePage().props;
    const [showFlash, setShowFlash] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const currentPath = usePage().url;

    useEffect(() => {
        if (flash?.success || flash?.error) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const handleLogout = () => {
        router.post('/logout');
    };

    const isActive = (path) => currentPath.startsWith(path);

    return (
        <div className="min-h-screen">
            {/* Fixed Sidebar */}
            <aside className={`
                fixed left-0 top-0 bottom-0 w-64 z-40 flex flex-col transition-transform duration-300
                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
                lg:translate-x-0
            `} style={{ background: 'linear-gradient(180deg, #0f172a 0%, #020617 100%)', borderRight: '1px solid rgba(148, 163, 184, 0.1)' }}>
                {/* Logo */}
                <div className="flex items-center gap-3 px-6 py-7 border-b border-slate-800/80">
                    <div className="w-12 h-12 rounded-xl gradient-indigo flex items-center justify-center shadow-lg shadow-indigo-500/30 animate-float">
                        <Sparkles className="w-6 h-6 text-white" strokeWidth={1.5} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">CRM Pro</h1>
                        <p className="text-[10px] text-indigo-400 uppercase tracking-widest font-semibold">AI Business Suite</p>
                    </div>
                </div>

                {/* Navigation */}
                <nav className="flex-1 py-6 space-y-1 overflow-y-auto scrollbar-thin">
                    <div className="px-6 mb-5">
                        <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">Навигация</p>
                    </div>
                    
                    <SidebarLink href="/deals" icon={LayoutDashboard} active={isActive('/deals')}>
                        Сделки
                    </SidebarLink>
                    
                    {auth.user?.isAdmin && (
                        <>
                            <div className="px-6 mt-10 mb-5">
                                <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">Администратор</p>
                            </div>
                            <a 
                                href="/admin" 
                                className="flex items-center gap-3 px-4 py-3.5 text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white rounded-xl mx-3 transition-all duration-300"
                            >
                                <Settings className="w-5 h-5" strokeWidth={1.5} />
                                <span>Админ-панель</span>
                            </a>
                        </>
                    )}
                </nav>

                {/* User Section */}
                <div className="border-t border-slate-800/80 py-4">
                    <UserMenu user={auth.user} onLogout={handleLogout} />
                </div>
            </aside>

            {/* Mobile Sidebar Overlay */}
            {sidebarOpen && (
                <div 
                    className="fixed inset-0 bg-black/60 backdrop-blur-sm z-30 lg:hidden" 
                    onClick={() => setSidebarOpen(false)} 
                />
            )}

            {/* Main Content */}
            <div className="lg:pl-64 min-h-screen flex flex-col">
                {/* Top Bar */}
                <header className="sticky top-0 z-20 glass">
                    <div className="flex items-center justify-between h-[72px] px-6">
                        {/* Mobile Menu Button */}
                        <button 
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            className="lg:hidden p-2.5 -ml-2 text-slate-400 hover:text-white hover:bg-slate-800/50 rounded-xl transition-all duration-300"
                        >
                            <Menu className="w-5 h-5" strokeWidth={1.5} />
                        </button>

                        {/* Page Title */}
                        {title && (
                            <h2 className="text-lg font-semibold text-white hidden sm:block">{title}</h2>
                        )}

                        {/* Right Section */}
                        <div className="flex items-center gap-4 ml-auto">
                            {/* Notifications */}
                            <button className="relative p-3 text-slate-400 hover:text-white hover:bg-slate-800/50 rounded-xl transition-all duration-300">
                                <Bell className="w-5 h-5" strokeWidth={1.5} />
                                <span className="absolute top-2 right-2 w-2.5 h-2.5 bg-rose-500 rounded-full ring-2 ring-slate-900" />
                            </button>

                            {/* User Avatar (Desktop) */}
                            <div className="hidden lg:flex items-center gap-4 pl-4 border-l border-slate-700/50">
                                <div className="text-right">
                                    <p className="text-sm font-semibold text-white">{auth.user?.name}</p>
                                    <p className="text-xs text-slate-400">
                                        {auth.user?.role === 'admin' ? 'Админ' : 'Менеджер'}
                                    </p>
                                </div>
                                <div className="w-11 h-11 rounded-xl gradient-indigo flex items-center justify-center shadow-lg shadow-indigo-500/20">
                                    <span className="text-white font-semibold">
                                        {auth.user?.name?.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-6">
                    {children}
                </main>
            </div>

            {/* Flash Messages */}
            {showFlash && (flash?.success || flash?.error) && (
                <FlashMessage
                    type={flash.success ? 'success' : 'error'}
                    message={flash.success || flash.error}
                    onClose={() => setShowFlash(false)}
                />
            )}
        </div>
    );
}
