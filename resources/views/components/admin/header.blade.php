<header class="bg-white shadow-sm border-b border-gray-200 px-4 py-5 flex items-center justify-between fixed top-0 right-0 left-0 z-30 lg:left-64 md:left-20 transition-left duration-300">
    <button id="mobile-menu-btn" class="p-2 rounded-md hover:bg-gray-100 md:hidden">
        <i class="fa-regular fa-bars text-gray-700 text-xl"></i>
    </button>
    <h1 class="text-gray-500 font-semibold text-lg">General Tinio RHU - Inventory Management System</h1>
    <div class="hidden md:flex items-center gap-2">
        <i class="fa-regular fa-user text-red-700 text-xl"></i>
        <div>
            <p class="text-sm font-semibold text-gray-700">{{auth()->user()->name}}</p>
            <p class="text-xs text-gray-500">{{auth()->user()->email}}</p>
        </div>
    </div>
</header>