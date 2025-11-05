<div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 dark:bg-black dark:bg-opacity-50 z-40 hidden md:hidden"></div>
<nav id="sidebar" class="fixed top-0 left-0 h-full bg-white dark:bg-gray-800 shadow-lg w-64 p-5 flex flex-col transition-all duration-300 z-50 translate-x-[-100%] md:translate-x-0 md:w-20 lg:w-64 border-r border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between border-b-2 border-gray-200 dark:border-gray-700 pb-3">
        <img class="nav-text lg:block w-14" src="{{ asset('images/gtlogo.png') }}" alt="Logo">
        <button id="desktop-collapse-btn" class="hidden lg:block p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
            <i class="fa-solid fa-chevron-left text-gray-600 dark:text-gray-400"></i>
        </button>
    </div>

    {{-- Main Navigation Links --}}
    <ul class="flex flex-col flex-1 mt-6 space-y-2">
        @auth
            @if(in_array(auth()->user()->user_level_id, [1, 2, 3]))
            <li>
                <a href="{{ route('admin.dashboard') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-house-chimney nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Dashboard</span>
                </a>
            </li>
            @endif
            @if(in_array(auth()->user()->user_level_id, [1, 2]))
            <li>
                <a href="{{ route('admin.inventory') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-cubes-stacked nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Inventory</span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.movements') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-file-spreadsheet nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Product Movement</span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.patientrecords') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-book-user nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Reports</span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.historylog') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-clock-rotate-left nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">History Logs</span>
                </a>
            </li>
            @endif
            @if(auth()->user()->user_level_id == 1)
            <li>
                <a href="{{ route('admin.manageaccount') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-users nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Manage Account</span>
                </a>
            </li>
            @endif
        @endauth
    </ul>
    <ul class="mt-auto space-y-1 border-t pt-4 border-gray-200 dark:border-gray-700">
        <li>
            <a href="#" class="w-full flex items-center px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg text-gray-700 dark:text-gray-300">
                <i class="fa-regular fa-circle-question nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Help & Tour</span>
            </a>
        </li>
        <li>
        <form action="{{ route('logout') }}" method="POST" class="w-full">
            @csrf
            <button type="submit" class="w-full flex items-center px-3 py-2.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg font-medium text-red-700 dark:text-red-300 hover:text-red-600 dark:hover:text-red-400">
                <i class="fa-regular fa-arrow-right-from-bracket nav-icon w-5 text-center"></i>
                <span class="nav-text ml-3 lg:inline md:hidden">Logout</span>
            </button>
        </form>
        </li>
    </ul>
</nav>
<script src="{{ asset('js/sidebar.js') }}"></script>