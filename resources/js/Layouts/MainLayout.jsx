/* ═══════════════════════════════════════════════════════════════════════════
   🏗️ JGGL CRM — Main Layout
   ═══════════════════════════════════════════════════════════════════════════
   
   Responsive breakpoints (synced with Filament):
   - Mobile: < 768px (Bottom nav + hamburger)
   - Tablet: 768px - 1023px (Sidebar + hamburger)
   - Desktop: >= 1024px (Full sidebar)
   
   Touch targets: min 44px everywhere
   ═══════════════════════════════════════════════════════════════════════════ */

import { Link, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { 
    LayoutDashboard, LogOut, Bell, X, CheckCircle, AlertCircle, 
    Menu, Sparkles, Shield, User, Home
} from 'lucide-react';
import { Avatar } from '../Components/UI';

/* ─────────────────────────────────────────────────────────────────────────────
   Sidebar Icon Link
   ───────────────────────────────────────────────────────────────────────────── */
function SidebarIcon({ href, icon: Icon, label, active, external }) {
    const Component = external ? 'a' : Link;
    const props = external ? { href, target: '_self' } : { href };
    
    return (
        <Component
            {...props}
            className={`
                group relative flex items-center justify-center 
                w-12 h-12 min-w-touch min-h-touch 
                rounded-xl transition-all duration-200
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-dark-base
                ${active 
                    ? 'bg-gradient-to-br from-primary-500 to-accent-500 text-white shadow-glow-primary' 
                    : 'text-zinc-500 hover:bg-glass hover:text-white'
                }
            `}
        >
            <Icon className="w-5 h-5" strokeWidth={1.5} />
            
            {/* Tooltip — Desktop only */}
            <span className="
                absolute left-full ml-3 px-3 py-1.5 
                text-xs font-medium text-white bg-zinc-800 
                rounded-lg whitespace-nowrap z-tooltip
                opacity-0 invisible group-hover:opacity-100 group-hover:visible 
                transition-all duration-200 shadow-xl
                hidden lg:block
            ">
                {label}
            </span>
        </Component>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   Bottom Navigation Item (Mobile)
   ───────────────────────────────────────────────────────────────────────────── */
function BottomNavItem({ href, icon: Icon, label, active, external, onClick }) {
    const Component = onClick ? 'button' : (external ? 'a' : Link);
    const props = onClick ? { onClick, type: 'button' } : (external ? { href, target: '_self' } : { href });
    
    return (
        <Component
            {...props}
            className={`
                flex flex-col items-center justify-center gap-1 
                py-2 px-3 min-w-[4rem] min-h-touch
                transition-colors duration-200
                ${active ? 'text-primary-400' : 'text-zinc-500'}
            `}
        >
            <Icon className="w-5 h-5" strokeWidth={1.5} />
            <span className="text-[10px] font-medium">{label}</span>
        </Component>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   Flash Message Toast
   ───────────────────────────────────────────────────────────────────────────── */
function FlashMessage({ type, message, onClose }) {
    const isSuccess = type === 'success';
    
    return (
        <div className={`
            fixed z-toast
            top-4 left-4 right-4 
            md:top-6 md:right-6 md:left-auto md:max-w-md 
            flex items-center gap-3 
            px-4 py-3 md:px-5 md:py-4 
            rounded-xl md:rounded-2xl 
            backdrop-blur-xl border
            animate-fade-in
            ${isSuccess 
                ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-300' 
                : 'bg-rose-500/20 border-rose-500/30 text-rose-300'
            }
        `}>
            {isSuccess ? (
                <CheckCircle className="w-5 h-5 shrink-0" strokeWidth={1.5} />
            ) : (
                <AlertCircle className="w-5 h-5 shrink-0" strokeWidth={1.5} />
            )}
            <p className="text-sm font-medium flex-1 line-clamp-2">{message}</p>
            <button 
                onClick={onClose} 
                className="p-2 hover:bg-line/10 rounded-lg transition-colors min-w-touch min-h-touch flex items-center justify-center"
            >
                <X className="w-4 h-4" strokeWidth={1.5} />
            </button>
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   🏠 MAIN LAYOUT
   ═══════════════════════════════════════════════════════════════════════════ */

export default function MainLayout({ children, title }) {
    const { auth, flash } = usePage().props;
    const [showFlash, setShowFlash] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const currentPath = usePage().url;

    // Flash message auto-hide
    useEffect(() => {
        if (flash?.success || flash?.error) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Close menus on route change
    useEffect(() => {
        setMobileMenuOpen(false);
        setUserMenuOpen(false);
    }, [currentPath]);

    const handleLogout = () => {
        router.post('/logout');
    };

    const isActive = (path) => currentPath.startsWith(path);

    return (
        <div className="min-h-screen min-h-[100dvh] bg-dark-base">
            {/* ═══════════════════════════════════════════════════════════════
               Desktop Sidebar (lg+)
               ═══════════════════════════════════════════════════════════════ */}
            <aside className="
                fixed left-0 top-0 bottom-0 w-20 
                hidden lg:flex flex-col items-center py-6 z-fixed
                border-r border-border-subtle
                bg-dark-base/80 backdrop-blur-xl
            ">
                {/* Logo */}
                <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center shadow-glow-primary mb-8">
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

                {/* User Avatar & Menu */}
                <div className="relative mt-auto">
                    <button
                        onClick={() => setUserMenuOpen(!userMenuOpen)}
                        className="
                            w-12 h-12 min-w-touch min-h-touch 
                            rounded-xl bg-gradient-to-br from-zinc-700 to-zinc-800 
                            flex items-center justify-center text-white font-semibold 
                            hover:ring-2 hover:ring-primary-500/50 
                            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500
                            transition-all duration-200
                        "
                    >
                        {auth.user?.name?.charAt(0).toUpperCase()}
                    </button>

                    {/* Dropdown */}
                    {userMenuOpen && (
                        <>
                            <div className="fixed inset-0 z-overlay" onClick={() => setUserMenuOpen(false)} />
                            <div className="
                                absolute left-full bottom-0 ml-3 w-56 
                                bg-dark-elevated/95 backdrop-blur-xl 
                                rounded-2xl border border-border 
                                py-2 z-modal shadow-2xl 
                                animate-fade-in
                            ">
                                <div className="px-4 py-3 border-b border-border-subtle">
                                    <p className="text-sm font-semibold text-white">{auth.user?.name}</p>
                                    <p className="text-xs text-zinc-500">{auth.user?.email}</p>
                                </div>
                                <button
                                    onClick={handleLogout}
                                    className="
                                        flex items-center gap-2 w-full px-4 py-3 
                                        text-sm text-rose-400 hover:bg-rose-500/10 
                                        transition-colors min-h-touch
                                    "
                                >
                                    <LogOut className="w-4 h-4" strokeWidth={1.5} />
                                    Выйти
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </aside>

            {/* ═══════════════════════════════════════════════════════════════
               Mobile Header (< lg)
               ═══════════════════════════════════════════════════════════════ */}
            <header className="
                lg:hidden fixed top-0 left-0 right-0 z-fixed
                bg-dark-base/90 backdrop-blur-xl 
                border-b border-border-subtle
                safe-top
            ">
                <div className="flex items-center justify-between h-14 px-4">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center shadow-glow-primary">
                            <Sparkles className="w-4 h-4 text-white" strokeWidth={1.5} />
                        </div>
                        <span className="text-base font-bold text-white">JGGL CRM</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <button className="
                            relative p-2 text-zinc-500 hover:text-white hover:bg-glass 
                            rounded-lg transition-colors min-w-touch min-h-touch 
                            flex items-center justify-center
                        ">
                            <Bell className="w-5 h-5" strokeWidth={1.5} />
                            <span className="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full" />
                        </button>
                        
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="hamburger min-w-touch min-h-touch"
                            aria-label="Меню"
                        >
                            <span className={mobileMenuOpen ? 'rotate-45 translate-y-[5px]' : ''} />
                            <span className={mobileMenuOpen ? 'opacity-0' : ''} />
                            <span className={mobileMenuOpen ? '-rotate-45 -translate-y-[5px]' : ''} />
                        </button>
                    </div>
                </div>
            </header>

            {/* Mobile Menu Overlay */}
            <div 
                className={`mobile-overlay ${mobileMenuOpen ? 'open' : ''}`} 
                onClick={() => setMobileMenuOpen(false)} 
            />

            {/* Mobile Slide Menu */}
            <div className={`
                fixed top-14 left-0 right-0 z-modal lg:hidden
                bg-dark-elevated/95 backdrop-blur-xl 
                border-b border-border
                transition-all duration-300
                ${mobileMenuOpen ? 'translate-y-0 opacity-100' : '-translate-y-full opacity-0 pointer-events-none'}
            `}>
                <nav className="p-4 space-y-2">
                    <Link 
                        href="/deals"
                        className={`
                            flex items-center gap-3 px-4 py-3 rounded-xl 
                            transition-colors min-h-touch
                            ${isActive('/deals') 
                                ? 'bg-primary-500/20 text-primary-300' 
                                : 'text-zinc-400 hover:bg-glass'
                            }
                        `}
                    >
                        <LayoutDashboard className="w-5 h-5" strokeWidth={1.5} />
                        Сделки
                    </Link>
                    
                    {auth.user?.isAdmin && (
                        <a 
                            href="/admin"
                            className="flex items-center gap-3 px-4 py-3 rounded-xl text-zinc-400 hover:bg-glass transition-colors min-h-touch"
                        >
                            <Shield className="w-5 h-5" strokeWidth={1.5} />
                            Админ-панель
                        </a>
                    )}
                </nav>
                
                <div className="p-4 pt-0 border-t border-border-subtle mt-2">
                    <div className="flex items-center gap-3 px-4 py-3 mb-2">
                        <Avatar name={auth.user?.name} size="default" />
                        <div>
                            <p className="text-sm font-medium text-white">{auth.user?.name}</p>
                            <p className="text-xs text-zinc-500">
                                {auth.user?.role === 'admin' ? 'Админ' : 'Менеджер'}
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="
                            flex items-center gap-3 w-full px-4 py-3 
                            text-rose-400 hover:bg-rose-500/10 
                            rounded-xl transition-colors min-h-touch
                        "
                    >
                        <LogOut className="w-5 h-5" strokeWidth={1.5} />
                        Выйти
                    </button>
                </div>
            </div>

            {/* ═══════════════════════════════════════════════════════════════
               Bottom Navigation (Mobile only)
               ═══════════════════════════════════════════════════════════════ */}
            <nav className="bottom-nav lg:hidden">
                <BottomNavItem 
                    href="/deals" 
                    icon={Home} 
                    label="Главная" 
                    active={currentPath === '/deals'} 
                />
                <BottomNavItem 
                    href="/deals" 
                    icon={LayoutDashboard} 
                    label="Сделки" 
                    active={isActive('/deals') && currentPath !== '/deals'} 
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

            {/* ═══════════════════════════════════════════════════════════════
               Main Content Area
               ═══════════════════════════════════════════════════════════════ */}
            <main className="
                lg:pl-20 
                pt-14 lg:pt-0 
                pb-20 lg:pb-0 
                min-h-screen min-h-[100dvh]
            ">
                {/* Desktop Page Header */}
                {title && (
                    <div className="
                        hidden lg:block sticky top-0 z-sticky
                        bg-dark-base/80 backdrop-blur-xl 
                        border-b border-border-subtle
                    ">
                        <div className="flex items-center justify-between h-16 px-6">
                            <h1 className="text-xl font-bold text-white">{title}</h1>
                            
                            <div className="flex items-center gap-3">
                                <button className="
                                    relative p-2.5 text-zinc-500 hover:text-white hover:bg-glass 
                                    rounded-xl transition-colors min-w-touch min-h-touch 
                                    flex items-center justify-center
                                ">
                                    <Bell className="w-5 h-5" strokeWidth={1.5} />
                                    <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full" />
                                </button>
                                
                                <div className="flex items-center gap-3 pl-3 border-l border-border-subtle">
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

            {/* ═══════════════════════════════════════════════════════════════
               Flash Messages
               ═══════════════════════════════════════════════════════════════ */}
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
