<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / Settings / <span class="text-red-700 dark:text-red-300 font-medium">Low Stock Thresholds</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-5">
                    Low Stock Threshold Settings
                </h2>
            </div>

            @if (session('error'))
                <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded border border-red-200 bg-red-50 text-red-700 p-3 text-sm">
                    <p class="font-semibold mb-1">Please review the following:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Per-Item Overrides --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Per-Item Overrides</h3>
                </div>

                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <form action="{{ route('admin.lowstock.override') }}" method="POST" class="flex flex-col lg:flex-row gap-4 items-end">
                        @csrf

                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
                            <select name="product_id" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                                <option value="" disabled {{ old('product_id') ? '' : 'selected' }}>-- Select Product --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ (string) old('product_id') === (string) $product->id ? 'selected' : '' }}>
                                        {{ $product->generic_name ?? $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Branch (optional)
                            </label>
                            <select name="branch_id" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                                <option value="">All branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Leave as "All branches" to apply to every branch.
                            </p>
                        </div>

                        <div class="w-full lg:w-40">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Threshold</label>
                            <input type="number" name="threshold" min="1" value="{{ old('threshold') }}" required class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                        </div>

                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm">
                            <i class="fa-solid fa-plus mr-1"></i> Add Override
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium">Branch</th>
                                <th class="py-3 px-4 font-medium text-center">Custom Threshold</th>
                                <th class="py-3 px-4 font-medium text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($overrides as $override)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ $override->product?->generic_name ?? $override->product?->name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $override->branch?->name ?? 'All branches' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center font-bold text-gray-900 dark:text-white">
                                        {{ $override->threshold }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <form action="{{ route('admin.lowstock.override.destroy', $override->id) }}" method="POST" class="inline" id="remove-override-{{ $override->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="text-red-600 hover:text-red-800 transition" aria-label="Remove override" onclick="gtConfirm({ title: 'Remove Setting?', text: 'This low stock override will be permanently removed.', icon: 'warning', confirmText: 'Yes, remove', onConfirm: function() { document.getElementById('remove-override-{{ $override->id }}').submit(); } })">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No overrides configured.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4">
                    {{ $overrides->links() }}
                </div>
            </div>

            {{-- Current Low Stock Alerts --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-orange-500"></i>
                        Current Low Stock Alerts
                        <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            {{ $lowStockItems->total() }}
                        </span>
                    </h3>
                </div>
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <form method="GET" action="{{ route('admin.lowstock.index') }}" class="grid grid-cols-1 lg:grid-cols-5 gap-3 items-end">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <input
                                type="text"
                                name="alert_search"
                                value="{{ $alertSearch }}"
                                placeholder="Search product, batch, or branch"
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch</label>
                            <select id="alertBranchSelect" name="alert_branch_id" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                                <option value="">All branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) $alertBranchId === (string) $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
                            <select id="alertProductSelect" name="alert_product_id" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                                <option value="">All products</option>
                                @foreach($alertProducts as $product)
                                    <option value="{{ $product->id }}" {{ (string) $alertProductId === (string) $product->id ? 'selected' : '' }}>
                                        {{ $product->generic_name ?? $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                All branches uses global rules; selecting a branch scopes this list to that branch inventory.
                            </p>
                        </div>
                        <div id="alertBatchField" class="{{ $alertProductId ? '' : 'hidden' }}">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batch Number</label>
                            <select id="alertBatchSelect" name="alert_batch_id" class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500" {{ $alertProductId ? '' : 'disabled' }}>
                                <option value="">All batches</option>
                                @foreach($alertBatches as $batch)
                                    <option value="{{ $batch['id'] }}" {{ (string) $alertBatchId === (string) $batch['id'] ? 'selected' : '' }}>
                                        {{ $batch['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                FEFO then FIFO. Batches with zero available stock are excluded.
                            </p>
                        </div>
                        <div class="flex gap-2 lg:col-span-5">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm">
                                <i class="fa-solid fa-filter mr-1"></i> Apply Filters
                            </button>
                            <a href="{{ route('admin.lowstock.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium">Batch</th>
                                <th class="py-3 px-4 font-medium">Branch</th>
                                <th class="py-3 px-4 font-medium text-center">Current Stock</th>
                                <th class="py-3 px-4 font-medium text-center">Threshold</th>
                                <th class="py-3 px-4 font-medium text-center">Threshold Source</th>
                                <th class="py-3 px-4 font-medium text-center">Deficit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($lowStockItems as $item)
                                @php
                                    $current = (int)($item['current_stock'] ?? 0);
                                    $thr = (int)($item['threshold'] ?? $globalThreshold ?? 100);
                                    $def = max(0, $thr - $current);
                                    $source = (string)($item['threshold_source'] ?? 'default_threshold');
                                    $sourceLabel = (string)($item['threshold_source_label'] ?? 'Default Threshold');
                                    $sourceClass = match($source) {
                                        'branch_override' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
                                        'global_override' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                    };
                                @endphp

                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ $item['product_name'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if(!empty($item['inventory_id']) && !empty($item['batch_number']))
                                            <a href="{{ route('admin.inventory', ['focus_inventory_id' => $item['inventory_id']]) }}" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                                                {{ $item['batch_number'] }}
                                            </a>
                                        @else
                                            {{ $item['batch_number'] ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $item['branch_name'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center font-bold text-red-600 dark:text-red-400">
                                        {{ $current }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">
                                        {{ $thr }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $sourceClass }}">
                                            {{ $sourceLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                            -{{ $def }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center">
                                            <i class="fa-solid fa-magnifying-glass text-3xl text-gray-400 mb-2"></i>
                                            <p>No low stock records found for the selected filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $lowStockItems->links() }}
                </div>
            </div>

        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect = document.getElementById('alertBranchSelect');
            const productSelect = document.getElementById('alertProductSelect');
            const batchField = document.getElementById('alertBatchField');
            const batchSelect = document.getElementById('alertBatchSelect');
            const filterOptionsEndpoint = @json(route('admin.lowstock.filter-options'));

            if (!branchSelect || !productSelect || !batchField || !batchSelect) {
                return;
            }

            let latestRequest = 0;

            const setBatchFieldVisibility = () => {
                const hasProduct = Boolean(productSelect.value);
                batchField.classList.toggle('hidden', !hasProduct);
                batchSelect.disabled = !hasProduct || batchSelect.options.length <= 1;
            };

            const renderProducts = (products, selectedProductId) => {
                productSelect.innerHTML = '';

                const allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = 'All products';
                productSelect.appendChild(allOption);

                (products || []).forEach((product) => {
                    const option = document.createElement('option');
                    option.value = String(product.id);
                    option.textContent = product.name;
                    productSelect.appendChild(option);
                });

                if (selectedProductId && productSelect.querySelector(`option[value="${selectedProductId}"]`)) {
                    productSelect.value = selectedProductId;
                } else {
                    productSelect.value = '';
                }
            };

            const renderBatches = (batches, selectedBatchId) => {
                batchSelect.innerHTML = '';

                const allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = 'All batches';
                batchSelect.appendChild(allOption);

                (batches || []).forEach((batch) => {
                    const option = document.createElement('option');
                    option.value = String(batch.id);
                    option.textContent = batch.label;
                    batchSelect.appendChild(option);
                });

                if (selectedBatchId && batchSelect.querySelector(`option[value="${selectedBatchId}"]`)) {
                    batchSelect.value = selectedBatchId;
                } else {
                    batchSelect.value = '';
                }

                setBatchFieldVisibility();
            };

            const loadFilterOptions = async ({ preserveBatch = false } = {}) => {
                const requestId = ++latestRequest;
                const selectedProductId = productSelect.value;
                const selectedBatchId = preserveBatch ? batchSelect.value : '';

                const url = new URL(filterOptionsEndpoint, window.location.origin);
                if (branchSelect.value) {
                    url.searchParams.set('branch_id', branchSelect.value);
                }
                if (selectedProductId) {
                    url.searchParams.set('product_id', selectedProductId);
                }

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load filter options');
                    }

                    const payload = await response.json();
                    if (requestId !== latestRequest) {
                        return;
                    }

                    renderProducts(payload.products || [], selectedProductId);

                    if (!productSelect.value) {
                        renderBatches([], '');
                        return;
                    }

                    renderBatches(payload.batches || [], selectedBatchId);
                } catch (error) {
                    console.error(error);
                    renderBatches([], '');
                }
            };

            branchSelect.addEventListener('change', () => {
                loadFilterOptions({ preserveBatch: false });
            });

            productSelect.addEventListener('change', () => {
                loadFilterOptions({ preserveBatch: false });
            });

            setBatchFieldVisibility();
        });
    </script>
</x-app-layout>

