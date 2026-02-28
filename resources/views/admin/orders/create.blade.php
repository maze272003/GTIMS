<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            
            <div class="mb-6 pt-20 flex justify-between items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / Orders / <span class="text-red-700 dark:text-red-300 font-medium">Create</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Create Replenishment Order</h2>
                </div>
            </div>

            <form action="{{ route('admin.orders.store') }}" method="POST" id="orderForm">
                @csrf
                
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
                                    <th class="py-3 px-4 font-medium min-w-[200px]">Product Name</th>
                                    @foreach($branches as $branch)
                                        <th class="py-3 px-4 font-medium text-center text-blue-600">{{ $branch->name }} Stock</th>
                                    @endforeach
                                    <th class="py-3 px-4 font-medium text-center text-gray-800 dark:text-gray-200">Total Stock</th>
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
                    <textarea name="remarks" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-red-500 focus:border-red-500 p-2" placeholder="Optional notes..."></textarea>
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

    {{-- HIDDEN SELECT FOR CLONING OPTIONS --}}
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
            // 1. Data
            const suggestedItems = @json($suggestedItems);
            const stockMap = @json($stockMap);
            const branchList = @json($branches->map(fn($branch) => ['id' => (int) $branch->id, 'name' => $branch->name]));

            // 2. Elements
            const tableBody = document.getElementById('orderTableBody');
            const emptyState = document.getElementById('emptyState');
            const addBtn = document.getElementById('addManualProductBtn');
            const masterSelect = document.getElementById('masterProductSelect');
            const productOptionsHTML = masterSelect ? masterSelect.innerHTML : '<option>Error loading products</option>';

            // 3. Add Row Function
            window.addItemRow = function (productId = null, productName = null, branchStocks = {}, total = 0, suggestedQty = 1, isManual = false) {
                const rowId = 'row_' + Date.now() + Math.random().toString(36).substr(2, 9);
                const tr = document.createElement('tr');
                tr.className = "border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750 transition group";

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
                    return `<td class=\"cell-branch-${branch.id} py-3 px-4 text-center text-blue-600 font-mono text-sm align-middle bg-blue-50/50 dark:bg-blue-900/10\">${isManual ? '-' : qty}</td>`;
                }).join('');

                tr.innerHTML = `
                    <td class="py-3 px-4 align-middle">${productCellHtml}</td>
                    ${branchCellsHtml}
                    <td class="cell-total py-3 px-4 text-center font-bold font-mono text-sm align-middle">${isManual ? '-' : total}</td>
                    <td class="py-3 px-4 align-middle">
                        <input type="number" name="items[${rowId}][quantity]" value="${suggestedQty}" min="1" 
                               class="w-full p-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:border-gray-600 text-center font-bold" required>
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
                    const cTotal = tr.querySelector('.cell-total');

                    select.addEventListener('change', function () {
                        const pid = this.value;
                        const stats = stockMap[pid] || { branches: {}, total: 0 };

                        branchList.forEach((branch) => {
                            const cell = tr.querySelector(`.cell-branch-${branch.id}`);
                            if (cell) {
                                const qty = Number(stats.branches?.[branch.id] ?? stats.branches?.[String(branch.id)] ?? 0);
                                cell.textContent = qty;
                            }
                        });

                        cTotal.textContent = stats.total;
                    });
                }

                checkEmptyState();
            };

            // 4. Populate Suggested Items
            if (suggestedItems && suggestedItems.length > 0) {
                suggestedItems.forEach(item => {
                    addItemRow(item.product_id, item.product_name, item.branch_stocks || {}, item.total_stock, item.suggested_qty, false);
                });
            } else {
                checkEmptyState();
            }

            // 5. Add Manual Product
            if (addBtn) {
                addBtn.addEventListener('click', e => {
                    e.preventDefault();
                    addItemRow(null, null, {}, 0, 100, true);
                });
            }

            // 6. Remove Row
            tableBody.addEventListener('click', e => {
                if (e.target.closest('.remove-row-btn')) {
                    e.target.closest('tr').remove();
                    checkEmptyState();
                }
            });

            // 7. Check Empty State
            function checkEmptyState() {
                emptyState.classList.toggle('hidden', tableBody.children.length > 0);
            }

            // 8. Submit Button with SweetAlert2 Confirmation
            document.getElementById('submitOrderBtn').addEventListener('click', function () {
                const form = document.getElementById('orderForm');
                const quantityInputs = form.querySelectorAll('input[name^="items"][name$="[quantity]"]');
                const productSelects = form.querySelectorAll('select[name^="items"][name$="[product_id]"]');

                let hasEmptyQuantity = false;
                let hasEmptyProduct = false;

                quantityInputs.forEach(input => {
                    if (!input.value || input.value.trim() === '' || parseInt(input.value) < 1) {
                        hasEmptyQuantity = true;
                    }
                });

                productSelects.forEach(select => {
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
                        allowOutsideClick: false,
                        customClass: {
                            container: 'swal-container',
                            popup: 'swal-popup',
                            title: 'swal-title',
                            htmlContainer: 'swal-content',
                            confirmButton: 'swal-confirm-button',
                            icon: 'swal-icon'
                        }
                    });
                    return;
                }

                if (hasEmptyQuantity || hasEmptyProduct) {
                    Swal.fire({
                        title: 'Incomplete Form',
                        text: 'Please fill in all product selections and quantities before submitting.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false,
                        customClass: {
                            container: 'swal-container',
                            popup: 'swal-popup',
                            title: 'swal-title',
                            htmlContainer: 'swal-content',
                            confirmButton: 'swal-confirm-button',
                            icon: 'swal-icon'
                        }
                    });
                    return;
                }

                Swal.fire({
                    title: 'Confirm Submission',
                    text: "Are you sure you want to submit this replenishment order for approval?",
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
        });
    </script>
</x-app-layout>
