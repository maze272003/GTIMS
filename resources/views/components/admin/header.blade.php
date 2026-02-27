<header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 px-4 py-5 flex items-center justify-between fixed top-0 right-0 left-0 z-30 lg:left-64 md:left-20 transition-left duration-300">
    <button id="mobile-menu-btn" class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 md:hidden" aria-label="Toggle navigation menu">
        <i class="fa-regular fa-bars text-gray-700 dark:text-gray-300 text-xl"></i>
    </button>
    <div class="hidden md:flex md:flex-col gap-1">
        <h1 class="text-gray-500 dark:text-gray-400 font-semibold text-lg">General Tinio RHU - Inventory Management System</h1>
        @if(current_tenant())
            <span class="inline-flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                <i class="fa-regular fa-building"></i>
                {{ strtoupper(current_tenant()->provinceSlug ?? '') }} / {{ strtoupper(current_tenant()->barangaySlug ?? '') }}
            </span>
        @elseif(auth()->check() && auth()->user()->isModerator())
            <span class="inline-flex w-fit items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200">
                <i class="fa-regular fa-shield"></i>
                MODERATOR
            </span>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <button id="dark-mode-toggle" class="ml-4 p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" aria-label="Toggle dark mode">
            <i id="dark-mode-icon" class="fa-regular fa-moon text-gray-600 dark:text-yellow-400 text-xl"></i>
        </button>
        <i class="fa-regular fa-user text-red-700 dark:text-red-300 text-xl hidden md:block"></i>
        <div class="hidden md:flex md:flex-col">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{auth()->user()->name}}</p>
            {{-- <p class="text-xs text-gray-500 dark:text-gray-400">{{auth()->user()->email}}</p> --}}
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('dark-mode-toggle');
        const icon = document.getElementById('dark-mode-icon');
        const html = document.documentElement;

        function setTheme(theme) {
            if (theme === 'dark') {
                html.classList.add('dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                html.classList.remove('dark');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        }

        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');
        setTheme(initialTheme);

        toggleBtn.addEventListener('click', function() {
            const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        });

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (!localStorage.getItem('theme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });
    });
</script>
