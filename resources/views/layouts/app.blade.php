<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- <title>General Tinio - Inventory System</title> --}}
        {{-- set the title base where blade name file name im in --}}
        <title>{{ $title ?? 'General Tinio - Inventory System' }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.1.0/css/all.css">
        <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        {{-- sweetalert --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="{{ asset('js/spa-navigation.js') }}"></script>
    </head>
    <body class="font-sans antialiased">
            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
