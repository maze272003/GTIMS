<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-20 flex justify-between items-center">
                <div class="">
                    <div class="flex gap-2 items-center font-semibold mb-4">
                        <a href="{{route('admin.dashboard')}}" class="text-sm text-gray-600 dark:text-gray-400"><i class="fa-regular fa-home mr-2"></i>Dashboard</a>
                        <span><i class="fa-regular fa-angle-right text-gray-600 dark:text-gray-400"></i></span>
                        <p class="text-red-500 dark:text-red-400">Order Stock</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">Orders Overview</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Can create and view orders.</p>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 text-red-700 p-4 text-sm">
                    <p class="font-semibold mb-1">Please fix the following before submitting:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.orders.store') }}" method="POST" id="orderForm">
                @csrf

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Source</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="sourceBranchSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Source Branch <span class="text-red-600">*</span>
                            </label>
                            <select
                                id="sourceBranchSelect"
                                name="source_branch_id"
                                class="w-full p-2.5 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm focus:ring-red-500 focus:border-red-500"
                                required
                            >
                                <option value="">-- Select Source Branch --</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (int) $defaultSourceBranchId === (int) $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_branch_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="sourceInventoryLoading" class="hidden md:pt-8 text-sm text-blue-700 dark:text-blue-300">
                            Refreshing source inventory...
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Order Items</h3>
                        <button type="button" id="addManualProductBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Add Product Manually
                        </button>
                    </div>

                    <div class="p-6 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                    <th class="py-3 px-4 font-medium min-w-[220px]">Product Name</th>
                                    @foreach($branches as $branch)
                                        <th class="py-3 px-4 font-medium text-center text-blue-600">{{ $branch->name }} Stock</th>
                                    @endforeach
                                    <th class="py-3 px-4 font-medium text-center text-gray-800 dark:text-gray-200">Total Stock</th>
                                    <th class="py-3 px-4 font-medium min-w-[320px]">Source Batch / Lot</th>
                                    <th class="py-3 px-4 font-medium w-48">Quantity to Order</th>
                                    <th class="py-3 px-4 font-medium w-10 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="orderTableBody">
                                {{-- Rows injected by JS --}}
                            </tbody>
                        </table>

                        <div id="emptyState" class="hidden text-center py-8 text-gray-500 dark:text-gray-400">
                            No items in the list. Please add a product.
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remarks / Notes</label>
                    <textarea name="remarks" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-red-500 focus:border-red-500 p-2" placeholder="Optional notes...">{{ old('remarks') }}</textarea>
                </div>

                <div class="flex justify-end gap-3 pb-10">
                    <a href="{{ route('admin.orders.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg bg-white hover:bg-gray-50 transition">Cancel</a>
                    <button type="button" id="submitOrderBtn" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md transition">
                        Submit Order for Approval
                    </button>
                </div>
            </form>

        </main>
    </div>

    <div style="display: none;">
        <select id="masterProductSelect">
            <option value="" disabled selected>-- Select Product --</option>
            @foreach($allProducts as $product)
                <option value="{{ $product->id }}">{{ $product->generic_name }} ({{ $product->brand_name }})</option>
            @endforeach
        </select>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const suggestedItems = @json($suggestedItems);
            const stockMap = @json($stockMap);
            const branchList = @json($branches->map(fn($branch) => ['id' => (int) $branch->id, 'name' => $branch->name])->values());
            const oldItems = @json(old('items', []));
            const sourceInventoryEndpoint = @json(route('admin.orders.source-inventory'));
            const defaultSourceBranchId = @json((int) $defaultSourceBranchId);

            const tableBody = document.getElementById('orderTableBody');
            const emptyState = document.getElementById('emptyState');
            const addBtn = document.getElementById('addManualProductBtn');
            const sourceBranchSelect = document.getElementById('sourceBranchSelect');
            const sourceInventoryLoading = document.getElementById('sourceInventoryLoading');
            const submitOrderBtn = document.getElementById('submitOrderBtn');
            const masterSelect = document.getElementById('masterProductSelect');
            const productOptionsHTML = masterSelect ? masterSelect.innerHTML : '<option value="">Error loading products</option>';

            let branchInventoryMap = {};
            let inventoryLoading = false;

            const oldItemsArray = oldItems && typeof oldItems === 'object'
                ? Object.values(oldItems)
                : [];

            const getProductStats = (productId) => {
                return stockMap[productId] || stockMap[String(productId)] || { branches: {}, total: 0 };
            };

            const getRowProductId = (row) => {
                const manualSelect = row.querySelector('.manual-product-select');
                if (manualSelect) {
                    return Number(manualSelect.value || 0);
                }
                return Number(row.dataset.productId || 0);
            };

            const setInventoryLoadingState = (isLoading) => {
                inventoryLoading = isLoading;
                sourceInventoryLoading.classList.toggle('hidden', !isLoading);
                submitOrderBtn.disabled = isLoading;
                submitOrderBtn.classList.toggle('opacity-60', isLoading);
                submitOrderBtn.classList.toggle('cursor-not-allowed', isLoading);
            };

            const resetBatchSelect = (select) => {
                select.innerHTML = '<option value="">-- Select Batch --</option>';
                select.disabled = true;
            };

            const syncQuantityValidation = (row) => {
                const batchSelect = row.querySelector('.item-batch-select');
                const qtyInput = row.querySelector('.item-qty-input');
                const batchMeta = row.querySelector('.batch-meta');
                const selectedOption = batchSelect?.selectedOptions?.[0];
                const available = Number(selectedOption?.dataset.available || 0);
                const expiry = selectedOption?.dataset.expiry || '-';
                const received = selectedOption?.dataset.received || '-';

                if (selectedOption && selectedOption.value) {
                    qtyInput.max = String(available);
                    batchMeta.textContent = `Avail: ${available} • Exp: ${expiry} • Recv: ${received}`;

                    if (Number(qtyInput.value || 0) > available) {
                        qtyInput.setCustomValidity(`Quantity exceeds available stock (${available}) for the selected batch.`);
                    } else {
                        qtyInput.setCustomValidity('');
                    }
                } else {
                    qtyInput.removeAttribute('max');
                    qtyInput.setCustomValidity('');
                    batchMeta.textContent = '';
                }
            };

            const fillBatchOptionsForRow = (row, { forceDefault = true } = {}) => {
                const batchSelect = row.querySelector('.item-batch-select');
                const batchMeta = row.querySelector('.batch-meta');
                const sourceBranchId = Number(sourceBranchSelect.value || 0);
                const productId = getRowProductId(row);
                const existingValue = batchSelect.value;
                const initialValue = batchSelect.dataset.initialBatch || '';

                resetBatchSelect(batchSelect);
                batchMeta.textContent = '';

                if (!sourceBranchId || !productId) {
                    return;
                }

                const batches = branchInventoryMap[productId] || branchInventoryMap[String(productId)] || [];
                if (!Array.isArray(batches) || batches.length === 0) {
                    batchMeta.textContent = 'No available batches for this product in the selected branch.';
                    return;
                }

                batches.forEach((batch) => {
                    const available = Number(batch.available_quantity || 0);
                    if (available <= 0) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = String(batch.inventory_id);
                    option.textContent = batch.label;
                    option.dataset.available = String(available);
                    option.dataset.expiry = batch.expiry_date || '-';
                    option.dataset.received = batch.received_date || '-';
                    batchSelect.appendChild(option);
                });

                if (batchSelect.options.length <= 1) {
                    batchMeta.textContent = 'No available batches for this product in the selected branch.';
                    return;
                }

                batchSelect.disabled = false;

                const preferred = initialValue || (!forceDefault ? existingValue : '');
                if (preferred && batchSelect.querySelector(`option[value="${preferred}"]`)) {
                    batchSelect.value = preferred;
                } else {
                    batchSelect.selectedIndex = 1;
                }

                batchSelect.dataset.initialBatch = '';
                syncQuantityValidation(row);
            };

            const refreshAllBatchOptions = ({ forceDefault = true } = {}) => {
                tableBody.querySelectorAll('tr').forEach((row) => {
                    fillBatchOptionsForRow(row, { forceDefault });
                });
            };

            const loadBranchInventory = async (branchId, { forceDefault = true } = {}) => {
                branchInventoryMap = {};
                refreshAllBatchOptions({ forceDefault: true });

                if (!branchId) {
                    return;
                }

                setInventoryLoadingState(true);

                try {
                    const url = new URL(sourceInventoryEndpoint, window.location.origin);
                    url.searchParams.set('branch_id', String(branchId));

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load source inventory.');
                    }

                    const payload = await response.json();
                    branchInventoryMap = payload.inventory_by_product || {};
                    refreshAllBatchOptions({ forceDefault });
                } catch (error) {
                    branchInventoryMap = {};
                    refreshAllBatchOptions({ forceDefault: true });
                    Swal.fire({
                        title: 'Inventory Load Failed',
                        text: 'Unable to load available source batches for the selected branch. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false,
                    });
                } finally {
                    setInventoryLoadingState(false);
                }
            };

            window.addItemRow = function ({
                productId = null,
                productName = null,
                branchStocks = {},
                total = 0,
                quantity = 1,
                isManual = false,
                selectedBatchId = null,
            } = {}) {
                const rowId = 'row_' + Date.now() + Math.random().toString(36).slice(2, 9);
                const tr = document.createElement('tr');
                tr.className = 'border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750 transition group';

                let productCellHtml = '';
                if (isManual) {
                    productCellHtml = `
                        <select name="items[${rowId}][product_id]" class="manual-product-select w-full p-2.5 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 text-sm focus:ring-red-500 focus:border-red-500" required>
                            ${productOptionsHTML}
                        </select>
                    `;
                } else {
                    productCellHtml = `
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-800 dark:text-gray-200">${productName}</span>
                            <input type="hidden" name="items[${rowId}][product_id]" value="${productId}">
                            <span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold border border-red-200">Low Stock</span>
                        </div>
                    `;
                }

                const branchCellsHtml = branchList.map((branch) => {
                    const qty = Number(branchStocks?.[branch.id] ?? branchStocks?.[String(branch.id)] ?? 0);
                    const value = isManual && !productId ? '-' : qty;
                    return `<td class="cell-branch-${branch.id} py-3 px-4 text-center text-blue-600 font-mono text-sm align-middle bg-blue-50/50 dark:bg-blue-900/10">${value}</td>`;
                }).join('');

                tr.innerHTML = `
                    <td class="py-3 px-4 align-middle">${productCellHtml}</td>
                    ${branchCellsHtml}
                    <td class="cell-total py-3 px-4 text-center font-bold font-mono text-sm align-middle">${isManual && !productId ? '-' : Number(total || 0)}</td>
                    <td class="py-3 px-4 align-middle">
                        <select name="items[${rowId}][source_inventory_id]" class="item-batch-select w-full p-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 text-xs focus:ring-red-500 focus:border-red-500" data-initial-batch="${selectedBatchId || ''}" required disabled>
                            <option value="">-- Select Batch --</option>
                        </select>
                        <p class="batch-meta mt-1 text-[11px] text-gray-500 dark:text-gray-400"></p>
                    </td>
                    <td class="py-3 px-4 align-middle">
                        <input type="number" name="items[${rowId}][quantity]" value="${Math.max(1, Number(quantity || 1))}" min="1"
                               class="item-qty-input w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:border-gray-600 text-center font-bold" required>
                    </td>
                    <td class="py-3 px-4 text-center align-middle">
                        <button type="button" class="remove-row-btn text-gray-400 hover:text-red-600 transition p-2 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                `;

                if (!isManual && productId) {
                    tr.dataset.productId = String(productId);
                }

                tableBody.appendChild(tr);

                const batchSelect = tr.querySelector('.item-batch-select');
                const qtyInput = tr.querySelector('.item-qty-input');
                batchSelect.addEventListener('change', () => syncQuantityValidation(tr));
                qtyInput.addEventListener('input', () => syncQuantityValidation(tr));

                if (isManual) {
                    const select = tr.querySelector('.manual-product-select');

                    if (productId) {
                        select.value = String(productId);
                    }

                    select.addEventListener('change', function () {
                        const selectedProductId = Number(this.value || 0);
                        const stats = getProductStats(selectedProductId);

                        tr.dataset.productId = selectedProductId ? String(selectedProductId) : '';
                        branchList.forEach((branch) => {
                            const cell = tr.querySelector(`.cell-branch-${branch.id}`);
                            if (!cell) return;
                            const qty = Number(stats.branches?.[branch.id] ?? stats.branches?.[String(branch.id)] ?? 0);
                            cell.textContent = selectedProductId ? String(qty) : '-';
                        });

                        const totalCell = tr.querySelector('.cell-total');
                        totalCell.textContent = selectedProductId ? String(Number(stats.total || 0)) : '-';
                        fillBatchOptionsForRow(tr, { forceDefault: true });
                    });
                }

                fillBatchOptionsForRow(tr, { forceDefault: false });
                checkEmptyState();
            };

            if (oldItemsArray.length > 0) {
                oldItemsArray.forEach((item) => {
                    const productId = Number(item.product_id || 0);
                    const stats = getProductStats(productId);
                    addItemRow({
                        productId: productId || null,
                        productName: null,
                        branchStocks: stats.branches || {},
                        total: stats.total || 0,
                        quantity: Number(item.quantity || 1),
                        isManual: true,
                        selectedBatchId: Number(item.source_inventory_id || 0),
                    });
                });
            } else if (Array.isArray(suggestedItems) && suggestedItems.length > 0) {
                suggestedItems.forEach((item) => {
                    addItemRow({
                        productId: item.product_id,
                        productName: item.product_name,
                        branchStocks: item.branch_stocks || {},
                        total: item.total_stock,
                        quantity: item.suggested_qty,
                        isManual: false,
                    });
                });
            } else {
                checkEmptyState();
            }

            if (addBtn) {
                addBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    addItemRow({
                        productId: null,
                        productName: null,
                        branchStocks: {},
                        total: 0,
                        quantity: 1,
                        isManual: true,
                    });
                });
            }

            tableBody.addEventListener('click', (event) => {
                if (event.target.closest('.remove-row-btn')) {
                    event.target.closest('tr').remove();
                    checkEmptyState();
                }
            });

            sourceBranchSelect.addEventListener('change', () => {
                const sourceBranchId = Number(sourceBranchSelect.value || 0);
                loadBranchInventory(sourceBranchId, { forceDefault: true });
            });

            function checkEmptyState() {
                emptyState.classList.toggle('hidden', tableBody.children.length > 0);
            }

            document.getElementById('submitOrderBtn').addEventListener('click', function () {
                const form = document.getElementById('orderForm');
                const quantityInputs = form.querySelectorAll('input[name^="items"][name$="[quantity]"]');
                const manualProductSelects = form.querySelectorAll('select[name^="items"][name$="[product_id]"]');
                const batchSelects = form.querySelectorAll('select[name^="items"][name$="[source_inventory_id]"]');

                let hasEmptyQuantity = false;
                let hasEmptyProduct = false;
                let hasEmptyBatch = false;
                let hasInvalidBatchQty = false;

                if (inventoryLoading) {
                    Swal.fire({
                        title: 'Please Wait',
                        text: 'Source inventory is still loading for the selected branch.',
                        icon: 'info',
                        confirmButtonText: 'OK',
                    });
                    return;
                }

                if (!sourceBranchSelect.value) {
                    Swal.fire({
                        title: 'Source Branch Required',
                        text: 'Please select the source branch before submitting.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                    });
                    return;
                }

                quantityInputs.forEach((input) => {
                    const qty = Number(input.value || 0);
                    if (!qty || qty < 1) {
                        hasEmptyQuantity = true;
                    }

                    if (!input.checkValidity()) {
                        hasInvalidBatchQty = true;
                    }
                });

                manualProductSelects.forEach((select) => {
                    if (!select.value) {
                        hasEmptyProduct = true;
                    }
                });

                batchSelects.forEach((select) => {
                    if (!select.value) {
                        hasEmptyBatch = true;
                    }
                });

                if (tableBody.children.length === 0) {
                    Swal.fire({
                        title: 'No Items Added',
                        text: 'Please add at least one product before submitting.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                    });
                    return;
                }

                if (hasEmptyQuantity || hasEmptyProduct || hasEmptyBatch) {
                    Swal.fire({
                        title: 'Incomplete Form',
                        text: 'Please fill in product, source batch, and quantity for all rows.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                    });
                    return;
                }

                if (hasInvalidBatchQty) {
                    Swal.fire({
                        title: 'Invalid Quantity',
                        text: 'One or more item quantities exceed batch availability.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                    });
                    return;
                }

                Swal.fire({
                    title: 'Confirm Submission',
                    text: 'Are you sure you want to submit this replenishment order for approval?',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm',
                    cancelButtonText: 'Cancel',
                    allowOutsideClick: false,
                    customClass: {
                        container: 'swal-container',
                        popup: 'swal-popup',
                        title: 'swal-title',
                        htmlContainer: 'swal-content',
                        confirmButton: 'swal-confirm-button',
                        cancelButton: 'swal-cancel-button',
                        icon: 'swal-icon'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Submitting...',
                            text: 'Please wait while we process your order.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            customClass: {
                                container: 'swal-container',
                                popup: 'swal-popup',
                                title: 'swal-title',
                                htmlContainer: 'swal-content',
                                icon: 'swal-icon'
                            },
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            });

            if (defaultSourceBranchId) {
                loadBranchInventory(defaultSourceBranchId, { forceDefault: false });
            }
        });
    </script>
</x-app-layout>
