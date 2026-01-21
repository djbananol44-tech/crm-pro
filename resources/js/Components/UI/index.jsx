/* ═══════════════════════════════════════════════════════════════════════════
   🎨 JGGL CRM — Unified UI Kit
   ═══════════════════════════════════════════════════════════════════════════
   
   Brand: Onyx Black + Orange
   Design Tokens: resources/css/design-tokens.css
   
   Components:
   - Button (primary/secondary/ghost/danger/success)
   - Input / Textarea / Select
   - Badge
   - Card / StatCard
   - Avatar
   - Skeleton
   - EmptyState
   - Divider
   - OnlineDot
   
   Requirements:
   - Touch targets ≥ 44px (Apple HIG)
   - Focus visible states
   - Loading states
   - Hover/Active/Disabled states
   ═══════════════════════════════════════════════════════════════════════════ */

import { forwardRef } from 'react';
import { Loader2 } from 'lucide-react';

/* ─────────────────────────────────────────────────────────────────────────────
   🔘 BUTTON
   Variants: primary, secondary, ghost, danger, success
   Sizes: sm, default, lg
   States: hover, active, focus, disabled, loading
   ───────────────────────────────────────────────────────────────────────────── */

export const Button = forwardRef(({ 
    children, 
    variant = 'primary', 
    size = 'default',
    isLoading = false,
    disabled = false,
    className = '',
    icon: Icon,
    iconPosition = 'left',
    ...props 
}, ref) => {
    const baseClasses = `
        relative inline-flex items-center justify-center gap-2 
        font-medium rounded-xl transition-all duration-200
        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-onyx-900
        disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none
        active:scale-[0.98]
        min-h-touch
    `;

    const variants = {
        primary: `
            text-white
            bg-gradient-to-br from-primary-500 to-accent-500
            shadow-glow-primary
            hover:shadow-[0_8px_25px_-5px_rgba(249,115,22,0.5)]
            hover:-translate-y-0.5
        `,
        secondary: `
            text-onyx-300 
            bg-glass border border-border
            hover:bg-glass-hover hover:text-onyx-100 hover:border-border-hover
        `,
        ghost: `
            text-onyx-400
            hover:bg-glass hover:text-onyx-100
        `,
        danger: `
            text-white
            bg-gradient-to-br from-rose-500 to-rose-600
            shadow-glow-danger
            hover:-translate-y-0.5
        `,
        success: `
            text-white
            bg-gradient-to-br from-emerald-500 to-emerald-600
            shadow-glow-success
            hover:-translate-y-0.5
        `,
    };

    const sizes = {
        sm: 'px-3 py-2 text-sm min-h-touch-sm',
        default: 'px-5 py-3 text-sm',
        lg: 'px-6 py-4 text-base min-h-touch-lg',
    };

    return (
        <button
            ref={ref}
            disabled={disabled || isLoading}
            className={`${baseClasses} ${variants[variant]} ${sizes[size]} ${className}`}
            {...props}
        >
            {isLoading && <Loader2 className="w-4 h-4 animate-spin" />}
            {!isLoading && Icon && iconPosition === 'left' && <Icon className="w-4 h-4" strokeWidth={1.5} />}
            {children}
            {!isLoading && Icon && iconPosition === 'right' && <Icon className="w-4 h-4" strokeWidth={1.5} />}
        </button>
    );
});

Button.displayName = 'Button';

/* ─────────────────────────────────────────────────────────────────────────────
   📝 INPUT
   States: default, focus, error, disabled
   ───────────────────────────────────────────────────────────────────────────── */

export const Input = forwardRef(({ 
    label,
    error,
    className = '',
    icon: Icon,
    ...props 
}, ref) => {
    return (
        <div className="space-y-2">
            {label && (
                <label className="block text-xs font-medium uppercase tracking-wider text-onyx-500">
                    {label}
                </label>
            )}
            <div className="relative">
                {Icon && (
                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-onyx-500">
                        <Icon className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                )}
                <input
                    ref={ref}
                    className={`
                        w-full rounded-xl transition-all duration-200
                        min-h-touch px-4 py-3
                        bg-glass border border-border
                        text-onyx-100 placeholder-onyx-600
                        text-base
                        focus:outline-none focus:bg-glass-hover focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20
                        disabled:opacity-50 disabled:cursor-not-allowed
                        ${Icon ? 'pl-11' : ''}
                        ${error ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/20' : ''}
                        ${className}
                    `}
                    {...props}
                />
            </div>
            {error && (
                <p className="text-xs text-rose-400">{error}</p>
            )}
        </div>
    );
});

Input.displayName = 'Input';

/* ─────────────────────────────────────────────────────────────────────────────
   📋 TEXTAREA
   ───────────────────────────────────────────────────────────────────────────── */

export const Textarea = forwardRef(({ 
    label,
    error,
    className = '',
    ...props 
}, ref) => {
    return (
        <div className="space-y-2">
            {label && (
                <label className="block text-xs font-medium uppercase tracking-wider text-onyx-500">
                    {label}
                </label>
            )}
            <textarea
                ref={ref}
                className={`
                    w-full rounded-xl transition-all duration-200
                    min-h-[6rem] px-4 py-3 resize-none
                    bg-glass border border-border
                    text-onyx-100 placeholder-onyx-600
                    text-base
                    focus:outline-none focus:bg-glass-hover focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20
                    disabled:opacity-50 disabled:cursor-not-allowed
                    ${error ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/20' : ''}
                    ${className}
                `}
                {...props}
            />
            {error && (
                <p className="text-xs text-rose-400">{error}</p>
            )}
        </div>
    );
});

Textarea.displayName = 'Textarea';

/* ─────────────────────────────────────────────────────────────────────────────
   🔽 SELECT
   ───────────────────────────────────────────────────────────────────────────── */

export const Select = forwardRef(({ 
    label,
    error,
    options = [],
    placeholder = 'Выберите...',
    className = '',
    ...props 
}, ref) => {
    return (
        <div className="space-y-2">
            {label && (
                <label className="block text-xs font-medium uppercase tracking-wider text-onyx-500">
                    {label}
                </label>
            )}
            <select
                ref={ref}
                className={`
                    w-full rounded-xl transition-all duration-200 appearance-none cursor-pointer
                    min-h-touch px-4 py-3 pr-10
                    bg-glass border border-border
                    text-onyx-100
                    text-base
                    focus:outline-none focus:bg-glass-hover focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20
                    disabled:opacity-50 disabled:cursor-not-allowed
                    ${error ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/20' : ''}
                    ${className}
                `}
                style={{
                    backgroundImage: `url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23606060' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e")`,
                    backgroundPosition: 'right 0.75rem center',
                    backgroundRepeat: 'no-repeat',
                    backgroundSize: '1.5em 1.5em',
                }}
                {...props}
            >
                <option value="" className="bg-onyx-900">{placeholder}</option>
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value} className="bg-onyx-900">
                        {opt.label}
                    </option>
                ))}
            </select>
            {error && (
                <p className="text-xs text-rose-400">{error}</p>
            )}
        </div>
    );
});

Select.displayName = 'Select';

/* ─────────────────────────────────────────────────────────────────────────────
   🏷️ BADGE
   Variants: default, primary, success, warning, danger, info
   ───────────────────────────────────────────────────────────────────────────── */

export function Badge({ 
    children, 
    variant = 'default',
    pulse = false,
    size = 'default',
    className = '',
    ...props 
}) {
    const variants = {
        default: 'bg-glass text-onyx-400 border-border',
        primary: 'bg-primary-500/15 text-primary-300 border-primary-500/30',
        success: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
        warning: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
        danger: 'bg-rose-500/15 text-rose-400 border-rose-500/30',
        info: 'bg-sky-500/15 text-sky-400 border-sky-500/30',
    };
    
    const sizes = {
        sm: 'px-2 py-1 text-[10px]',
        default: 'px-3 py-1.5 text-xs',
        lg: 'px-4 py-2 text-sm',
    };

    return (
        <span
            className={`
                inline-flex items-center gap-1.5 
                font-medium 
                rounded-full border
                ${variants[variant]}
                ${sizes[size]}
                ${pulse ? 'animate-pulse' : ''}
                ${className}
            `}
            {...props}
        >
            {children}
        </span>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   🃏 CARD
   ───────────────────────────────────────────────────────────────────────────── */

export function Card({ 
    children, 
    hover = true,
    className = '',
    ...props 
}) {
    return (
        <div
            className={`
                relative overflow-hidden rounded-2xl sm:rounded-3xl
                bg-glass backdrop-blur-xl
                border border-border
                transition-all duration-300
                ${hover ? 'hover:bg-glass-hover hover:border-border-hover hover:-translate-y-0.5' : ''}
                ${className}
            `}
            {...props}
        >
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '', ...props }) {
    return (
        <div className={`px-4 sm:px-6 py-4 border-b border-border ${className}`} {...props}>
            {children}
        </div>
    );
}

export function CardContent({ children, className = '', ...props }) {
    return (
        <div className={`px-4 sm:px-6 py-4 ${className}`} {...props}>
            {children}
        </div>
    );
}

export function CardFooter({ children, className = '', ...props }) {
    return (
        <div className={`px-4 sm:px-6 py-4 border-t border-border ${className}`} {...props}>
            {children}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   📊 STAT CARD
   ───────────────────────────────────────────────────────────────────────────── */

export function StatCard({ 
    icon: Icon, 
    label, 
    value, 
    trend,
    trendUp = true,
    className = '',
}) {
    return (
        <Card hover className={`p-4 sm:p-5 ${className}`}>
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs sm:text-sm text-onyx-500 mb-1">{label}</p>
                    <p className="text-2xl sm:text-3xl font-bold text-onyx-50">{value}</p>
                    {trend && (
                        <p className={`text-xs mt-1 ${trendUp ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {trendUp ? '↑' : '↓'} {trend}
                        </p>
                    )}
                </div>
                {Icon && (
                    <div className="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center shadow-glow-primary">
                        <Icon className="w-5 h-5 sm:w-6 sm:h-6 text-white" strokeWidth={1.5} />
                    </div>
                )}
            </div>
        </Card>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   🔵 AVATAR
   ───────────────────────────────────────────────────────────────────────────── */

export function Avatar({ 
    name, 
    src,
    size = 'default',
    className = '',
}) {
    const sizes = {
        sm: 'w-8 h-8 text-xs',
        default: 'w-10 h-10 text-sm',
        lg: 'w-12 h-12 text-base',
        xl: 'w-16 h-16 text-lg',
    };

    const initials = name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

    if (src) {
        return (
            <img 
                src={src} 
                alt={name} 
                className={`${sizes[size]} rounded-xl object-cover ${className}`}
            />
        );
    }

    return (
        <div className={`
            ${sizes[size]}
            rounded-xl bg-gradient-to-br from-onyx-700 to-onyx-800
            flex items-center justify-center
            text-onyx-200 font-semibold
            ${className}
        `}>
            {initials}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   ⏳ SKELETON — Loading Placeholder
   ───────────────────────────────────────────────────────────────────────────── */

export function Skeleton({ className = '', variant = 'default', ...props }) {
    const variants = {
        default: 'rounded-xl',
        circle: 'rounded-full',
        text: 'rounded h-4',
    };
    
    return (
        <div 
            className={`
                relative overflow-hidden bg-onyx-800
                ${variants[variant]}
                ${className}
            `}
            {...props}
        >
            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-onyx-700/50 to-transparent animate-shimmer" />
        </div>
    );
}

/* Skeleton presets for common patterns */
export function SkeletonCard({ className = '' }) {
    return (
        <Card hover={false} className={`p-4 sm:p-5 ${className}`}>
            <div className="flex items-start justify-between">
                <div className="space-y-3 flex-1">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-8 w-16" />
                    <Skeleton className="h-3 w-20" />
                </div>
                <Skeleton variant="default" className="w-10 h-10 sm:w-12 sm:h-12 rounded-xl" />
            </div>
        </Card>
    );
}

export function SkeletonListItem({ className = '' }) {
    return (
        <div className={`flex items-center gap-4 p-4 ${className}`}>
            <Skeleton variant="circle" className="w-10 h-10 flex-shrink-0" />
            <div className="flex-1 space-y-2">
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-3 w-1/2" />
            </div>
            <Skeleton className="h-6 w-16 rounded-full" />
        </div>
    );
}

export function SkeletonMessage({ className = '', isOutgoing = false }) {
    return (
        <div className={`flex ${isOutgoing ? 'justify-end' : 'justify-start'} ${className}`}>
            <Skeleton className={`h-16 w-3/4 max-w-[280px] rounded-2xl ${isOutgoing ? 'rounded-br-sm' : 'rounded-bl-sm'}`} />
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   🟢 ONLINE DOT
   ───────────────────────────────────────────────────────────────────────────── */

export function OnlineDot({ className = '' }) {
    return (
        <span className={`relative inline-flex w-2.5 h-2.5 ${className}`}>
            <span className="absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75 animate-ping" />
            <span className="relative inline-flex rounded-full w-2.5 h-2.5 bg-emerald-500" />
        </span>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   📏 DIVIDER
   ───────────────────────────────────────────────────────────────────────────── */

export function Divider({ className = '', label = '' }) {
    if (label) {
        return (
            <div className={`flex items-center gap-4 ${className}`}>
                <div className="flex-1 h-px bg-gradient-to-r from-transparent via-border to-transparent" />
                <span className="text-xs text-onyx-500 uppercase tracking-wider">{label}</span>
                <div className="flex-1 h-px bg-gradient-to-r from-transparent via-border to-transparent" />
            </div>
        );
    }
    
    return (
        <div className={`w-full h-px bg-gradient-to-r from-transparent via-border to-transparent ${className}`} />
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   💬 EMPTY STATE
   ───────────────────────────────────────────────────────────────────────────── */

export function EmptyState({ 
    icon: Icon,
    title,
    description,
    action,
    className = '',
}) {
    return (
        <div className={`flex flex-col items-center justify-center py-12 px-4 text-center ${className}`}>
            {Icon && (
                <div className="w-16 h-16 rounded-2xl bg-glass flex items-center justify-center mb-4">
                    <Icon className="w-8 h-8 text-onyx-600" strokeWidth={1.5} />
                </div>
            )}
            <h3 className="text-lg font-semibold text-onyx-100 mb-2">{title}</h3>
            {description && (
                <p className="text-sm text-onyx-500 max-w-sm mb-4">{description}</p>
            )}
            {action}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   🔔 TOAST (notification style)
   ───────────────────────────────────────────────────────────────────────────── */

export function Toast({ 
    title,
    message,
    variant = 'default',
    onClose,
    className = '',
}) {
    const variants = {
        default: 'border-border',
        success: 'border-emerald-500/30 bg-emerald-500/10',
        error: 'border-rose-500/30 bg-rose-500/10',
        warning: 'border-amber-500/30 bg-amber-500/10',
        info: 'border-sky-500/30 bg-sky-500/10',
    };
    
    const iconColors = {
        default: 'text-onyx-400',
        success: 'text-emerald-400',
        error: 'text-rose-400',
        warning: 'text-amber-400',
        info: 'text-sky-400',
    };

    return (
        <div className={`
            flex items-start gap-3 p-4
            bg-onyx-850 backdrop-blur-xl
            border rounded-xl
            shadow-soft-lg
            animate-slide-in-right
            ${variants[variant]}
            ${className}
        `}>
            <div className="flex-1">
                {title && <p className="font-medium text-onyx-100 mb-1">{title}</p>}
                {message && <p className="text-sm text-onyx-400">{message}</p>}
            </div>
            {onClose && (
                <button 
                    onClick={onClose}
                    className="text-onyx-500 hover:text-onyx-300 transition-colors p-1 -m-1"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   🎯 SLA INDICATOR
   ───────────────────────────────────────────────────────────────────────────── */

export function SlaIndicator({ 
    percentage = 0,
    isWarning = false,
    className = '',
}) {
    return (
        <div className={`h-1 rounded-full bg-onyx-800 overflow-hidden ${className}`}>
            <div 
                className={`h-full transition-all duration-500 ${
                    isWarning 
                        ? 'bg-gradient-to-r from-amber-500 to-rose-500 animate-pulse' 
                        : 'bg-gradient-to-r from-emerald-500 to-primary-500'
                }`}
                style={{ width: `${Math.min(100, Math.max(0, percentage))}%` }}
            />
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────────
   🤖 AI SCORE
   ───────────────────────────────────────────────────────────────────────────── */

export function AiScore({ 
    score,
    size = 'default',
    className = '',
}) {
    const isHot = score >= 70;
    
    const sizes = {
        sm: 'w-8 h-8 text-xs',
        default: 'w-12 h-12 text-sm',
        lg: 'w-16 h-16 text-lg',
    };
    
    return (
        <div 
            className={`
                relative inline-flex items-center justify-center rounded-xl font-bold
                ${sizes[size]}
                ${isHot 
                    ? 'bg-gradient-to-br from-amber-500/30 to-rose-500/30 border border-amber-500/50 text-amber-300 animate-glow-pulse' 
                    : 'bg-gradient-to-br from-primary-500/20 to-accent-500/20 border border-primary-500/30 text-primary-300'
                }
                ${className}
            `}
        >
            {score}
        </div>
    );
}
