<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-20 flex justify-between items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.suppliers.index') }}" class="hover:underline">Suppliers</a> / <span class="text-red-700 dark:text-red-300 font-medium">Edit</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Edit Supplier: {{ $supplier->name }}</h2>
                </div>
            </div>

            {{-- Edit Supplier Info --}}
            <form action="{{ route('admin.suppliers.update', $supplier->id) }}" method="POST">
                @csrf @method('PUT')
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Supplier Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Person</label>
                            <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('contact_person') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <textarea name="address" rows="3" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">{{ old('address', $supplier->address) }}</textarea>
                            @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mb-6">
                    <a href="{{ route('admin.suppliers.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md transition">
                        <i class="fa-solid fa-save mr-1"></i> Update Supplier
                    </button>
                </div>
            </form>

            @php
                $availableInventories = collect($availableInventories ?? []);
                $productDropdownOptions = $availableInventories
                    ->map(fn($inventory) => $inventory->product)
                    ->filter()
                    ->unique('id')
                    ->sortBy(fn($product) => strtolower(trim(($product->generic_name ?? '') . ' ' . ($product->brand_name ?? ''))))
                    ->values();

                $inventoryDropdownData = $availableInventories->map(function ($inventory) {
                    $branch = $inventory->branch;

                    return [
                        'id' => (int) $inventory->id,
                        'product_id' => (int) $inventory->product_id,
                        'branch_id' => (int) ($inventory->branch_id ?? 0),
                        'branch_name' => $branch?->name ?? 'Unknown Branch',
                        'batch_number' => (string) ($inventory->batch_number ?? 'N/A'),
                        'quantity' => (int) ($inventory->quantity ?? 0),
                        'expiry_date' => optional($inventory->expiry_date)->format('Y-m-d'),
                    ];
                })->values();
            @endphp

            {{-- Linked Inventory Batches --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Linked Inventory Batches</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Supplier links are tracked per inventory record (product, location, batch).</p>
                    </div>
                    <button
                        type="button"
                        id="open-link-inventory-modal"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg text-sm transition shadow-sm"
                        @disabled($availableInventories->isEmpty())
                    >
                        <i class="fa-solid fa-plus mr-2"></i> Add Product Batch
                    </button>
                </div>

                @if($availableInventories->isEmpty())
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 text-sm">
                        No available inventory batches to link. Active batches may already be linked.
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium">Location</th>
                                <th class="py-3 px-4 font-medium">Batch No.</th>
                                <th class="py-3 px-4 font-medium text-center">Qty</th>
                                <th class="py-3 px-4 font-medium text-center">Expiry</th>
                                <th class="py-3 px-4 font-medium text-center">Lead Time (days)</th>
                                <th class="py-3 px-4 font-medium text-center">Unit Cost</th>
                                <th class="py-3 px-4 font-medium text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($supplier->supplierProducts ?? [] as $link)
                                @php
                                    $inventory = $link->inventory;
                                    $product = $inventory?->product;
                                    $branch = $inventory?->branch;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ $product?->generic_name ?? $product?->name ?? 'Unknown Product' }}
                                        @if($product?->brand_name)
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $product->brand_name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $branch?->name ?? 'Unknown Branch' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono">{{ $inventory?->batch_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $inventory?->quantity ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ optional($inventory?->expiry_date)->format('Y-m-d') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $link->lead_time_days ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">{{ $link->unit_cost ? 'PHP ' . number_format((float) $link->unit_cost, 2) : '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <form action="{{ route('admin.suppliers.unlink-inventory', [$supplier->id, $inventory?->id ?? 0]) }}" method="POST" class="inline" id="unlink-form-{{ $link->id }}">
                                            @csrf @method('DELETE')
                                            <button type="button" class="text-red-600 hover:text-red-800 transition" aria-label="Unlink inventory batch" onclick="gtConfirm({ title: 'Unlink Inventory Batch?', text: 'This batch will be unlinked from this supplier.', icon: 'warning', confirmText: 'Yes, unlink', onConfirm: function() { document.getElementById('unlink-form-{{ $link->id }}').submit(); } })">
                                                <i class="fa-solid fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No inventory batches linked to this supplier.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Link Inventory Modal --}}
            <div id="link-inventory-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="link-inventory-modal-title">
                <div class="absolute inset-0 bg-black/50" data-modal-close></div>
                <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
                    <div class="w-full max-w-2xl mt-12 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <h3 id="link-inventory-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Add Product Batch to Supplier</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select product/location/batch from database dropdowns, then enter the purchase cost manually.</p>
                            </div>
                            <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" data-modal-close aria-label="Close modal">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <form action="{{ route('admin.suppliers.link-inventory', $supplier->id) }}" method="POST" id="link-inventory-form">
                            @csrf
                            <div class="p-5 space-y-4">
                                <div>
                                    <label for="supplier-link-product-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
                                    <select id="supplier-link-product-select" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500" {{ $availableInventories->isEmpty() ? 'disabled' : '' }}>
                                        <option value="">-- Select Product --</option>
                                        @foreach($productDropdownOptions as $productOption)
                                            <option value="{{ $productOption->id }}">
                                                {{ $productOption->generic_name ?? $productOption->name }}
                                                @if($productOption->brand_name)
                                                    ({{ $productOption->brand_name }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="supplier-link-branch-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location</label>
                                    <select id="supplier-link-branch-select" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500" disabled>
                                        <option value="">-- Select Location --</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="supplier-link-batch-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batch Number</label>
                                    <select name="inventory_id" id="supplier-link-batch-select" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500" required disabled>
                                        <option value="">-- Select Batch --</option>
                                    </select>
                                    @error('inventory_id')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="supplier-link-cost-input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cost</label>
                                    <input
                                        type="number"
                                        name="unit_cost"
                                        id="supplier-link-cost-input"
                                        min="0"
                                        step="0.01"
                                        value="{{ old('unit_cost') }}"
                                        placeholder="Enter purchase price"
                                        class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500"
                                    >
                                    @error('unit_cost')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="rounded-lg border border-blue-100 dark:border-blue-900 bg-blue-50 dark:bg-blue-900/20 px-3 py-2 text-xs text-blue-800 dark:text-blue-200">
                                    Select the batch from the dropdowns, then enter the exact purchase cost manually if available.
                                </div>
                            </div>

                            <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 bg-gray-50 dark:bg-gray-900/50">
                                <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition" data-modal-close>
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition" {{ $availableInventories->isEmpty() ? 'disabled' : '' }}>
                                    <i class="fa-solid fa-link mr-1"></i> Link Batch
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modal = document.getElementById('link-inventory-modal');
                    const openBtn = document.getElementById('open-link-inventory-modal');
                    if (!modal || !openBtn) {
                        return;
                    }

                    const productSelect = document.getElementById('supplier-link-product-select');
                    const branchSelect = document.getElementById('supplier-link-branch-select');
                    const batchSelect = document.getElementById('supplier-link-batch-select');
                    const closeTargets = modal.querySelectorAll('[data-modal-close]');
                    const inventoryRows = @json($inventoryDropdownData);

                    const resetSelect = (selectEl, placeholder, disabled = true) => {
                        selectEl.innerHTML = '';
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = placeholder;
                        selectEl.appendChild(option);
                        selectEl.value = '';
                        selectEl.disabled = disabled;
                    };

                    const openModal = () => {
                        modal.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    };

                    const closeModal = () => {
                        modal.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    };

                    const populateBranches = () => {
                        const selectedProductId = Number(productSelect.value || 0);
                        resetSelect(branchSelect, '-- Select Location --');
                        resetSelect(batchSelect, '-- Select Batch --');

                        if (!selectedProductId) {
                            return;
                        }

                        const branchMap = new Map();
                        inventoryRows
                            .filter(item => item.product_id === selectedProductId)
                            .forEach(item => {
                                if (!branchMap.has(item.branch_id)) {
                                    branchMap.set(item.branch_id, item.branch_name);
                                }
                            });

                        Array.from(branchMap.entries())
                            .sort((a, b) => String(a[1]).localeCompare(String(b[1])))
                            .forEach(([branchId, branchName]) => {
                                const option = document.createElement('option');
                                option.value = String(branchId);
                                option.textContent = branchName;
                                branchSelect.appendChild(option);
                            });

                        branchSelect.disabled = branchMap.size === 0;
                    };

                    const populateBatches = () => {
                        const selectedProductId = Number(productSelect.value || 0);
                        const selectedBranchId = Number(branchSelect.value || 0);
                        resetSelect(batchSelect, '-- Select Batch --');

                        if (!selectedProductId || !selectedBranchId) {
                            return;
                        }

                        inventoryRows
                            .filter(item => item.product_id === selectedProductId && item.branch_id === selectedBranchId)
                            .sort((a, b) => {
                                if (String(a.batch_number) === String(b.batch_number)) {
                                    return a.id - b.id;
                                }

                                return String(a.batch_number).localeCompare(String(b.batch_number));
                            })
                            .forEach(item => {
                                const option = document.createElement('option');
                                option.value = String(item.id);
                                option.textContent = `${item.batch_number} | Qty: ${item.quantity}${item.expiry_date ? ` | Exp: ${item.expiry_date}` : ''}`;
                                batchSelect.appendChild(option);
                            });

                        batchSelect.disabled = batchSelect.options.length <= 1;
                    };

                    openBtn.addEventListener('click', openModal);
                    closeTargets.forEach(el => el.addEventListener('click', closeModal));

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });

                    productSelect?.addEventListener('change', populateBranches);
                    branchSelect?.addEventListener('change', populateBatches);

                    const oldInventoryId = Number(@json(old('inventory_id')) || 0);
                    if (oldInventoryId) {
                        const selectedRow = inventoryRows.find(item => item.id === oldInventoryId);
                        if (selectedRow) {
                            productSelect.value = String(selectedRow.product_id);
                            populateBranches();
                            branchSelect.value = String(selectedRow.branch_id);
                            populateBatches();
                            batchSelect.value = String(selectedRow.id);
                        }
                        openModal();
                    }
                });
            </script>

        </main>
    </div>
</x-app-layout>
