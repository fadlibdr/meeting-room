import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Bespoke BPJS component classes (app.css @layer components) whose modifier
    // is built dynamically in Blade — e.g. <x-bpjs.button> emits 'btn--'.$variant
    // and <x-bpjs.pill>/<x-bpjs.status-pill> emit 'pill--'.$variant. Those literals
    // never appear in source, so the production purge would strip the unused ones
    // (btn--success, btn--solid-danger, pill--red, …). Safelist the full families.
    safelist: [
        'btn--primary', 'btn--success', 'btn--ghost', 'btn--danger', 'btn--solid-danger', 'btn--lg', 'btn--block',
        'pill--green', 'pill--amber', 'pill--red', 'pill--blue', 'pill--slate',
        'input--err',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Libre Franklin"', ...defaultTheme.fontFamily.sans],
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
            borderRadius: {
                // BPJS kit uses slightly larger radii than Breeze defaults
                'card': '1rem',     // 16px — cards / panels / modals
                'ctl': '0.625rem',  // 10px — buttons, inputs, controls
            },
            boxShadow: {
                'card': '0 1px 2px rgba(16,24,40,.04)',
                'pop': '0 18px 40px rgba(16,24,40,.16)',
                'modal': '0 24px 60px rgba(0,0,0,.3)',
            },
        },
    },

    plugins: [forms],
};
