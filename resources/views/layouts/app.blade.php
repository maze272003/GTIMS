<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-level" content="{{ auth()->check() ? auth()->user()->user_level_id : '' }}">
        <meta name="user-permissions" content="{{ implode(',', $permissionView->names()) }}">
        <meta name="user-default-access-url" content="{{ $permissionView->destination()['url'] ?? '' }}">
        <title>{{ $title ?? 'General Tinio - Inventory System' }}</title>

        {{-- Prevent light-theme flash before CSS/JS loads by applying saved theme immediately --}}
        <script>
            (function () {
                try {
                    var savedTheme = localStorage.getItem('theme');
                    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var useDark = savedTheme ? savedTheme === 'dark' : prefersDark;
                    var root = document.documentElement;

                    if (useDark) root.classList.add('dark');
                    else root.classList.remove('dark');

                    root.style.colorScheme = useDark ? 'dark' : 'light';
                } catch (e) {
                    // Ignore localStorage/matchMedia access errors and keep default theme
                }
            })();
        </script>
        <style>
            html { background-color: #f9fafb; }
            html.dark { background-color: #111827; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
        <link rel="stylesheet" href="{{ asset('css/icon-compat.css') }}">
        <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">

        <script src="{{ asset('js/icon-compat.js') }}" defer></script>
        <script src="{{ asset('js/gtims-notify.js') }}" defer></script>
        <script src="{{ asset('js/permission-ui.js') }}" defer></script>
        <script src="{{ asset('js/tour.js') }}" defer></script>
        <script src="{{ asset('js/user-permissions.js') }}" defer></script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <style>
            body, html, input, button, select, textarea {
                font-family: 'Inter', sans-serif;
            }
        </style>
    </head>

    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <x-global-preloader />

        {{-- Offline Banner --}}
        <div id="offline-banner" class="hidden fixed top-0 left-0 right-0 z-[9998] bg-yellow-500 text-yellow-900 text-center text-sm font-medium py-2 px-4" role="alert">
            <i class="fa-solid fa-wifi-slash mr-1"></i> You are offline. Some features may not be available.
        </div>

        {{-- Global Error Fallback --}}
        <div id="error-fallback" class="hidden fixed inset-0 z-[9997] bg-gray-50 dark:bg-gray-900 flex items-center justify-center p-6" role="alert">
            <div class="text-center max-w-md">
                <i class="fa-solid fa-triangle-exclamation text-5xl text-red-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2">Something went wrong</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6">An unexpected error occurred. Please reload the page.</p>
                <button onclick="window.location.reload()" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 px-6 rounded-xl shadow transition-all">
                    <i class="fa-solid fa-rotate-right mr-2"></i> Reload Page
                </button>
            </div>
        </div>

        {{ $slot }}

        {{-- Auto-render session flash messages as toasts --}}
        <x-toast />

        @stack('scripts')

        {{-- Logout Form (required for auto logout) --}}
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>

        {{-- Globals --}}
        <script>
            // @deprecated Use window.hasPermission() instead of window.currentUserLevel for access checks
            window.currentUserLevel = {{ auth()->check() ? auth()->user()->user_level_id : 'null' }};
            window.permissionContext = {
                permissions: @json($permissionView->names()),
                redirectDestination: @json($permissionView->destination()),
            };
            window.userPermissions = window.permissionContext.permissions;
            window.hasPermission = function(perm) { return window.userPermissions.indexOf(perm) !== -1; };
        </script>

        {{-- Auto Logout (Idle Warning + Countdown) --}}
        <script>
            // ====== CONFIG (seconds) ======
            const SECONDS_BEFORE_WARNING = 2000; // idle seconds before showing warning
            const SECONDS_TO_COUNTDOWN  = 200; // countdown seconds before auto logout

            let idleTimer = null;
            let isLoggingOut = false;

            function safeLogout() {
                if (isLoggingOut) return;
                isLoggingOut = true;
                const form = document.getElementById('logout-form');
                if (form) form.submit();
            }

            function resetTimer() {
                clearTimeout(idleTimer);

                // If warning modal is open, don't restart idle timer until user confirms
                if (window.Swal && Swal.isVisible()) return;

                idleTimer = setTimeout(showLogoutWarning, SECONDS_BEFORE_WARNING * 1000);
            }

            function showLogoutWarning() {
                let timerInterval;

                Swal.fire({
                    title: 'Inactivity Warning',
                    html: 'No movement detected. System will logout in <b>' + SECONDS_TO_COUNTDOWN + '</b> seconds.',
                    timer: SECONDS_TO_COUNTDOWN * 1000,
                    timerProgressBar: true,
                    icon: 'warning',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: true,
                    confirmButtonText: 'I am still here',
                    confirmButtonColor: '#3085d6',
                    didOpen: () => {
                        const b = Swal.getHtmlContainer()?.querySelector('b');
                        timerInterval = setInterval(() => {
                            const left = Swal.getTimerLeft();
                            if (!left) return;
                            const secondsLeft = Math.ceil(left / 1000);
                            if (b) b.textContent = secondsLeft;
                        }, 200);
                    },
                    willClose: () => clearInterval(timerInterval)
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetTimer();
                        return;
                    }

                    // If timer finished, auto logout
                    if (result.dismiss === Swal.DismissReason.timer) {
                        safeLogout();
                    } else {
                        // Any other dismiss reason: still logout for safety
                        safeLogout();
                    }
                });
            }

            // Track activity (PC + Mobile)
            const activityEvents = [
                'mousedown', 'mousemove', 'keypress',
                'scroll', 'touchstart', 'click'
            ];

            activityEvents.forEach(evt => window.addEventListener(evt, resetTimer, true));

            // If user switches tab and comes back, treat as activity
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) resetTimer();
            });

            // Start timer
            resetTimer();
        </script>

        {{-- Offline Banner & Error Boundary --}}
        <script>
            (function() {
                var banner = document.getElementById('offline-banner');
                function updateOnlineStatus() {
                    if (!banner) return;
                    if (navigator.onLine) banner.classList.add('hidden');
                    else banner.classList.remove('hidden');
                }
                window.addEventListener('online', updateOnlineStatus);
                window.addEventListener('offline', updateOnlineStatus);
                updateOnlineStatus();

                // Global JS error boundary – show fallback UI for hard crashes
                var errorCount = 0;
                window.onerror = function(msg, url, line) {
                    errorCount++;
                    var fallback = document.getElementById('error-fallback');
                    if (msg && typeof msg === 'string' && msg.indexOf('Script error') === -1) {
                        if (errorCount >= 3 && fallback) {
                            fallback.classList.remove('hidden');
                        } else if (typeof gtToast !== 'undefined') {
                            gtToast.error('An unexpected error occurred.');
                        }
                    }
                    return false;
                };
            })();
        </script>
    </body>
</html>
