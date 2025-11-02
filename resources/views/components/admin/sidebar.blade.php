{{-- components/admin/sidebar.blade.php --}}

@php
$links = [
    [
        'route' => 'admin.dashboard',
        'label' => 'Dashboard',
        'icon' => 'fa-regular fa-house-chimney',
        'levels' => [1, 2, 3],
    ],
    [
        'route' => 'admin.inventory',
        'label' => 'Inventory',
        'icon' => 'fa-regular fa-cubes-stacked',
        'levels' => [1, 2],
    ],
    [
        'route' => 'admin.movements',
        'label' => 'Product Movement',
        'icon' => 'fa-regular fa-file-spreadsheet',
        'levels' => [1, 2],
    ],
    [
        'route' => 'admin.patientrecords',
        'label' => 'Reports',
        'icon' => 'fa-regular fa-book-user',
        'levels' => [1, 2],
    ],
    [
        'route' => 'admin.historylog',
        'label' => 'History Logs',
        'icon' => 'fa-regular fa-clock-rotate-left',
        'levels' => [1, 2],
    ],
    [
        'route' => 'admin.manageaccount',
        'label' => 'Manage Account',
        'icon' => 'fa-regular fa-users',
        'levels' => [1],
    ],
    // expandable for future links
    // [
    //     'route' => 'admin.manageaccount', <--- your new route here
    //     'label' => 'Hotdog',              <--- your new label here
    //     'icon' => 'fa-regular fa-users',  <--- your new icon here
    //     'levels' => [1],                  <--- your new levels here
    // ],
];
@endphp

<div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden"></div>

{{-- Main sidebar container --}}
<nav id="sidebar" class="fixed top-0 left-0 h-full bg-white shadow-lg w-64 p-4 flex flex-col transition-all duration-300 z-50 translate-x-[-100%] md:translate-x-0 md:w-20 lg:w-64 md:p-3 lg:p-4">
    
    <div class="flex items-center justify-between border-b-2 border-gray-200 pb-3">
        <img class="nav-text lg:block w-14" src="{{ asset('images/gtlogo.png') }}" alt="Logo">
        <button id="desktop-collapse-btn" class="hidden lg:block p-2 rounded-full hover:bg-gray-100">
            <i class="fa-solid fa-chevron-left text-gray-600"></i>
        </button>
    </div>

    {{-- Main Navigation Links --}}
    <ul class="flex flex-col flex-1 mt-6 space-y-2">
        @auth
            @foreach ($links as $link)
                @if (in_array(auth()->user()->user_level_id, $link['levels']))
                    @php
                        // Check if the current route matches the link's route
                        $isActive = request()->routeIs($link['route']);
                    @endphp
                    <li>
                        <a href="{{ route($link['route']) }}" 
                           class="nav-link flex items-center px-3 py-2.5 rounded-lg transition-colors duration-200
                                  {{ $isActive 
                                      ? 'bg-red-50 text-red-700' 
                                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' 
                                  }}">
                            
                            <i class="{{ $link['icon'] }} nav-icon w-5 text-center text-lg 
                                      {{ $isActive ? 'text-red-600' : 'text-gray-500' }}"></i>
                            
                            <span class="nav-text ml-4 font-medium lg:inline md:hidden">
                                {{ $link['label'] }}
                            </span>
                        </a>
                    </li>
                @endif
            @endforeach
        @endauth
    </ul>

    {{-- Bottom Links (Help & Logout) --}}
    <ul class="mt-auto space-y-2 border-t border-gray-200 pt-4">
        <li>
            <a href="#" class="w-full flex items-center px-3 py-2.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                <i class="fa-regular fa-circle-question nav-icon w-5 text-center text-lg text-gray-500"></i>
                <span class="nav-text ml-4 font-medium lg:inline md:hidden">Help & Tour</span>
            </a>
        </li>
        <li>
            <form action="{{ route('logout') }}" method="POST" class="w-full">
                @csrf 
                <button type="submit" 
                        class="w-full flex items-center px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 font-medium transition-colors duration-200">
                    <i class="fa-regular fa-arrow-right-from-bracket nav-icon w-5 text-center text-lg"></i>
                    <span class="nav-text ml-4 lg:inline md:hidden">Logout</span>
                </button>
            </form>
        </li>
    </ul>
</nav>

<script src="{{ asset('js/sidebar.js') }}"></script>