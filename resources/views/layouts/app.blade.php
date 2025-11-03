<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'General Tinio - Inventory System' }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Poppins', 'sans-serif'],
                        },
                    },
                },
            }
        </script>
        <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.1.0/css/all.css">
        <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        {{ $slot }}
    </body>
</html>