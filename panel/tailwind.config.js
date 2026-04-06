import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/**/*.php',
    ],
    safelist: [
        // Status dot colors (usadas dinamicamente via match() em PHP)
        'bg-emerald-500', 'bg-amber-500', 'bg-red-500', 'bg-slate-400', 'bg-green-500', 'bg-rose-500',
        // Status border glow
        'border-emerald-500/20', 'border-amber-500/20', 'border-red-500/20',
        // Animations
        'animate-pulse', 'animate-building', 'animate-fade-in-up',
        // Stagger delays
        'stagger-1', 'stagger-2', 'stagger-3', 'stagger-4', 'stagger-5', 'stagger-6',
        // Deployment logs terminal - text colors (usadas dinamicamente em Alpine.js)
        'text-red-400', 'text-amber-400', 'text-sky-300', 'text-emerald-400', 'text-slate-300',
        'text-sky-400', 'text-slate-400', 'text-slate-500', 'text-slate-600', 'text-slate-700',
        'text-sky-500', 'text-purple-400', 'text-emerald-500',
        // Deployment logs terminal - background colors
        'bg-sky-500/15', 'bg-amber-500/15', 'bg-purple-500/15', 'bg-emerald-500/15', 'bg-slate-800',
        'bg-sky-500/10', 'bg-amber-500/10', 'bg-emerald-500/10', 'bg-red-500/10', 'bg-slate-700/50',
        'bg-sky-500/20', 'bg-slate-800',
        // Deployment logs terminal - border colors
        'border-sky-500/30', 'border-amber-500/30', 'border-emerald-500/30', 'border-red-500/30',
        'border-slate-600/50', 'border-sky-500/50', 'border-emerald-500/50', 'border-slate-700',
        // Deployment logs terminal - ring
        'ring-2', 'ring-sky-500/15',
        // Font weight
        'font-semibold',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                display: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', 'Fira Code', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                brand: {
                    50: '#eef9ff',
                    100: '#d8f1ff',
                    200: '#b9e7ff',
                    300: '#89daff',
                    400: '#52c4ff',
                    500: '#2aaeff',
                    600: '#0d8bfa',  // Cor principal EasyTI
                    700: '#0c6fc7',
                    800: '#125da1',
                    900: '#154e7f',
                    950: '#10314d',
                },
                cyan: {
                    50: '#ecfeff',
                    100: '#cffafe',
                    200: '#a5f3fc',
                    300: '#67e8f9',
                    400: '#22d3ee',
                    500: '#06b6d4',  // Cor de destaque
                    600: '#0891b2',
                    700: '#0e7490',
                    800: '#155e75',
                    900: '#164e63',
                    950: '#083344',
                },
                navy: {
                    50: '#f0f4f8',
                    100: '#d9e2ec',
                    200: '#bcccdc',
                    300: '#9fb3c8',
                    400: '#829ab1',
                    500: '#627d98',
                    600: '#486581',
                    700: '#334e68',
                    800: '#243b53',
                    900: '#1a2a3a',
                    950: '#102a43',
                },
            },
        },
    },
    plugins: [],
};
