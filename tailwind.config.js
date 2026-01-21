/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],
    
    /* ═══════════════════════════════════════════════════════════════════════
       JGGL CRM Design System — Single Source of Truth
       Brand: Onyx Black + Orange
       Synced with: resources/css/design-tokens.css
       ═══════════════════════════════════════════════════════════════════════ */
    theme: {
        /* ─────────────────────────────────────────────────────────────────────
           Breakpoints — Mobile First
           ───────────────────────────────────────────────────────────────────── */
        screens: {
            'xs': '375px',      // iPhone SE, small phones
            'sm': '640px',      // Large phones landscape
            'md': '768px',      // Tablets portrait
            'lg': '1024px',     // Tablets landscape, laptops
            'xl': '1280px',     // Desktops
            '2xl': '1536px',    // Large desktops
            '3xl': '1920px',    // Full HD
            // Feature queries
            'hover-supported': { 'raw': '(hover: hover) and (pointer: fine)' },
            'touch': { 'raw': '(hover: none)' },
            'portrait': { 'raw': '(orientation: portrait)' },
            'landscape': { 'raw': '(orientation: landscape)' },
            'short': { 'raw': '(max-height: 500px)' },
            'fold': { 'raw': '(max-width: 320px)' },
        },
        
        extend: {
            /* ─────────────────────────────────────────────────────────────────
               Typography
               ───────────────────────────────────────────────────────────────── */
            fontFamily: {
                sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                mono: ['JetBrains Mono', 'Fira Code', 'SF Mono', 'Monaco', 'monospace'],
            },
            fontSize: {
                'xs': ['0.75rem', { lineHeight: '1rem' }],      // 12px
                'sm': ['0.875rem', { lineHeight: '1.25rem' }],  // 14px
                'base': ['1rem', { lineHeight: '1.5rem' }],     // 16px (iOS zoom prevent)
                'lg': ['1.125rem', { lineHeight: '1.75rem' }],  // 18px
                'xl': ['1.25rem', { lineHeight: '1.75rem' }],   // 20px
                '2xl': ['1.5rem', { lineHeight: '2rem' }],      // 24px
                '3xl': ['1.875rem', { lineHeight: '2.25rem' }], // 30px
                '4xl': ['2.25rem', { lineHeight: '2.5rem' }],   // 36px
                '5xl': ['3rem', { lineHeight: '1.2' }],         // 48px
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Colors — Onyx Black + Orange Brand (CSS Variable Based)
               ───────────────────────────────────────────────────────────────── */
            colors: {
                // CSS Variable based colors (Single Source of Truth)
                onyx: 'rgb(var(--onyx) / <alpha-value>)',
                surface: 'rgb(var(--surface) / <alpha-value>)',
                'surface-2': 'rgb(var(--surface-2) / <alpha-value>)',
                'surface-3': 'rgb(var(--surface-3) / <alpha-value>)',
                fg: 'rgb(var(--fg) / <alpha-value>)',
                'fg-muted': 'rgb(var(--fg-muted) / <alpha-value>)',
                line: 'rgb(var(--line) / <alpha-value>)',
                orange: 'rgb(var(--orange) / <alpha-value>)',
                'orange-2': 'rgb(var(--orange-2) / <alpha-value>)',
                ring: 'rgb(var(--ring) / <alpha-value>)',
                
                // Primary — Orange (hex fallbacks)
                primary: {
                    50:  '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#f97316',  // Main brand color
                    600: '#ea580c',
                    700: '#c2410c',
                    800: '#9a3412',
                    900: '#7c2d12',
                    950: '#431407',
                },
                // Accent — Amber
                accent: {
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                },
                // Dark mode backgrounds
                dark: {
                    base:     '#0b0f14',
                    elevated: '#10151c',
                    surface:  '#161c24',
                    overlay:  '#1c222c',
                },
                // Glass effects
                glass: {
                    DEFAULT: 'rgba(255, 255, 255, 0.03)',
                    hover:   'rgba(255, 255, 255, 0.06)',
                    active:  'rgba(255, 255, 255, 0.09)',
                },
            },
            
            /* Border color defaults */
            borderColor: {
                DEFAULT: 'rgb(var(--line) / 0.08)',
            },
            
            /* Ring color defaults */
            ringColor: {
                DEFAULT: 'rgb(var(--ring) / 0.5)',
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Spacing & Sizing — Touch Targets
               ───────────────────────────────────────────────────────────────── */
            spacing: {
                'touch': '2.75rem',      // 44px — Apple HIG touch target
                'touch-sm': '2.5rem',    // 40px — Compact touch target
                'touch-lg': '3rem',      // 48px — Large touch target
                'safe-top': 'env(safe-area-inset-top)',
                'safe-bottom': 'env(safe-area-inset-bottom)',
                'safe-left': 'env(safe-area-inset-left)',
                'safe-right': 'env(safe-area-inset-right)',
            },
            minHeight: {
                'touch': '2.75rem',
                'touch-sm': '2.5rem',
                'touch-lg': '3rem',
            },
            minWidth: {
                'touch': '2.75rem',
                'touch-sm': '2.5rem',
                'touch-lg': '3rem',
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Border Radius
               ───────────────────────────────────────────────────────────────── */
            borderRadius: {
                'sm': '0.375rem',   // 6px
                'DEFAULT': '0.5rem', // 8px
                'md': '0.5rem',     // 8px
                'lg': '0.75rem',    // 12px
                'xl': '1rem',       // 16px
                '2xl': '1.25rem',   // 20px
                '3xl': '1.5rem',    // 24px
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Shadows — Soft Dark Theme
               ───────────────────────────────────────────────────────────────── */
            boxShadow: {
                'soft': '0 1px 2px rgba(0, 0, 0, 0.4)',
                'soft-md': '0 4px 8px -2px rgba(0, 0, 0, 0.5)',
                'soft-lg': '0 8px 16px -4px rgba(0, 0, 0, 0.6)',
                'soft-xl': '0 16px 32px -8px rgba(0, 0, 0, 0.7)',
                'soft-2xl': '0 24px 48px -12px rgba(0, 0, 0, 0.8)',
                // Glow effects — Orange brand
                'glow': '0 0 40px -10px rgba(249, 115, 22, 0.4)',
                'glow-primary': '0 4px 20px -4px rgba(249, 115, 22, 0.4)',
                'glow-success': '0 4px 20px -4px rgba(16, 185, 129, 0.4)',
                'glow-danger': '0 4px 20px -4px rgba(244, 63, 94, 0.4)',
                'glow-warning': '0 4px 20px -4px rgba(245, 158, 11, 0.4)',
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Transitions
               ───────────────────────────────────────────────────────────────── */
            transitionDuration: {
                'fast': '150ms',
                'base': '200ms',
                'slow': '300ms',
            },
            transitionTimingFunction: {
                'spring': 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Animations
               ───────────────────────────────────────────────────────────────── */
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out forwards',
                'fade-in-up': 'fadeInUp 0.4s ease-out forwards',
                'scale-in': 'scaleIn 0.2s ease-out forwards',
                'slide-up': 'slideUp 0.3s ease-out forwards',
                'slide-in-right': 'slideInRight 0.3s ease-out forwards',
                'pulse-soft': 'pulseSoft 2s ease-in-out infinite',
                'glow-pulse': 'glowPulse 2s ease-in-out infinite',
                'online-ping': 'onlinePing 1.5s cubic-bezier(0, 0, 0.2, 1) infinite',
                'shimmer': 'shimmer 1.5s infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(4px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                scaleIn: {
                    '0%': { opacity: '0', transform: 'scale(0.95)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                slideUp: {
                    '0%': { opacity: '0', transform: 'translateY(100%)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideInRight: {
                    '0%': { opacity: '0', transform: 'translateX(16px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                pulseSoft: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.7' },
                },
                glowPulse: {
                    '0%, 100%': { boxShadow: '0 0 20px -5px rgba(249, 115, 22, 0.4)' },
                    '50%': { boxShadow: '0 0 30px -5px rgba(249, 115, 22, 0.6)' },
                },
                onlinePing: {
                    '75%, 100%': { transform: 'scale(2)', opacity: '0' },
                },
                shimmer: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(100%)' },
                },
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Z-Index Scale
               ───────────────────────────────────────────────────────────────── */
            zIndex: {
                'dropdown': '10',
                'sticky': '20',
                'fixed': '30',
                'overlay': '40',
                'modal': '50',
                'popover': '60',
                'tooltip': '70',
                'toast': '80',
            },
            
            /* ─────────────────────────────────────────────────────────────────
               Max Width — Content Containers
               ───────────────────────────────────────────────────────────────── */
            maxWidth: {
                'content': '1440px',
                'content-sm': '640px',
                'content-md': '768px',
                'content-lg': '1024px',
            },
        },
    },
    
    plugins: [
        require('@tailwindcss/forms')({
            strategy: 'class', // Only generate classes, no base styles
        }),
    ],
};
