@php
    use Carbon\Carbon;
@endphp
<x-app-layout>
    <x-slot name="title">Product Movements - General Tinio</x-slot>

    <body class="bg-gray-50 dark:bg-gray-900">
        <x-admin.sidebar/>

        <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
            <x-admin.header/>

            {{-- @if(in_array(auth()->user()->user_level_id, [1,2,3,4]) && auth()->user()->branch_id != 2) --}}
                <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
                    <div class="mb-6 pt-16">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Home / <span class="text-red-700 dark:text-red-300 font-medium">Product Movements</span></p>
                    </div>

                    <!-- STAT CARDS -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Movements Today</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $movementsTodayCount ?? 0 }}</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1"><i class="fa-regular fa-clock mr-1"></i> Last 24 hours</p>
                                </div>
                                <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded-full">
                                    <i class="fa-regular fa-xl fa-clock text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Items IN Today</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ number_format($itemsInToday ?? 0) }}</p>
                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1"><i class="fa-regular fa-arrow-down-to-bracket mr-1"></i> Received</p>
                                </div>
                                <div class="bg-green-100 dark:bg-green-900 p-4 rounded-full">
                                    <i class="fa-regular fa-arrow-down-to-bracket text-2xl text-green-600"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Items OUT Today</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ number_format($itemsOutToday ?? 0) }}</p>
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1"><i class="fa-regular fa-arrow-up-from-bracket mr-1"></i> Dispatched</p>
                                </div>
                                <div class="bg-red-100 dark:bg-red-900 p-4 rounded-full">
                                    <i class="fa-regular fa-arrow-up-from-bracket text-2xl text-red-600"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Movements</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ number_format($movements->total()) }}</p>
                                    <p class="text-xs text-gray-600 mt-1"><i class="fa-regular fa-boxes-stacked mr-1"></i> All records</p>
                                </div>
                                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-full">
                                    <i class="fa-regular fa-boxes-stacked text-2xl text-gray-600"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FILTERS -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border mb-5 dark:text-gray-300 dark:bg-gray-700">
                        <form id="filter-form" action="{{ route('admin.movements') }}" method="GET">
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                                <div class="relative">
                                  <input type="text" name="search" value="{{ request('search') }}" placeholder="Search batch/description..." class="pl-10 py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                  <i class="fa-regular fa-magnifying-glass text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 dark:text-gray-300"></i>
                                </div>

                                <select name="product_id" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                    <option value="">All Products</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>
                                            {{ $p->generic_name }} ({{ $p->brand_name }})
                                        </option>
                                    @endforeach
                                </select>

                                <select name="type" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                    <option value="">All Types</option>
                                    <option value="IN" @selected(request('type')=='IN')>IN</option>
                                    <option value="OUT" @selected(request('type')=='OUT')>OUT</option>
                                </select>

                                <!-- NEW: Branch Filter -->
                                <select name="branch_id" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                    <option value="">All Branches</option>
                                    <option value="1" @selected(request('branch_id')==1)>RHU 1</option>
                                    <option value="2" @selected(request('branch_id')==2)>RHU 2</option>
                                </select>

                                <select name="user_id" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                    <option value="">All Users</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                                <input type="date" name="from" value="{{ request('from') }}" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                <input type="date" name="to" value="{{ request('to') }}" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                <select name="sort" class="py-3 border rounded-lg text-sm dark:text-gray-300 dark:bg-gray-700">
                                    <option value="desc" @selected(request('sort','desc')=='desc')>Newest First</option>
                                    <option value="asc" @selected(request('sort')=='asc')>Oldest First</option>
                                </select>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.movements') }}" class="px-4 py-3 bg-gray-200 dark:bg-gray-700 rounded-lg text-sm dark:text-gray-300">Clear</a>
                                    <button type="submit" class="px-6 py-3 bg-red-700 dark:bg-red-900 text-white rounded-lg text-sm">Apply</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- TABLE -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border overflow-hidden">
                        <div class="p-4 border-b flex justify-end">
                            <button onclick="exportToCSV()" class="flex items-center gap-2 px-4 py-2 border rounded-lg hover:shadow">
                                <i class="fa-regular fa-file-export text-green-600"></i> Export CSV
                            </button>
                        </div>
                        <div class="overflow-x-auto p-5" id="movements-data-container">
                            @include('admin.partials.movements_table')
                        </div>
                    </div>
                </main>
            {{-- @else --}}
                {{-- <main class="pt-20 p-8 text-center">
                    <i class="fa-regular fa-lock text-6xl text-gray-400 mb-4"></i>
                    <h1 class="text-3xl font-bold">Unauthorized Access</h1>
                    <p>You do not have permission to view this page.</p>
                    <a href="{{ route('admin.inventory') }}" class="mt-4 px-6 py-3 bg-blue-600 text-white rounded">Go to Inventory</a>
                </main> --}}
            {{-- @endif --}}
        </div>
    </body>
</x-app-layout>

<script>
function exportToCSV() {
    window.location = "{{ route('admin.movements') }}?export=csv";
}
</script>