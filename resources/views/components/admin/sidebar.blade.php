<div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 dark:bg-black dark:bg-opacity-50 z-40 hidden md:hidden"></div>

<nav id="sidebar" class="fixed top-0 left-0 h-full bg-white dark:bg-gray-800 shadow-lg w-64 p-5 flex flex-col transition-all duration-300 z-50 translate-x-[-100%] md:translate-x-0 md:w-20 lg:w-64 border-r border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between border-b-2 border-gray-200 dark:border-gray-700 pb-3">
        <img class="nav-text lg:block w-14" src="{{ asset('images/gtlogo.png') }}" alt="Logo">
        <button id="desktop-collapse-btn" class="hidden lg:block p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
            <i class="fa-solid fa-chevron-left text-gray-600 dark:text-gray-400"></i>
        </button>
    </div>

    {{-- Main Navigation Links --}}
    <ul id="sidebar-scroll" class="flex flex-col flex-1 min-h-0 mt-6 space-y-2 overflow-y-auto overflow-x-hidden pr-1 scroll-smooth">
        @auth

            {{-- 1. DASHBOARD --}}
            @haspermission('dashboard.view')
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fa-regular fa-house-chimney nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                        <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Dashboard</span>
                    </a>
                </li>
            @endhaspermission

            {{-- ORDER STOCK --}}
            @haspermission('orders.view')
                <li>
                    <a href="{{ route('admin.orders.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.orders.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <i class="fa-solid fa-cart-shopping nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                        <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Order Stock</span>

                        @php
                            $pendingCount = 0;
                            if(auth()->user()->hasPermission('orders.approve_admin')) {
                                $pendingCount = \App\Models\Order::where('status', 'pending_admin')->count();
                            }
                            elseif(auth()->user()->hasPermission('orders.approve_finance')) {
                                $pendingCount = \App\Models\Order::where('status', 'pending_finance')->count();
                            }
                        @endphp

                        @if($pendingCount > 0)
                            <span class="ml-auto inline-flex items-center justify-center w-5 h-5 ml-2 text-xs font-semibold text-white bg-red-600 rounded-full">
                                {{ $pendingCount }}
                            </span>
                        @endif
                    </a>
                </li>
            @endhaspermission

            {{-- INVENTORY --}}
            @haspermission('inventory.view')
            <li>
                <a href="{{ route('admin.inventory') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-cubes-stacked nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Inventory</span>
                </a>
            </li>
            @endhaspermission

            @haspermission('movements.view')
            <li>
                <a href="{{ route('admin.movements') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-file-spreadsheet nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Product Movement</span>
                </a>
            </li>
            @endhaspermission

            {{-- PATIENT RECORDS --}}
            @haspermission('patients.view')
            <li>
                <a href="{{ route('admin.patientrecords') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-book-user nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Records</span>
                </a>
            </li>
            @endhaspermission

            @haspermission('historylog.view')
            <li>
                <a href="{{ route('admin.historylog') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-clock-rotate-left nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">History Logs</span>
                </a>
            </li>
            @endhaspermission

            {{-- HOLDS --}}
            @haspermission('holds.view')
            <li>
                <a href="{{ route('admin.holds.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.holds.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-hand nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Holds / Pullout</span>
                </a>
            </li>
            @endhaspermission

            {{-- REQUESTS --}}
            @haspermission('requests.view')
            <li>
                <a href="{{ route('admin.requests.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.requests.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-inbox nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Requests</span>
                </a>
            </li>
            @endhaspermission

            {{-- SUPPLIERS --}}
            @haspermission('suppliers.view')
            <li>
                <a href="{{ route('admin.suppliers.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.suppliers.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-truck nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Suppliers</span>
                </a>
            </li>
            @endhaspermission

            {{-- LOW STOCK SETTINGS --}}
            @haspermission('settings.low_stock')
            <li>
                <a href="{{ route('admin.lowstock.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.lowstock.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-triangle-exclamation nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Low Stock Settings</span>
                </a>
            </li>
            @endhaspermission

            {{-- NOTIFICATIONS --}}
            @haspermission('notifications.manage')
            <li>
                <a href="{{ route('admin.notifications.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.notifications.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-bell nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Notifications</span>
                </a>
            </li>
            @endhaspermission

            {{-- AUDIT --}}
            @haspermission('audit.view')
            <li>
                <a href="{{ route('admin.audit.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.audit.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-shield-check nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Audit Logs</span>
                </a>
            </li>
            @endhaspermission

            {{-- MANAGE ACCOUNT --}}
            @haspermission('users.manage')
            <li>
                <a href="{{ route('admin.manageaccount') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fa-regular fa-users nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Manage Accounts</span>
                </a>
            </li>
            @endhaspermission

            @haspermission('settings.roles')
            <li>
                <a href="{{ route('admin.roles.index') }}" class="nav-link flex items-center px-3 py-2.5 rounded-lg text-gray-700 dark:text-gray-300 md:text-gray-700 dark:md:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 {{ request()->routeIs('admin.roles.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                    <i class="fa-regular fa-user-shield nav-icon w-5 text-center text-gray-600 dark:text-gray-400"></i>
                    <span class="nav-text ml-3 font-medium lg:inline md:hidden text-gray-700 dark:text-gray-300">Roles & Permissions</span>
                </a>
            </li>
            @endhaspermission

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
            <form action="{{ route('logout') }}" id="logout-form" method="POST" class="w-full">
                @csrf
                <button id="logout-btn" type="button" class="w-full flex items-center px-3 py-2.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg font-medium text-red-700 dark:text-red-300 hover:text-red-600 dark:hover:text-red-400">
                    <i class="fa-regular fa-arrow-right-from-bracket nav-icon w-5 text-center"></i>
                    <span class="nav-text ml-3 lg:inline md:hidden">Logout</span>
                </button>
            </form>
        </li>
    </ul>
</nav>

<script src="{{ asset('js/sidebar.js') }}"></script>
