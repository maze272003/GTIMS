@php
    use Carbon\Carbon;
@endphp
<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / <span class="text-red-700 dark:text-red-300 font-medium">Inventory</span>
                </p>
            </div>

            @if (session('success'))
                <div id="successAlert" class="w3 fixed top-24 right-5 border-l-4 border-green-500 bg-white text-green-500 py-3 px-6 rounded-lg shadow-lg z-101 flex items-center gap-3 z-50">
                    <i class="fa-solid fa-circle-check text-2xl"></i>
                    <div>
                        <p class="font-bold">Success!</p>
                        <p id="successMessage" class="text-black">{{ session('success') }}</p>
                    </div>
                </div>
                <script>
                    setTimeout(() => {
                        const alert = document.getElementById('successAlert');
                        if (alert) {
                            alert.remove();
                        }
                    }, 3000);
                </script>
            @endif

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">In Stock</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                                {{ $inventorycount->where('quantity', '>=', 100)->count() }}
                            </p>
                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">Currently in stock</p>
                        </div>
                        <div class="bg-green-100 dark:bg-green-900 p-4 rounded-full">
                            <i class="fa-regular fa-boxes-stacked text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Low Stock</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                                {{ $inventorycount->where('quantity', '<', 100)->where('quantity', '>', 0)->count() }}
                            </p>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">Requires attention</p>
                        </div>
                        <div class="bg-orange-100 dark:bg-orange-900 p-4 rounded-full">
                            <i class="fa-regular fa-exclamation text-2xl text-orange-600 dark:text-orange-400"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Expired Stock</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                                {{ $inventorycount->where('expiry_date', '<', Carbon::now())->count() }}
                            </p>
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">Must be removed</p>
                        </div>
                        <div class="bg-red-100 dark:bg-red-900 p-4 rounded-full">
                            <i class="fa-regular fa-xl fa-calendar-xmark text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Nearly Expired</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                                {{ $inventorycount->where('expiry_date', '>', Carbon::now())
                                    ->where('expiry_date', '<', Carbon::now()->addDays(30))->count() }}
                            </p>
                            <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">Expires in 30 days</p>
                        </div>
                        <div class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded-full">
                            <i class="fa-regular fa-clock text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Buttons --}}
            @if (auth()->user()->branch_id != 2)
                <div class="mt-6 flex flex-wrap gap-3 w-full justify-end mb-8">
                    @if (auth()->user()->user_level_id != 4)
                    <button id="addnewproductbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300 flex-1 sm:flex-none min-w-[200px]">
                        <i class="fa-regular fa-plus mr-2"></i> Register New Product
                    </button>
                    @endif
                    <button id="viewallproductsbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300 flex-1 sm:flex-none min-w-[200px]">
                        <i class="fa-regular fa-eye mr-2"></i> View All Products
                    </button>
                    <button id="viewarchiveproductsbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300 flex-1 sm:flex-none min-w-[200px]">
                        <i class="fa-regular fa-box-archive mr-2"></i> View Archive Products
                    </button>
                </div>
            @endif

            {{-- RHU 1 Table --}}
            <div class="mt-10 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-lg font-semibold text-red-700 dark:text-gray-100">RHU 1 Inventory</p>
                    <select id="filter-rhu1" class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 text-sm">
                        <option value="">All Items</option>
                        <option value="in_stock" {{ request('filter_rhu1') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                        <option value="low_stock" {{ request('filter_rhu1') == 'low_stock' ? 'selected' : '' }}>Low Stock</option>
                        <option value="out_of_stock" {{ request('filter_rhu1') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                        <option value="nearly_expired" {{ request('filter_rhu1') == 'nearly_expired' ? 'selected' : '' }}>Nearly Expired</option>
                        <option value="expired" {{ request('filter_rhu1') == 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>

                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <div class="relative w-full sm:w-[40%]">
                        <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="search-rhu1" placeholder="Search by Product Name or Batch Number" class="pl-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-sm w-full" value="{{ request('search_rhu1') }}">
                    </div>

                    @if (auth()->user()->branch_id != 2)
                    <form action="{{ route('admin.inventory.export') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="branch" value="1">
                        <input type="hidden" name="filter" id="export-filter-rhu1" value="{{ request('filter_rhu1', '') }}">
                        <input type="hidden" name="search" id="export-search-rhu1" value="{{ request('search_rhu1', '') }}">
                        <button type="submit" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
                            <i class="fa-regular fa-file-export text-lg text-green-600 dark:text-green-400"></i>
                            <span class="ml-2">Export to XLSX</span>
                        </button>
                    </form>
                    @endif
                </div>
                <div class="overflow-x-auto" id="rhu1-container">
                    @include('admin.partials._inventory_table', ['inventories' => $inventories_rhu1, 'branch' => 1])
                </div>
            </div>

            {{-- RHU 2 Table --}}
            <div class="mt-10 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-lg font-semibold text-red-700 dark:text-gray-100">RHU 2 Inventory</p>
                    <select id="filter-rhu2" class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 text-sm">
                        <option value="">All Items</option>
                        <option value="in_stock" {{ request('filter_rhu2') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                        <option value="low_stock" {{ request('filter_rhu2') == 'low_stock' ? 'selected' : '' }}>Low Stock</option>
                        <option value="out_of_stock" {{ request('filter_rhu2') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                        <option value="nearly_expired" {{ request('filter_rhu2') == 'nearly_expired' ? 'selected' : '' }}>Nearly Expired</option>
                        <option value="expired" {{ request('filter_rhu2') == 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>

                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <div class="relative w-full sm:w-[40%]">
                        <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="search-rhu2" placeholder="Search by Product Name or Batch Number" class="pl-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-sm w-full" value="{{ request('search_rhu2') }}">
                    </div>

                    <form action="{{ route('admin.inventory.export') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="branch" value="2">
                        <input type="hidden" name="filter" id="export-filter-rhu2" value="{{ request('filter_rhu2', '') }}">
                        <input type="hidden" name="search" id="export-search-rhu2" value="{{ request('search_rhu2', '') }}">
                        <button type="submit" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
                            <i class="fa-regular fa-file-export text-lg text-green-600 dark:text-green-400"></i>
                            <span class="ml-2">Export to XLSX</span>
                        </button>
                    </form>
                </div>
                <div class="overflow-x-auto" id="rhu2-container">
                    @include('admin.partials._inventory_table', ['inventories' => $inventories_rhu2, 'branch' => 2])
                </div>
            </div>
        </main>
    </div>

    {{-- Modals --}}
    @include('components.admin.modals.inventory.view-all-products', ['products' => $products])
    @include('components.admin.modals.inventory.view-archive-products', ['archiveproducts' => $archiveproducts])
    @include('components.admin.modals.inventory.archived-stocks')
    @include('components.admin.modals.inventory.add-new-product')
    @include('components.admin.modals.inventory.add-stock')
    @include('components.admin.modals.inventory.edit-product')
    @include('components.admin.modals.inventory.edit-stock')
</x-app-layout>

<script src="{{ asset('js/inventory.js') }}"></script>
<script>window.successMessage = @json(session('success'));</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if ($errors->hasBag('addproduct') || $errors->hasBag('addstock') || $errors->hasBag('updateproduct') || $errors->hasBag('editstock'))
        @if ($errors->hasBag('addproduct'))
            document.getElementById('addnewproductmodal')?.classList.remove('hidden');
        @elseif ($errors->hasBag('addstock'))
            document.getElementById('viewallproductsmodal')?.classList.remove('hidden');
            document.getElementById('addstockmodal')?.classList.remove('hidden');
        @elseif ($errors->hasBag('updateproduct'))
            document.getElementById('viewallproductsmodal')?.classList.remove('hidden');
            document.getElementById('editproductmodal')?.classList.remove('hidden');
        @elseif ($errors->hasBag('editstock'))
            document.getElementById('editstockmodal')?.classList.remove('hidden');
        @endif
    @endif

    const baseUrl = '{{ route("admin.inventory") }}';

    const debounce = (func, delay) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => func(...args), delay);
        };
    };

    function fetchTable(branch) {
        const searchInput = document.getElementById(`search-rhu${branch}`);
        const filterSelect = document.getElementById(`filter-rhu${branch}`);
        const container = document.getElementById(`rhu${branch}-container`);

        const search = searchInput.value.trim();
        const filter = filterSelect.value;

        const url = new URL(baseUrl);
        if (search) url.searchParams.set(`search_rhu${branch}`, search);
        if (filter) url.searchParams.set(`filter_rhu${branch}`, filter);

        const other = branch === 1 ? 2 : 1;
        url.searchParams.delete(`search_rhu${other}`);
        url.searchParams.delete(`filter_rhu${other}`);
        url.searchParams.delete(`page_rhu${other}`);

        fetch(url.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
            history.replaceState({}, '', url.href);

            // Update export hidden fields
            document.getElementById(`export-search-rhu${branch}`).value = search;
            document.getElementById(`export-filter-rhu${branch}`).value = filter;
        });
    }

    [1, 2].forEach(branch => {
        const searchInput = document.getElementById(`search-rhu${branch}`);
        const filterSelect = document.getElementById(`filter-rhu${branch}`);
        const container = document.getElementById(`rhu${branch}-container`);

        // Search (AJAX)
        searchInput.addEventListener('keyup', debounce(() => fetchTable(branch), 500));

        // Filter change → full reload (preserves filter)
        filterSelect.addEventListener('change', function() {
            const url = new URL(baseUrl);
            const searchVal = document.getElementById(`search-rhu${branch}`).value.trim();
            const filterVal = this.value;

            if (searchVal) url.searchParams.set(`search_rhu${branch}`, searchVal);
            if (filterVal) url.searchParams.set(`filter_rhu${branch}`, filterVal);

            const other = branch === 1 ? 2 : 1;
            url.searchParams.delete(`search_rhu${other}`);
            url.searchParams.delete(`filter_rhu${other}`);
            url.searchParams.delete(`page_rhu${other}`);

            window.location.href = url.href;
        });

        // Pagination (AJAX)
        container.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (!link || !link.classList.contains('pagination-link')) return;
            e.preventDefault();

            const url = new URL(link.href);
            const currentSearch = searchInput.value.trim();
            const currentFilter = filterSelect.value;

            if (currentSearch) url.searchParams.set(`search_rhu${branch}`, currentSearch);
            if (currentFilter) url.searchParams.set(`filter_rhu${branch}`, currentFilter);

            const other = branch === 1 ? 2 : 1;
            url.searchParams.delete(`search_rhu${other}`);
            url.searchParams.delete(`filter_rhu${other}`);
            url.searchParams.delete(`page_rhu${other}`);

            fetch(url.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => {
                    container.innerHTML = html;
                    history.replaceState({}, '', url.href);
                });
        });
    });
});
</script>