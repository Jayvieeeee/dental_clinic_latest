import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                rem: ['REM', 'sans-serif'], 
            },
            colors: {
                dark: '#326672',
                neutral: '#4D9D9E',
                light: '#AED1D3',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
