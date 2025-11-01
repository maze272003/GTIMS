const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php', 
    ],

    theme: {
        extend: {
            fontFamily: {
                'sans': ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'primary': {
                    'light': '#F87171', 
                    'DEFAULT': '#DC2626',
                    'dark': '#B91C1C', 
                },
                'secondary': {
                    'light': '#F3F4F6',
                    'DEFAULT': '#6B7280', 
                    'dark': '#1F2937',  
                }
            },
        },
    },

    plugins: [
        require('@tailwindcss/forms'),
    ],
};