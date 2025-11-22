<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-level" content="{{ auth()->user()->user_level_id }}">
        <title>{{ $title ?? 'General Tinio - Inventory System' }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.1.0/css/all.css">
        <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        // Global variable na accessible sa lahat ng JS files
        window.currentUserLevel = {{ auth()->user()->user_level_id }};

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
</html>