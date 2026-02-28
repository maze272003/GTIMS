<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            {{-- ✅ no $hold here --}}
            <div class="mb-6 pt-20 flex justify-between items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home /
                        <a href="{{ route('admin.holds.index') }}" class="hover:underline">Holds</a> /
                        <span class="text-red-700 dark:text-red-300 font-medium">Create</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Create Hold</h2>
                </div>
            </div>

            <form action="{{ route('admin.holds.store') }}" method="POST" id="holdForm">
                @csrf

                {{-- Hold Details --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Hold Details</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Branch <span class="text-red-500">*</span>
                            </label>
                            <select name="branch_id" required
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                                <option value="" disabled {{ old('branch_id') ? '' : 'selected' }}>-- Select Branch --</option>
                                @foreach($branches ?? [] as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- ✅ Barangay/Location --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Barangay / Location
                            </label>
                            <select name="barangay_id"
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                                <option value="" {{ old('barangay_id') ? '' : 'selected' }}>-- Select Barangay --</option>
                                @foreach($barangays ?? [] as $b)
                                    <option value="{{ $b->id }}" {{ old('barangay_id') == $b->id ? 'selected' : '' }}>
                                        {{ $b->barangay_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('barangay_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type <span class="text-red-500">*</span>
                            </label>
                            <select name="type" required
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                                <option value="" disabled {{ old('type') ? '' : 'selected' }}>-- Select Type --</option>
                                <option value="reservation" {{ old('type') == 'reservation' ? 'selected' : '' }}>Reservation</option>
                                <option value="quarantine" {{ old('type') == 'quarantine' ? 'selected' : '' }}>Quarantine</option>
                                <option value="recall" {{ old('type') == 'recall' ? 'selected' : '' }}>Recall</option>
                            </select>
                            @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Reason Code <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="reason_code" value="{{ old('reason_code') }}" required
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500"
                                placeholder="e.g. QUALITY_CHECK">
                            @error('reason_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expires At</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500">
                            @error('expires_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remarks</label>
                            <textarea name="remarks" rows="3"
                                class="w-full border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-red-500"
                                placeholder="Optional notes...">{{ old('remarks') }}</textarea>
                            @error('remarks') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Hold Items --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Hold Items</h3>
                        <button type="button" id="addItemBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </button>
                    </div>

                    <div class="p-6 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                    <th class="py-3 px-4 font-medium min-w-[200px]">Product</th>
                                    <th class="py-3 px-4 font-medium min-w-[220px]">Inventory Batch</th>
                                    <th class="py-3 px-4 font-medium w-40">Quantity</th>
                                    <th class="py-3 px-4 font-medium w-10 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="holdItemsBody"></tbody>
                        </table>

                        <div id="emptyState" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No items added. Click "Add Item" to begin.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pb-10">
                    <a href="{{ route('admin.holds.index') }}"
                       class="px-6 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancel
                    </a>
                    <button type="button" id="submitHoldBtn"
                        class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md transition">
                        Submit Hold
                    </button>
                </div>
            </form>

        </main>
    </div>

    {{-- Hidden selects for cloning --}}
    <div style="display:none;">
        <select id="masterProductSelect">
            <option value="" disabled selected>-- Select Product --</option>
            @foreach($products ?? [] as $product)
                <option value="{{ $product->id }}">{{ $product->generic_name ?? $product->name }}</option>
            @endforeach
        </select>

        <select id="masterBatchSelect">
            <option value="" disabled selected>-- Select Batch --</option>
            @foreach($batches ?? [] as $batch)
                <option value="{{ $batch->id }}" data-product="{{ $batch->product_id }}">
                    {{ $batch->batch_number }} (Available: {{ $batch->available_quantity }}, On-hand: {{ $batch->onhand_qty ?? $batch->quantity }})
                </option>
            @endforeach
        </select>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('holdItemsBody');
        const emptyState = document.getElementById('emptyState');
        const productOptionsHTML = document.getElementById('masterProductSelect').innerHTML;
        const allBatchOptions = document.getElementById('masterBatchSelect').querySelectorAll('option');

        function checkEmpty() {
            emptyState.classList.toggle('hidden', tableBody.children.length > 0);
        }

        document.getElementById('addItemBtn').addEventListener('click', function () {
            const rowId = 'row_' + Date.now() + Math.random().toString(36).slice(2);
            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750 transition';

            tr.innerHTML = `
                <td class="py-3 px-4">
                    <select name="items[${rowId}][product_id]" class="product-select w-full p-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm focus:ring-red-500" required>
                        ${productOptionsHTML}
                    </select>
                </td>

                <td class="py-3 px-4">
                    <select name="items[${rowId}][inventory_id]" class="batch-select w-full p-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm focus:ring-red-500" required>
                        <option value="" disabled selected>-- Select product first --</option>
                    </select>
                </td>

                <td class="py-3 px-4">
                    <input type="number" name="items[${rowId}][quantity]" min="1" value="1"
                        class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-center font-bold focus:ring-red-500" required>
                </td>

                <td class="py-3 px-4 text-center">
                    <button type="button" class="remove-row-btn text-gray-400 hover:text-red-600 transition p-2 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;

            tableBody.appendChild(tr);

            const productSelect = tr.querySelector('.product-select');
            const batchSelect = tr.querySelector('.batch-select');

            productSelect.addEventListener('change', function () {
                const pid = this.value;
                batchSelect.innerHTML = '<option value="" disabled selected>-- Select Batch --</option>';
                allBatchOptions.forEach(opt => {
                    if (opt.dataset.product === pid) batchSelect.appendChild(opt.cloneNode(true));
                });
            });

            checkEmpty();
        });

        tableBody.addEventListener('click', function (e) {
            if (e.target.closest('.remove-row-btn')) {
                e.target.closest('tr').remove();
                checkEmpty();
            }
        });

        document.getElementById('submitHoldBtn').addEventListener('click', function () {
            if (tableBody.children.length === 0) {
                Swal.fire({ title: 'No Items', text: 'Please add at least one item.', icon: 'warning', confirmButtonText: 'OK' });
                return;
            }

            Swal.fire({
                title: 'Confirm Hold',
                text: 'Are you sure you want to create this hold?',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Submitting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    document.getElementById('holdForm').submit();
                }
            });
        });

        checkEmpty();
    });
    </script>
</x-app-layout>
