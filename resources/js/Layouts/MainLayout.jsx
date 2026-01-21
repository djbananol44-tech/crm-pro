import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { 
    LayoutDashboard, Settings, LogOut, Bell, 
    ChevronDown, X, CheckCircle, AlertCircle, Menu, Sparkles, Shield, User, Home
} from 'lucide-react';

// Compact Sidebar Icon Link
function SidebarIcon({ href, icon: Icon, label, active, external }) {
    const Component = external ? 'a' : Link;
    const props = external ? { href, target: '_self' } : { href };
    
    return (
        <Component
            {...props}
            className={`
                group relative w-12 h-12 min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center rounded-xl transition-all duration-300
                ${active 
                    ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30' 
                    : 'text-zinc-500 hover:bg-white/5 hover:text-white'
                }
            `}
        >
            <Icon className="w-5 h-5" strokeWidth={1.5} />
            
            {/* Tooltip */}
            <span className="absolute left-full ml-3 px-3 py-1.5 text-xs font-medium text-white bg-zinc-800 rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 whitespace-nowrap z-50 shadow-xl">
                {label}
            </span>
        </Component>
    );
}

// Bottom Navigation Item (Mobile)
function BottomNavItem({ href, icon: Icon, label, active, external, onClick }) {
    const Component = onClick ? 'button' : (external ? 'a' : Link);
    const props = onClick ? { onClick } : (external ? { href, target: '_self' } : { href });
    
    return (
        <Component
            {...props}
            className={`
                flex flex-col items-center justify-center gap-1 py-2 px-3 min-w-[4rem] min-h-[2.75rem] transition-all duration-300
                ${active 
                    ? 'text-indigo-400' 
                    : 'text-zinc-500'
                }
            `}
        >
            <Icon className="w-5 h-5" strokeWidth={1.5} />
            <span className="text-[10px] font-medium">{label}</span>
        </Component>
    );
}

// Flash Message Component
function FlashMessage({ type, message, onClose }) {
    const isSuccess = type === 'success';
    
    return (
        <div className={`
            fixed top-4 left-4 right-4 md:top-6 md:right-6 md:left-auto md:max-w-md z-[100] flex items-center gap-3 px-4 md:px-5 py-3 md:py-4 rounded-xl md:rounded-2xl animate-in
            backdrop-blur-xl border
            ${isSuccess 
                ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-300' 
                : 'bg-rose-500/20 border-rose-500/30 text-rose-300'
            }
        `}>
            {isSuccess ? (
                <CheckCircle className="w-5 h-5 flex-shrink-0" strokeWidth={1.5} />
            ) : (
                <AlertCircle className="w-5 h-5 flex-shrink-0" strokeWidth={1.5} />
            )}
            <p className="text-sm font-medium flex-1 line-clamp-2">{message}</p>
            <button onClick={onClose} className="p-2 hover:bg-white/10 rounded-lg transition-colors min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                <X className="w-4 h-4" strokeWidth={1.5} />
            </button>
        </div>
    );
}

export default function MainLayout({ children, title }) {
    const { auth, flash } = usePage().props;
    const [showFlash, setShowFlash] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const currentPath = usePage().url;

    useEffect(() => {
        if (flash?.success || flash?.error) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Close mobile menu on route change
    useEffect(() => {
        setMobileMenuOpen(false);
    }, [currentPath]);

    const handleLogout = () => {
        router.post('/logout');
    };

    const isActive = (path) => currentPath.startsWith(path);

    return (
        <div className="min-h-screen min-h-[100dvh] bg-[#0a0a0b]">
            {/* Minimal Sidebar - Desktop */}
            <aside className="fixed left-0 top-0 bottom-0 w-20 hidden lg:flex flex-col items-center py-6 z-40 border-r border-white/5 bg-[#0a0a0b]/80 backdrop-blur-xl">
                {/* Logo */}
                <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 mb-8">
                    <Sparkles className="w-6 h-6 text-white" strokeWidth={1.5} />
                </div>

                {/* Navigation */}
                <nav className="flex-1 flex flex-col items-center gap-2">
                    <SidebarIcon 
                        href="/deals" 
                        icon={LayoutDashboard} 
                        label="Сделки" 
                        active={isActive('/deals')} 
                    />
                    
                    {auth.user?.isAdmin && (
                        <SidebarIcon 
                            href="/admin" 
                            icon={Shield} 
                            label="Админ-панель" 
                            external
                        />
                    )}
                </nav>

                {/* User Avatar */}
                <div className="relative mt-auto">
                    <button
                        onClick={() => setUserMenuOpen(!userMenuOpen)}
                        className="w-12 h-12 min-w-[2.75rem] min-h-[2.75rem] rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-800 flex items-center justify-center text-white font-semibold hover:ring-2 hover:ring-indigo-500/50 transition-all duration-300"
                    >
                        {auth.user?.name?.charAt(0).toUpperCase()}
                    </button>

                    {/* User Dropdown */}
                    {userMenuOpen && (
                        <>
                            <div className="fixed inset-0 z-40" onClick={() => setUserMenuOpen(false)} />
                            <div className="absolute left-full bottom-0 ml-3 w-56 bg-zinc-900/95 backdrop-blur-xl rounded-2xl border border-white/10 py-2 z-50 shadow-2xl animate-in">
                                <div className="px-4 py-3 border-b border-white/5">
                                    <p className="text-sm font-semibold text-white">{auth.user?.name}</p>
                                    <p className="text-xs text-zinc-500">{auth.user?.email}</p>
                                </div>
                                <button
                                    onClick={handleLogout}
                                    className="flex items-center gap-2 w-full px-4 py-3 text-sm text-rose-400 hover:bg-rose-500/10 transition-all min-h-[2.75rem]"
                                >
                                    <LogOut className="w-4 h-4" strokeWidth={1.5} />
                                    Выйти
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </aside>

            {/* Mobile Header */}
            <header className="lg:hidden fixed top-0 left-0 right-0 z-40 bg-[#0a0a0b]/90 backdrop-blur-xl border-b border-white/5 safe-top">
                <div className="flex items-center justify-between h-14 px-4">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                            <Sparkles className="w-4 h-4 text-white" strokeWidth={1.5} />
                        </div>
                        <span className="text-base font-bold text-white">CRM Pro</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <button className="relative p-2 text-zinc-500 hover:text-white hover:bg-white/5 rounded-lg transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                            <Bell className="w-5 h-5" strokeWidth={1.5} />
                            <span className="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full" />
                        </button>
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className={`hamburger ${mobileMenuOpen ? 'open' : ''}`}
                        >
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    </div>
                </div>
            </header>

            {/* Mobile Menu Overlay */}
            <div className={`mobile-overlay ${mobileMenuOpen ? 'open' : ''}`} onClick={() => setMobileMenuOpen(false)} />

            {/* Mobile Slide-in Menu */}
            <div className={`fixed top-14 left-0 right-0 z-50 lg:hidden bg-zinc-900/95 backdrop-blur-xl border-b border-white/10 transition-all duration-300 ${mobileMenuOpen ? 'translate-y-0 opacity-100' : '-translate-y-full opacity-0 pointer-events-none'}`}>
                <nav className="p-4 space-y-2">
                    <Link 
                        href="/deals"
                        className={`flex items-center gap-3 px-4 py-3 rounded-xl transition-all min-h-[2.75rem] ${isActive('/deals') ? 'bg-indigo-500/20 text-indigo-300' : 'text-zinc-400 hover:bg-white/5'}`}
                    >
                        <LayoutDashboard className="w-5 h-5" strokeWidth={1.5} />
                        Сделки
                    </Link>
                    {auth.user?.isAdmin && (
                        <a 
                            href="/admin"
                            className="flex items-center gap-3 px-4 py-3 rounded-xl text-zinc-400 hover:bg-white/5 transition-all min-h-[2.75rem]"
                        >
                            <Shield className="w-5 h-5" strokeWidth={1.5} />
                            Админ-панель
                        </a>
                    )}
                </nav>
                <div className="p-4 pt-0 border-t border-white/5 mt-2">
                    <div className="flex items-center gap-3 px-4 py-3 mb-2">
                        <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-800 flex items-center justify-center text-white font-semibold">
                            {auth.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <p className="text-sm font-medium text-white">{auth.user?.name}</p>
                            <p className="text-xs text-zinc-500">{auth.user?.role === 'admin' ? 'Админ' : 'Менеджер'}</p>
                        </div>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="flex items-center gap-3 w-full px-4 py-3 text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all min-h-[2.75rem]"
                    >
                        <LogOut className="w-5 h-5" strokeWidth={1.5} />
                        Выйти
                    </button>
                </div>
            </div>

            {/* Bottom Navigation - Mobile */}
            <nav className="bottom-nav lg:hidden">
                <BottomNavItem 
                    href="/deals" 
                    icon={Home} 
                    label="Главная" 
                    active={isActive('/deals') && currentPath === '/deals'} 
                />
                <BottomNavItem 
                    href="/deals" 
                    icon={LayoutDashboard} 
                    label="Сделки" 
                    active={isActive('/deals')} 
                />
                {auth.user?.isAdmin && (
                    <BottomNavItem 
                        href="/admin" 
                        icon={Shield} 
                        label="Админ" 
                        external
                    />
                )}
                <BottomNavItem 
                    icon={User} 
                    label="Профиль" 
                    onClick={() => setMobileMenuOpen(true)}
                />
            </nav>

            {/* Main Content */}
            <main className="lg:pl-20 pt-14 lg:pt-0 pb-20 lg:pb-0 min-h-screen min-h-[100dvh]">
                {/* Page Header - Desktop only */}
                {title && (
                    <div className="hidden lg:block sticky top-0 z-30 bg-[#0a0a0b]/80 backdrop-blur-xl border-b border-white/5">
                        <div className="flex items-center justify-between h-16 px-6">
                            <h1 className="text-xl font-bold text-white">{title}</h1>
                            
                            <div className="flex items-center gap-3">
                                <button className="relative p-2.5 text-zinc-500 hover:text-white hover:bg-white/5 rounded-xl transition-all min-w-[2.75rem] min-h-[2.75rem] flex items-center justify-center">
                                    <Bell className="w-5 h-5" strokeWidth={1.5} />
                                    <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full" />
                                </button>
                                
                                <div className="flex items-center gap-3 pl-3 border-l border-white/5">
                                    <div className="text-right">
                                        <p className="text-sm font-medium text-white">{auth.user?.name}</p>
                                        <p className="text-xs text-zinc-500">
                                            {auth.user?.role === 'admin' ? 'Админ' : 'Менеджер'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Page Content */}
                <div className="p-4 md:p-6">
                    {children}
                </div>
            </main>

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
