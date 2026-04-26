<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-20 flex justify-between items-center">
                <div>
                    <div class="flex gap-2 items-center font-semibold mb-4">
                        <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-600 dark:text-gray-400"><i class="fa-regular fa-home mr-2"></i>Dashboard</a>
                        <span><i class="fa-regular fa-angle-right text-gray-600 dark:text-gray-400"></i></span>
                        <p class="text-red-500 dark:text-red-400">Order Stock</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">Create Order</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Enter only the product and quantity. Batches are received later.</p>
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

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Order Items</h3>
                        <button type="button" id="addManualProductBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Add Product
                        </button>
                    </div>

                    <div class="p-6 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                    <th class="py-3 px-4 font-medium min-w-[240px]">Product Name</th>
                                    @foreach($branches as $branch)
                                        <th class="py-3 px-4 font-medium text-center text-blue-600">{{ $branch->name }} Stock</th>
                                    @endforeach
                                    <th class="py-3 px-4 font-medium text-center">Total Stock</th>
                                    <th class="py-3 px-4 font-medium w-48">Quantity to Order</th>
                                    <th class="py-3 px-4 font-medium w-10 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="orderTableBody"></tbody>
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

    <div class="hidden">
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
            const branchList = @json($branches->map(fn ($branch) => ['id' => (int) $branch->id, 'name' => $branch->name])->values());
            const oldItems = @json(old('items', []));

            const tableBody = document.getElementById('orderTableBody');
            const emptyState = document.getElementById('emptyState');
            const addBtn = document.getElementById('addManualProductBtn');
            const submitOrderBtn = document.getElementById('submitOrderBtn');
            const masterSelect = document.getElementById('masterProductSelect');
            const productOptionsHTML = masterSelect ? masterSelect.innerHTML : '<option value="">Error loading products</option>';
            const oldItemsArray = oldItems && typeof oldItems === 'object' ? Object.values(oldItems) : [];

            const getProductStats = (productId) => {
                return stockMap[productId] || stockMap[String(productId)] || { branches: {}, total: 0 };
            };

            const updateStockCells = (row, productId) => {
                const stats = getProductStats(productId);

                branchList.forEach((branch) => {
                    const cell = row.querySelector(`.cell-branch-${branch.id}`);
                    if (!cell) {
                        return;
                    }

                    const qty = Number(stats.branches?.[branch.id] ?? stats.branches?.[String(branch.id)] ?? 0);
                    cell.textContent = productId ? String(qty) : '-';
                });

                const totalCell = row.querySelector('.cell-total');
                totalCell.textContent = productId ? String(Number(stats.total || 0)) : '-';
            };

            window.addItemRow = function ({
                productId = null,
                productName = null,
                branchStocks = {},
                total = 0,
                quantity = 1,
                isManual = false,
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
                        <input type="number" name="items[${rowId}][quantity]" value="${Math.max(1, Number(quantity || 1))}" min="1"
                               class="item-qty-input w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:border-gray-600 text-center font-bold" required>
                    </td>
                    <td class="py-3 px-4 text-center align-middle">
                        <button type="button" class="remove-row-btn text-gray-400 hover:text-red-600 transition p-2 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                `;

                tableBody.appendChild(tr);

                if (isManual) {
                    const select = tr.querySelector('.manual-product-select');

                    if (productId) {
                        select.value = String(productId);
                        updateStockCells(tr, productId);
                    }

                    select.addEventListener('change', function () {
                        updateStockCells(tr, Number(this.value || 0));
                    });
                }

                checkEmptyState();
            };

            if (oldItemsArray.length > 0) {
                oldItemsArray.forEach((item) => {
                    const productId = Number(item.product_id || 0);
                    const stats = getProductStats(productId);
                    addItemRow({
                        productId: productId || null,
                        branchStocks: stats.branches || {},
                        total: stats.total || 0,
                        quantity: Number(item.quantity || 1),
                        isManual: true,
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

            addBtn.addEventListener('click', function (event) {
                event.preventDefault();
                addItemRow({
                    productId: null,
                    branchStocks: {},
                    total: 0,
                    quantity: 1,
                    isManual: true,
                });
            });

            tableBody.addEventListener('click', (event) => {
                if (event.target.closest('.remove-row-btn')) {
                    event.target.closest('tr').remove();
                    checkEmptyState();
                }
            });

            function checkEmptyState() {
                emptyState.classList.toggle('hidden', tableBody.children.length > 0);
            }

            submitOrderBtn.addEventListener('click', function () {
                const form = document.getElementById('orderForm');
                const quantityInputs = form.querySelectorAll('input[name^="items"][name$="[quantity]"]');
                const manualProductSelects = form.querySelectorAll('select[name^="items"][name$="[product_id]"]');

                let hasEmptyQuantity = false;
                let hasEmptyProduct = false;

                quantityInputs.forEach((input) => {
                    const qty = Number(input.value || 0);
                    if (!qty || qty < 1) {
                        hasEmptyQuantity = true;
                    }
                });

                manualProductSelects.forEach((select) => {
                    if (!select.value) {
                        hasEmptyProduct = true;
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

                if (hasEmptyQuantity || hasEmptyProduct) {
                    Swal.fire({
                        title: 'Incomplete Form',
                        text: 'Please fill in product and quantity for all rows.',
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
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Submitting...',
                            text: 'Please wait while we process your order.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                });
            });
        });
    </script>
</x-app-layout>
