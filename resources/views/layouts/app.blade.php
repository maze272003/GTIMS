<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-level" content="{{ auth()->check() ? auth()->user()->user_level_id : '' }}">
        <meta name="user-permissions" content="{{ auth()->check() ? auth()->user()->level?->permissions->pluck('name')->implode(',') : '' }}">
        <title>{{ $title ?? 'General Tinio - Inventory System' }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.1.0/css/all.css">
        <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="{{ asset('js/gtims-notify.js') }}"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.css">
        <script src="https://cdn.jsdelivr.net/npm/driver.js@1.4.0/dist/driver.js.iife.js"></script>
        <script src="{{ asset('js/tour.js') }}" defer></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        
    </head>
    <style>
        body, html, input, button, select, textarea {
            font-family: 'Inter', sans-serif;
        }
        #sleep-overlay {
            transition: opacity 0.5s ease-in-out;
        }
    </style>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
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

        <div id="sleep-overlay" class="fixed inset-0 z-[9999] bg-black/95 hidden flex-col items-center justify-center text-white backdrop-blur-sm p-4">
            <div class="text-center animate-pulse w-full max-w-lg">
                {{-- Logo: 24 (small) sa mobile, 32 (large) sa desktop --}}
                <img src="{{ asset('images/gtlogo.png') }}" alt="Logo" class="w-24 h-24 md:w-32 md:h-32 mx-auto mb-4 md:mb-6 opacity-80 object-contain">
                
                {{-- Time: 4xl sa mobile, 6xl sa desktop --}}
                <div id="sleep-clock" class="text-4xl md:text-6xl font-bold tracking-widest mb-2 md:mb-4 font-mono break-words">00:00:00</div>
                
                {{-- Date: base text sa mobile, xl sa desktop --}}
                <div id="sleep-date" class="text-base md:text-xl text-gray-400 mb-8 md:mb-12 font-light">Loading date...</div>

                <p class="text-gray-500 text-xs md:text-sm uppercase tracking-[0.3em]">System Sleeping</p>
                
                {{-- Instructions change slightly for visual clarity --}}
                <p class="text-gray-600 text-[10px] md:text-xs mt-2">
                    <span class="block md:hidden">Tap screen to wake up</span>
                    <span class="hidden md:block">Move mouse or press any key to wake up</span>
                </p>
            </div>
        </div>
        </body>

    <script>
        // @deprecated Use window.hasPermission() instead of window.currentUserLevel for access checks
        window.currentUserLevel = {{ auth()->check() ? auth()->user()->user_level_id : 'null' }};
        window.userPermissions = '{{ auth()->check() ? auth()->user()->level?->permissions->pluck("name")->implode(",") : "" }}'.split(',');
        window.hasPermission = function(perm) { return window.userPermissions.indexOf(perm) !== -1; };

        // ================= SLEEP MODE SCRIPT ================= //
        document.addEventListener('DOMContentLoaded', function() {
            let idleTime = 0;
            const sleepOverlay = document.getElementById('sleep-overlay');
            
            // CONFIGURATION: Ilang minuto bago mag sleep? (Example: 5 minutes)
            // 1 minute = 60000 ms
            const idleLimit = 5; // 5 Minutes setup

            // Increment the idle time counter every minute.
            const idleInterval = setInterval(timerIncrement, 60000); // Check every 1 minute

            // Zero the idle timer on mouse movement or key press.
            window.onload = resetTimer;
            window.onmousemove = resetTimer;
            window.onmousedown = resetTimer; // Clicks
            window.ontouchstart = resetTimer; // Touchscreen
            window.onclick = resetTimer;     // Touchpad clicks
            window.onkeydown = resetTimer;   // Keyboard
            window.onscroll = resetTimer;    // Scrolling

            function timerIncrement() {
                idleTime = idleTime + 1;
                if (idleTime >= idleLimit) { 
                    // Activate Sleep Mode
                    showSleepMode();
                }
            }

            function resetTimer() {
                idleTime = 0;
                // Kung naka-show ang sleep mode, itago ito pag gumalaw ang user
                if (!sleepOverlay.classList.contains('hidden')) {
                    hideSleepMode();
                }
            }

            function showSleepMode() {
                sleepOverlay.classList.remove('hidden');
                sleepOverlay.classList.add('flex');
                // Simulan ang orasan sa sleep screen
                startSleepClock();
            }

            function hideSleepMode() {
                sleepOverlay.classList.add('hidden');
                sleepOverlay.classList.remove('flex');
            }

            // --- Clock Logic for Sleep Screen ---
            function startSleepClock() {
                const updateClock = () => {
                    const now = new Date();
                    
                    // Format Time (12-hour format)
                    let hours = now.getHours();
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12; 
                    
                    document.getElementById('sleep-clock').innerText = `${hours}:${minutes} ${ampm}`;
                    
                    // Format Date
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    document.getElementById('sleep-date').innerText = now.toLocaleDateString(undefined, options);
                };

                updateClock(); // Run immediately
                setInterval(updateClock, 1000); // Update every second
            }
        });
    </script>

    {{-- Offline Banner & Error Boundary --}}
    <script>
        (function() {
            var banner = document.getElementById('offline-banner');
            function updateOnlineStatus() {
                if (banner) {
                    if (navigator.onLine) {
                        banner.classList.add('hidden');
                    } else {
                        banner.classList.remove('hidden');
                    }
                }
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
                        // Multiple errors: show full fallback UI for hard crashes
                        fallback.classList.remove('hidden');
                    } else if (typeof gtToast !== 'undefined') {
                        // Single error: show toast for recoverable issues
                        gtToast.error('An unexpected error occurred.');
                    }
                }
                return false;
            };
        })();
    </script>
</html>