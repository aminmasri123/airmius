import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                air: {
                    bg: '#f2f2f0',
                    card: '#505155',
                    primary: '#87a1c5',
                    secondary: '#ffbb1d',
                    success: '#b9eb84',
                    error: '#ffa8a8',
                    border: '#505155',
                    muted: '#87a1c5',
                },

                dark: {
                    bg: '#141414',
                    card: '#1f1f1f',
                    primary: '#ffffff',
                    secondary: '#9CA3AF',
                    success: '#00C853',
                    error: '#ff5252',
                    border: '#2a2a2a',
                    muted: '#9CA3AF',
                },

                womanly: {
                    bg: '#fff0f5',        // bleibt (sehr hell)
                    card: '#ffffff',      // leicht dunkler für mehr Tiefe

                    primary: '#4a1f3d',   // 👈 WICHTIG: dunkler Text statt weiß
                    secondary: '#b95aa2', // bleibt (Accent)

                    success: '#7bc96f',   // etwas kräftiger
                    error: '#e57373',     // besser sichtbar

                    border: '#f3b6cc',    // klarer als vorher
                    muted: '#9c4a84',     // dunkler für Lesbarkeit
                },
                // 👇 DIESE sind entscheidend
                bg: 'var(--bg)',
                card: 'var(--card)',
                primary: 'var(--primary)',
                secondary: 'var(--secondary)',
                success: 'var(--success)',
                error: 'var(--error)',
                border: 'var(--border)',
                muted: 'var(--muted)',
            }
        },
    },

    plugins: [forms, typography],
};
