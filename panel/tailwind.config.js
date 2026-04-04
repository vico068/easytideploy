import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                display: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
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
