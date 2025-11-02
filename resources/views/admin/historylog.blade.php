<x-app-layout>
{{-- this block of code is transferded to app layout --}}
{{-- <body class="bg-gray-50"> --}}
    {{-- <x-admin.sidebar/> --}}
 
    {{-- <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/> --}}
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-600">
                    Home / <span class="text-red-700 font-medium">History Logs</span>
                </p>
            </div>
 
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-6">
                <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-700">System Activity Timeline</h2>
                    <div class="flex gap-2">
                        <button id="toggleFilterBtn" class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                            <i class="fas fa-filter text-xs"></i> Filter
                        </button>
                    </div>
                </div>

                <!-- FILTER PANEL -->
                <div id="filterPanel" class="max-h-0 overflow-hidden transition-all duration-[400ms] border-b border-gray-100 bg-gray-50">
                    <div class="p-4">
                        <form id="filterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="text-sm text-gray-600">Action</label>
                                <select id="filterAction" name="action" class="w-full mt-1 p-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">All Actions</option>
                                    @foreach($actions as $action)
                                        <option value="{{ $action }}">{{ $action }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-sm text-gray-600">User</label>
                                <select id="filterUser" name="user" class="w-full mt-1 p-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">All Users</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user }}">{{ $user }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-sm text-gray-600">From</label>
                                <input id="filterFrom" type="date" name="from" class="w-full mt-1 p-2 border border-gray-300 rounded-lg text-sm">
                            </div>

                            <div>
                                <label class="text-sm text-gray-600">To</label>
                                <input id="filterTo" type="date" name="to" class="w-full mt-1 p-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                        </form>

                        <div class="mt-3 text-right">
                            <button id="applyFilterBtn" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Apply Filters</button>
                            <button id="resetFilterBtn" class="px-4 py-2 bg-gray-300 text-gray-800 text-sm rounded-lg hover:bg-gray-400">Reset</button>
                        </div>
                    </div>
                </div>
                <!-- END FILTER PANEL -->

                <div class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="relative w-full md:w-1/2">
                        <i class="fa-regular fa-magnifying-glass absolute left-3 top-[21%] transform text-gray-400 text-sm"></i>
                        <input 
                            id="searchInput"
                            type="text" 
                            placeholder="Search activity..." 
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent text-sm transition-all"
                        >
                        <p class="text-xs text-gray-400 mt-1 pl-1 italic">
                            Tip: You can search by 
                            <span class="font-medium text-gray-500">action</span>, 
                            <span class="font-medium text-gray-500">user</span>, 
                            <span class="font-medium text-gray-500">details</span>, or 
                            <span class="font-medium text-gray-500">date & time</span>.
                        </p>
                    </div>
                </div>

                <!-- Table wrapper -->
                <div class="relative overflow-x-auto p-5">
                    <div id="table-loader" class="absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-sm hidden z-10">
                        <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>

                    <div id="history-table">
                        @include('admin.partials._history_table')
                    </div>
                </div>
            </div>
        </main>
    </div>
 
    <!-- Modal -->
    <div id="viewMoreModal" class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
        <div class="modal bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto p-6">
            <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4 sticky top-0 bg-white z-10">
                <h3 class="text-xl font-medium text-gray-700">Full Description</h3>
                <button id="closeModalBtn" class="p-2 rounded-full hover:bg-gray-100 transition-colors">
                    <i class="fa-regular fa-xmark text-xl text-gray-600"></i>
                </button>
            </div>

            <div class="overflow-y-auto max-h-[70vh] pr-1">
                <p id="modalDescription" class="text-gray-600 whitespace-pre-line text-sm leading-relaxed"></p>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/historyLog.js') }}"></script>
</body>
</x-app-layout>
