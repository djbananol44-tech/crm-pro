/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],
    theme: {
        screens: {
            'xs': '320px',      // Very small phones, Galaxy Fold (folded)
            'sm': '640px',      // Small phones landscape
            'md': '768px',      // Tablets, Galaxy Fold (unfolded)
            'lg': '1024px',     // Laptops, iPad Pro
            'xl': '1280px',     // Desktops
            '2xl': '1536px',    // Large desktops
            '3xl': '1920px',    // Full HD
            '4xl': '2560px',    // Ultrawide, QHD
            '5xl': '3840px',    // 4K monitors
            // Foldable devices
            'fold-open': { 'raw': '(min-width: 717px) and (max-width: 884px)' },
            // Hover capability
            'hover-supported': { 'raw': '(hover: hover) and (pointer: fine)' },
            // Touch devices
            'touch': { 'raw': '(hover: none)' },
            // Portrait orientation
            'portrait': { 'raw': '(orientation: portrait)' },
            // Landscape orientation
            'landscape': { 'raw': '(orientation: landscape)' },
            // Short height (keyboard open, mobile landscape)
            'short': { 'raw': '(max-height: 500px)' },
        },
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
            },
            colors: {
                primary: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
            },
            boxShadow: {
                'soft': '0 8px 30px rgb(0 0 0 / 0.04)',
                'soft-lg': '0 12px 40px rgb(0 0 0 / 0.08)',
                'soft-xl': '0 20px 50px rgb(0 0 0 / 0.1)',
            },
            spacing: {
                'safe-top': 'env(safe-area-inset-top)',
                'safe-bottom': 'env(safe-area-inset-bottom)',
                'safe-left': 'env(safe-area-inset-left)',
                'safe-right': 'env(safe-area-inset-right)',
            },
            minHeight: {
                'touch': '2.75rem', // 44px - Apple HIG touch target
            },
            minWidth: {
                'touch': '2.75rem',
            },
            animation: {
                'fade-in': 'fadeIn 0.4s ease-out forwards',
                'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                'scale-in': 'scaleIn 0.3s ease-out forwards',
                'slide-in': 'slideInRight 0.4s ease-out forwards',
                'slide-up': 'slideUp 0.3s ease-out forwards',
                'float': 'float 3s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                scaleIn: {
                    '0%': { opacity: '0', transform: 'scale(0.95)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                slideInRight: {
                    '0%': { opacity: '0', transform: 'translateX(20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                slideUp: {
                    '0%': { opacity: '0', transform: 'translateY(100%)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-4px)' },
                },
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
