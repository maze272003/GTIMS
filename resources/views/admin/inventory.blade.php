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
                    @if (auth()->user()->hasPermission('inventory.add'))
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
                    @include('admin.partials._inventory_table', [
                        'inventories' => $inventories_rhu1,
                        'branch' => 1,
                        'focusInventoryId' => $focusInventoryId ?? null,
                    ])
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
                    @include('admin.partials._inventory_table', [
                        'inventories' => $inventories_rhu2,
                        'branch' => 2,
                        'focusInventoryId' => $focusInventoryId ?? null,
                    ])
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
    {{-- Included Transfer Modal (was separate in your code, keeping structure) --}}
    {{-- @include('components.admin.modals.inventory.transfer-stock')  --}}
    {{-- transfer into modal if needed - jm --}}
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
                    <input type="number" name="quantity" id="transfer_qty" min="1" required
                           class="w-full mt-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100">
                    <p class="text-xs text-red-500 mt-1 hidden" id="transfer-error">Not enough stock!</p>
                </div>

                <div>
                    <label for="destination_branch" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Transfer To Branch <span class="text-red-500">*</span>
                    </label>
                    <select name="destination_branch" id="destination_branch" required
                            class="w-full mt-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100">
                        <option value="1">RHU 1</option>
                        <option value="2">RHU 2</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 p-6 border-t dark:border-gray-700">
                <button type="button" class="close-modal px-6 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-medium text-gray-700 dark:text-gray-300">
                    Cancel
                </button>
                <button type="button" id="confirm-transfer-btn"
                        class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Transfer Stock
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>

{{-- Note: I assumed the Transfer Modal is in a component. If not, paste it here --}}

<script src="{{ asset('js/inventory.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Error Handling for Modals
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

    function focusInventoryRow() {
        if (!focusInventoryId || !focusBranch) return;

        const row = document.getElementById(`inventory-row-${focusInventoryId}`);
        const branchContainer = document.getElementById(`rhu${focusBranch}-container`);

        if (!row || !branchContainer) return;

        branchContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.outline = '2px solid rgb(239 68 68)';
            row.style.outlineOffset = '-2px';
        }, 150);
    }

    // Debounce function
    const debounce = (func, delay) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => func(...args), delay);
        };
    };

    // --- MAIN SEARCH/FILTER LOGIC ---
    function fetchTable(branch) {
        const searchInput = document.getElementById(`search-rhu${branch}`);
        const filterSelect = document.getElementById(`filter-rhu${branch}`);
        const container = document.getElementById(`rhu${branch}-container`);

        const search = searchInput.value.trim();
        const filter = filterSelect.value;

        const url = new URL(baseUrl);
        
        // CRITICAL FIX: Explicitly set the branch param for the controller
        url.searchParams.set('branch', branch); 

        if (search) url.searchParams.set(`search_rhu${branch}`, search);
        if (filter) url.searchParams.set(`filter_rhu${branch}`, filter);

        // Remove params for the other branch to keep URL clean
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
            
            // Re-attach listeners for buttons inside the newly loaded table
            attachTableListeners(branch);
            focusInventoryRow();

            // Update export hidden fields
            document.getElementById(`export-search-rhu${branch}`).value = search;
            document.getElementById(`export-filter-rhu${branch}`).value = filter;
        });
    }

    // Helper to attach listeners (like Transfer/Edit buttons) after AJAX reload
    function attachTableListeners(branch) {
        // If you have specific listeners for Edit/Transfer buttons inside the table,
        // you should call their initialization function here.
        // For example: if you have a global `attachTransferButtonListeners()` function.
        if (typeof attachTransferButtonListeners === 'function') {
            attachTransferButtonListeners();
        }
        if (typeof attachEditButtonListeners === 'function') {
            attachEditButtonListeners();
        }
    }

    // Initialize Listeners for Both Branches
    [1, 2].forEach(branch => {
        const searchInput = document.getElementById(`search-rhu${branch}`);
        const filterSelect = document.getElementById(`filter-rhu${branch}`);
        const container = document.getElementById(`rhu${branch}-container`);

        // Search (AJAX)
        searchInput.addEventListener('keyup', debounce(() => fetchTable(branch), 500));

        // Filter change (AJAX)
        filterSelect.addEventListener('change', () => fetchTable(branch));

        // Pagination (AJAX)
        container.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (!link || !link.classList.contains('pagination-link')) return;
            e.preventDefault();

            const url = new URL(link.href);
            const currentSearch = searchInput.value.trim();
            const currentFilter = filterSelect.value;

            // CRITICAL FIX: Ensure branch is sent during pagination
            url.searchParams.set('branch', branch);

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
                    attachTableListeners(branch);
                    focusInventoryRow();
                });
        });
    });

    // --- TRANSFER MODAL LOGIC (Re-integrated from your snippet) ---
    const transferModal = document.getElementById('transferstockmodal');

    // Make this global or accessible so we can call it after AJAX
    window.attachTransferButtonListeners = function() {
        document.querySelectorAll('.transfer-stock-btn').forEach(btn => {
            // Remove old listener to prevent duplicates (optional if replacing HTML)
            btn.replaceWith(btn.cloneNode(true)); 
        });

        // Re-select fresh buttons
        document.querySelectorAll('.transfer-stock-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const data = this.dataset;

                document.getElementById('transfer-inventory-id').value = data.stockId;
                document.getElementById('transfer-product-name').textContent = data.product + ' ' + data.strength + ' ' + data.form;
                document.getElementById('transfer-batch').textContent = data.batch;
                document.getElementById('transfer-current-branch').textContent = data.branch;
                document.getElementById('transfer-available-qty').textContent = data.quantity;
                
                // Set max for validation
                document.getElementById('transfer_qty').max = data.quantity;

                // Auto-select opposite branch
                document.getElementById('destination_branch').value = data.branchId == 1 ? 2 : 1;

                if(transferModal) transferModal.classList.remove('hidden');
            });
        });
    }

    // Close modal logic
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', () => transferModal && transferModal.classList.add('hidden'));
    });

    // Initial attachment on page load
    attachTransferButtonListeners();
    focusInventoryRow();

    // Confirm Transfer Logic
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

                return true;
            }
        });
    }
});
</script>
