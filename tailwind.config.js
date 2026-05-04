import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                bpjs: {
                    blue: {
                        50: '#e6f0f8',
                        100: '#cce1f1',
                        200: '#99c3e2',
                        300: '#66a5d4',
                        400: '#3387c5',
                        500: '#0066B3',
                        600: '#005490',
                        700: '#00416d',
                        800: '#002e4a',
                        900: '#001b27',
                    },
                    green: {
                        50: '#e6f9ee',
                        100: '#ccf3dc',
                        200: '#99e7b9',
                        300: '#66db96',
                        400: '#33cf73',
                        500: '#00B140',
                        600: '#008e33',
                        700: '#006c26',
                        800: '#00491a',
                        900: '#00270d',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
