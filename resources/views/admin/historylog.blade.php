<x-app-layout>
<body class="bg-gray-50 dark:bg-gray-900">
 
    <x-admin.sidebar/>
 
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>

        {{-- Check for Authorization --}}
        @if(in_array(auth()->user()->user_level_id, [1, 2, 3, 4]) && auth()->user()->branch_id != 2)
            {{-- AUTHORIZED VIEW --}}
            <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
                <div class="mb-6 pt-16">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">History Logs</span>
                    </p>
                </div>
    
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden mt-6 border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300">System Activity Timeline</h2>
                        <div class="flex gap-2">
                            <button id="toggleFilterBtn" class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center gap-2">
                                <i class="fas fa-filter text-xs"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- FILTER PANEL -->
                    <div id="filterPanel" class="max-h-0 overflow-hidden transition-all duration-[400ms] border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <div class="p-4">
                            <form id="filterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">Action</label>
                                    <select id="filterAction" name="action" class="w-full mt-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="">All Actions</option>
                                        @foreach($actions as $action)
                                            <option value="{{ $action }}">{{ $action }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">User</label>
                                    <select id="filterUser" name="user" class="w-full mt-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="">All Users</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user }}">{{ $user }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">From</label>
                                    <input id="filterFrom" type="date" name="from" class="w-full mt-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>

                                <div>
                                    <label class="text-sm text-gray-600 dark:text-gray-400">To</label>
                                    <input id="filterTo" type="date" name="to" class="w-full mt-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                            </form>

                            <div class="mt-3 text-right">
                                <button id="applyFilterBtn" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">Apply Filters</button>
                                <button id="resetFilterBtn" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">Reset</button>
                            </div>
                        </div>
                    </div>
                    <!-- END FILTER PANEL -->

                    <div class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="relative w-full md:w-1/2">
                            <i class="fa-regular fa-magnifying-glass absolute left-3 top-[21%] transform text-gray-400 dark:text-gray-500 text-sm"></i>
                            <input 
                                id="searchInput"
                                type="text" 
                                placeholder="Search activity..." 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent text-sm transition-all bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            >
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 pl-1 italic">
                                Tip: You can search by 
                                <span class="font-medium text-gray-500 dark:text-gray-400">action</span>, 
                                <span class="font-medium text-gray-500 dark:text-gray-400">user</span>, 
                                <span class="font-medium text-gray-500 dark:text-gray-400">details</span>, or 
                                <span class="font-medium text-gray-500 dark:text-gray-400">date & time</span>.
                            </p>
                        </div>
                    </div>

                    <!-- Table wrapper -->
                    <div class="relative overflow-x-auto p-5">
                        <div id="table-loader" class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-black/30 backdrop-blur-sm hidden z-10">
                            <div class="w-10 h-10 border-4 border-blue-500 dark:border-blue-400 border-t-transparent rounded-full animate-spin"></div>
                        </div>

                        <div id="history-table">
                            @include('admin.partials._history_table')
                        </div>
                    </div>
                </div>
            </main>
        
        @else
            {{-- UNAUTHORIZED VIEW (Added this else block) --}}
            <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen flex flex-col items-center justify-center">
                <i class="fa-regular fa-lock text-6xl text-gray-400 mb-4"></i>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Unauthorized Access</h1>
                <p class="text-gray-600">You do not have permission to view this page.</p>
                <a href="{{ route('admin.inventory') }}" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Go to Inventory
                </a>
            </main>
        @endif
    </div>
    
 
    <!-- Modal -->
    <div id="viewMoreModal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
        <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4 sticky top-0 bg-white dark:bg-gray-800 z-10">
                <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300">Full Description</h3>
                <button id="closeModalBtn" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i class="fa-regular fa-xmark text-xl text-gray-600 dark:text-gray-400"></i>
                </button>
            </div>

            <div class="overflow-y-auto max-h-[70vh] pr-1">
                <p id="modalDescription" class="text-gray-600 dark:text-gray-400 whitespace-pre-line text-sm leading-relaxed"></p>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/historyLog.js') }}"></script>
</body>
</x-app-layout>