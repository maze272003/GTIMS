@php
    $createProductState = $permissionView->disabledAttributes('inventory.add', 'register a new product');
    $createProductClasses = $permissionView->disabledClasses('inventory.add');
    $archiveViewState = $permissionView->disabledAttributes('inventory.archive', 'view archived inventory');
    $archiveViewClasses = $permissionView->disabledClasses('inventory.archive');
    $exportInventoryState = $permissionView->disabledAttributes(['inventory.view', 'reports.export'], 'export inventory data', true, true);
    $exportInventoryClasses = $permissionView->disabledClasses(['inventory.view', 'reports.export'], true);
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

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">In Stock</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                                {{ $inventoryStats['in_stock'] ?? 0 }}
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
                                {{ $inventoryStats['low_stock'] ?? 0 }}
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
                                {{ $inventoryStats['expired'] ?? 0 }}
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
                                {{ $inventoryStats['nearly_expired'] ?? 0 }}
                            </p>
                            <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">Expires in 30 days</p>
                        </div>
                        <div class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded-full">
                            <i class="fa-regular fa-clock text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3 w-full justify-end mb-8">
                <button
                    id="addnewproductbtn"
                    type="button"
                    {!! $createProductState !!}
                    class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-3 border border-gray-300 dark:border-gray-600 rounded-xl transition-all duration-200 text-gray-700 dark:text-gray-300 flex-1 sm:flex-none min-w-[200px] hover:-translate-y-1 hover:shadow-md {{ $createProductClasses }}"
                >
                    <i class="fa-regular fa-plus mr-2"></i> Register New Product
                </button>
                <button id="viewallproductsbtn" type="button" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-3 border border-gray-300 dark:border-gray-600 rounded-xl hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300 flex-1 sm:flex-none min-w-[200px]">
                    <i class="fa-regular fa-eye mr-2"></i> View All Products
                </button>
                <button
                    id="viewarchiveproductsbtn"
                    type="button"
                    {!! $archiveViewState !!}
                    class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-3 border border-gray-300 dark:border-gray-600 rounded-xl transition-all duration-200 text-gray-700 dark:text-gray-300 flex-1 sm:flex-none min-w-[200px] hover:-translate-y-1 hover:shadow-md {{ $archiveViewClasses }}"
                >
                    <i class="fa-regular fa-box-archive mr-2"></i> View Archive Products
                </button>
            </div>

            @forelse($branches as $branch)
                @php
                    $branchId = (int) $branch->id;
                    $filterKey = 'filter_branch_'.$branchId;
                    $searchKey = 'search_branch_'.$branchId;
                    $branchInventoriesList = $branchInventories[$branchId] ?? null;
                @endphp

                <div class="mt-10 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700 gap-3">
                        <p class="text-lg font-semibold text-red-700 dark:text-gray-100">{{ $branch->name }} Inventory</p>
                        <select id="filter-branch-{{ $branchId }}" class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 text-sm">
                            <option value="">All Items</option>
                            <option value="in_stock" {{ request($filterKey) == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                            <option value="low_stock" {{ request($filterKey) == 'low_stock' ? 'selected' : '' }}>Low Stock</option>
                            <option value="out_of_stock" {{ request($filterKey) == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                            <option value="nearly_expired" {{ request($filterKey) == 'nearly_expired' ? 'selected' : '' }}>Nearly Expired</option>
                            <option value="expired" {{ request($filterKey) == 'expired' ? 'selected' : '' }}>Expired</option>
                        </select>
                    </div>

                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                        <div class="relative w-full sm:w-[40%]">
                            <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" id="search-branch-{{ $branchId }}" placeholder="Search by Product Name or Batch Number" class="pl-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-sm w-full" value="{{ request($searchKey) }}">
                        </div>

                        <form action="{{ route('admin.inventory.export') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="branch" value="{{ $branchId }}">
                            <input type="hidden" name="filter" id="export-filter-branch-{{ $branchId }}" value="{{ request($filterKey, '') }}">
                            <input type="hidden" name="search" id="export-search-branch-{{ $branchId }}" value="{{ request($searchKey, '') }}">
                            <button
                                type="submit"
                                {!! $exportInventoryState !!}
                                class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-3 border border-gray-300 dark:border-gray-600 rounded-xl transition-all duration-200 text-gray-700 dark:text-gray-300 hover:-translate-y-1 hover:shadow-md {{ $exportInventoryClasses }}"
                            >
                                <i class="fa-regular fa-file-export text-lg text-green-600 dark:text-green-400"></i>
                                <span class="ml-2">Export to XLSX</span>
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto" id="branch-{{ $branchId }}-container">
                        @if($branchInventoriesList)
                            @include('admin.partials._inventory_table', [
                                'inventories' => $branchInventoriesList,
                                'branch' => $branchId,
                                'focusInventoryId' => $focusInventoryId ?? null,
                            ])
                        @else
                            <div class="p-6 text-sm text-gray-500">No inventory data available for this branch.</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-sm text-gray-500">
                    No active branches configured. Ask a Super Administrator to create or unarchive a branch.
                </div>
            @endforelse
        </main>
    </div>

    @include('components.admin.modals.inventory.view-all-products', ['products' => $products])
    @include('components.admin.modals.inventory.view-archive-products', ['archiveproducts' => $archiveproducts])
    @include('components.admin.modals.inventory.archived-stocks')
    @include('components.admin.modals.inventory.add-new-product')
    @include('components.admin.modals.inventory.add-stock')
    @include('components.admin.modals.inventory.edit-product')
    @include('components.admin.modals.inventory.edit-stock')

    <div id="transferstockmodal" class="hidden fixed bg-black/60 w-full h-screen top-0 left-0 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="modal bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md transform transition-all">
            <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Transfer Stock</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <i class="fa-regular fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="{{ route('admin.inventory.transferstock') }}" method="POST" id="transfer-form">
                @csrf
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Product</label>
                        <p id="transfer-product-name" class="text-lg font-medium text-red-600 dark:text-white mt-1"></p>
                        <input type="hidden" name="inventory_id" id="transfer-inventory-id">
                        <input type="hidden" id="transfer-source-branch-id" value="">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Batch No.</label>
                            <p id="transfer-batch" class="font-bold text-purple-700 dark:text-purple-400"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Current Branch</label>
                            <p id="transfer-current-branch" class="font-medium text-gray-700 dark:text-gray-300"></p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Available Quantity</label>
                        <p id="transfer-available-qty" class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1"></p>
                    </div>

                    <div>
                        <label for="transfer_qty" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Quantity to Transfer <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="quantity" id="transfer_qty" min="1" required class="w-full mt-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100">
                        <p class="text-xs text-red-500 mt-1 hidden" id="transfer-error">Not enough stock!</p>
                    </div>

                    <div>
                        <label for="destination_branch" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Transfer To Branch <span class="text-red-500">*</span>
                        </label>
                        <select name="destination_branch" id="destination_branch" required class="w-full mt-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 p-6 border-t dark:border-gray-700">
                    <button type="button" class="close-modal px-6 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-medium text-gray-700 dark:text-gray-300">
                        Cancel
                    </button>
                    <button type="button" id="confirm-transfer-btn" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Transfer Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<script src="{{ asset('js/inventory.js') }}"></script>

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
    const focusInventoryId = @json($focusInventoryId ?? null);
    const focusBranch = @json($focusBranch ?? null);
    const branchIds = @json($branches->pluck('id')->values());

    function focusInventoryRow() {
        if (!focusInventoryId || !focusBranch) return;

        const row = document.getElementById(`inventory-row-${focusInventoryId}`);
        const branchContainer = document.getElementById(`branch-${focusBranch}-container`);

        if (!row || !branchContainer) return;

        branchContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.outline = '2px solid rgb(239 68 68)';
            row.style.outlineOffset = '-2px';
        }, 150);
    }

    const debounce = (func, delay) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => func(...args), delay);
        };
    };

    function fetchTable(branchId) {
        const searchInput = document.getElementById(`search-branch-${branchId}`);
        const filterSelect = document.getElementById(`filter-branch-${branchId}`);
        const container = document.getElementById(`branch-${branchId}-container`);

        if (!searchInput || !filterSelect || !container) return;

        const search = searchInput.value.trim();
        const filter = filterSelect.value;

        const url = new URL(baseUrl);
        url.searchParams.set('branch', branchId);

        if (search) url.searchParams.set(`search_branch_${branchId}`, search);
        if (filter) url.searchParams.set(`filter_branch_${branchId}`, filter);

        branchIds.filter(id => Number(id) !== Number(branchId)).forEach(other => {
            url.searchParams.delete(`search_branch_${other}`);
            url.searchParams.delete(`filter_branch_${other}`);
            url.searchParams.delete(`page_branch_${other}`);
        });

        fetch(url.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
            attachTableListeners();
            focusInventoryRow();

            const exportSearch = document.getElementById(`export-search-branch-${branchId}`);
            const exportFilter = document.getElementById(`export-filter-branch-${branchId}`);
            if (exportSearch) exportSearch.value = search;
            if (exportFilter) exportFilter.value = filter;
        });
    }

    function attachTableListeners() {
        if (typeof attachTransferButtonListeners === 'function') {
            attachTransferButtonListeners();
        }
        if (typeof attachEditButtonListeners === 'function') {
            attachEditButtonListeners();
        }
    }

    branchIds.forEach(branchId => {
        const searchInput = document.getElementById(`search-branch-${branchId}`);
        const filterSelect = document.getElementById(`filter-branch-${branchId}`);
        const container = document.getElementById(`branch-${branchId}-container`);

        if (!searchInput || !filterSelect || !container) return;

        searchInput.addEventListener('keyup', debounce(() => fetchTable(branchId), 500));
        filterSelect.addEventListener('change', () => fetchTable(branchId));

        container.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (!link || !link.classList.contains('pagination-link')) return;
            e.preventDefault();

            const url = new URL(link.href);
            const currentSearch = searchInput.value.trim();
            const currentFilter = filterSelect.value;

            url.searchParams.set('branch', branchId);

            if (currentSearch) url.searchParams.set(`search_branch_${branchId}`, currentSearch);
            if (currentFilter) url.searchParams.set(`filter_branch_${branchId}`, currentFilter);

            branchIds.filter(id => Number(id) !== Number(branchId)).forEach(other => {
                url.searchParams.delete(`search_branch_${other}`);
                url.searchParams.delete(`filter_branch_${other}`);
                url.searchParams.delete(`page_branch_${other}`);
            });

            fetch(url.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => {
                    container.innerHTML = html;
                    attachTableListeners();
                    focusInventoryRow();
                });
        });
    });

    const transferModal = document.getElementById('transferstockmodal');

    window.attachTransferButtonListeners = function() {
        document.querySelectorAll('.transfer-stock-btn').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });

        document.querySelectorAll('.transfer-stock-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const data = this.dataset;
                const destinationSelect = document.getElementById('destination_branch');
                const confirmBtn = document.getElementById('confirm-transfer-btn');

                document.getElementById('transfer-inventory-id').value = data.stockId;
                document.getElementById('transfer-product-name').textContent = `${data.product} ${data.strength} ${data.form}`;
                document.getElementById('transfer-batch').textContent = data.batch;
                document.getElementById('transfer-current-branch').textContent = data.branch;
                document.getElementById('transfer-available-qty').textContent = data.quantity;
                document.getElementById('transfer-source-branch-id').value = data.branchId;

                const qtyInput = document.getElementById('transfer_qty');
                qtyInput.max = data.quantity;

                if (destinationSelect) {
                    const sourceId = String(data.branchId);
                    let firstAvailable = null;

                    Array.from(destinationSelect.options).forEach(option => {
                        option.disabled = option.value === sourceId;
                        if (!option.disabled && !firstAvailable) {
                            firstAvailable = option;
                        }
                    });

                    if (firstAvailable) {
                        destinationSelect.value = firstAvailable.value;
                        confirmBtn.disabled = false;
                    } else {
                        confirmBtn.disabled = true;
                    }
                }

                if (transferModal) transferModal.classList.remove('hidden');
            });
        });
    };

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', () => transferModal && transferModal.classList.add('hidden'));
    });

    attachTransferButtonListeners();
    focusInventoryRow();

    if (window.inventoryModalValidation) {
        window.inventoryModalValidation.bindValidatedModalSubmit({
            buttonId: 'confirm-transfer-btn',
            formId: 'transfer-form',
            confirmIcon: 'warning',
            confirmText: 'Confirm stock transfer?',
            confirmButtonText: 'Transfer',
            validate: () => {
                const qtyInput = document.getElementById('transfer_qty');
                const availableQty = parseInt(document.getElementById('transfer-available-qty').textContent, 10);
                const requestedQty = parseInt(qtyInput?.value, 10);
                const destination = document.getElementById('destination_branch')?.value;
                const sourceBranch = document.getElementById('transfer-source-branch-id')?.value;

                if (!requestedQty || requestedQty <= 0) {
                    return {
                        title: 'Invalid Quantity',
                        text: 'Please enter a valid quantity.',
                        icon: 'error',
                    };
                }

                if (requestedQty > availableQty) {
                    return {
                        title: 'Not Enough Stock',
                        text: 'The requested transfer quantity exceeds available stock.',
                        icon: 'error',
                    };
                }

                if (String(destination) === String(sourceBranch)) {
                    return {
                        title: 'Invalid Destination',
                        text: 'Please select a different destination branch.',
                        icon: 'error',
                    };
                }

                return true;
            }
        });
    }
});
</script>
