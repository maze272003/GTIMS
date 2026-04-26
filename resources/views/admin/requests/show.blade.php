<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.requests.index') }}" class="hover:underline">Requests</a> / <span class="text-red-700 dark:text-red-300 font-medium">Order #{{ $order->id }}</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Order Request #{{ $order->id }}</h2>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.requests.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                    @if(!$order->received_at)
                        <button type="button" id="openReceiveModalBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm">
                            <i class="fa-regular fa-box mr-1"></i> Order Receive
                        </button>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Branch</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $order->branch->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Requester</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $order->user->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                        @if($order->received_at)
                            <x-badge variant="success">Received</x-badge>
                        @else
                            <x-badge variant="warning">Approved</x-badge>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ optional($order->created_at)->format('M d, Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Admin Approved</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ optional($order->admin_approved_at)->format('M d, Y H:i') ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Finance Approved</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ optional($order->finance_approved_at)->format('M d, Y H:i') ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Received By</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $order->receiver?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Received At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ optional($order->received_at)->format('M d, Y H:i') ?: '-' }}</p>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Remarks</p>
                        <p class="text-gray-700 dark:text-gray-300">{{ $order->remarks ?: '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Order Details</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium text-center">Requested Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ $item->product->generic_name ?? '-' }} ({{ $item->product->brand_name ?? '-' }})
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center font-bold">{{ (int) $item->quantity_requested }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    @if(!$order->received_at)
        <div id="receiveOrderModal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm items-center justify-center p-4 z-50 hidden overflow-y-auto">
            <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl shadow-xl border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Receive Order #{{ $order->id }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Enter the delivered batches, quantity, and expiry date for each order item.</p>
                    </div>
                    <button type="button" id="closeReceiveModalBtn" class="text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form action="{{ route('admin.requests.fulfill', $order->id) }}" method="POST" id="receiveOrderForm">
                    @csrf
                    <div class="max-h-[70vh] overflow-y-auto px-6 py-5 space-y-6">
                        @foreach($order->items as $item)
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4" data-order-item-card>
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-4">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $item->product->generic_name ?? '-' }} ({{ $item->product->brand_name ?? '-' }})</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Ordered quantity: <span class="font-semibold">{{ (int) $item->quantity_requested }}</span></p>
                                    </div>
                                    <button type="button" class="add-batch-row inline-flex items-center px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm transition" data-batches-target="batches-{{ $item->id }}">
                                        <i class="fa-solid fa-plus mr-2"></i> Add Batch
                                    </button>
                                </div>

                                <div id="batches-{{ $item->id }}" class="space-y-3 batch-group" data-item-id="{{ $item->id }}" data-required-qty="{{ (int) $item->quantity_requested }}">
                                    <div class="batch-row rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
                                        <div class="flex justify-end mb-3">
                                            <button type="button" class="remove-batch-row rounded-lg border border-red-200 text-red-600 hover:bg-red-50 px-3 py-2 hidden">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-11 gap-3">
                                        <div class="md:col-span-4">
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Batch Number</label>
                                            <input type="text" name="items[{{ $item->id }}][batches][0][batch_number]" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" required>
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Quantity</label>
                                            <input type="number" min="1" name="items[{{ $item->id }}][batches][0][quantity]" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 batch-quantity-input" required>
                                        </div>
                                        <div class="md:col-span-4">
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Expiry Date</label>
                                            <input type="date" name="items[{{ $item->id }}][batches][0][expiry_date]" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" required>
                                        </div>
                                        <div class="md:col-span-4"></div>
                                        </div>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    Total entered: <span class="font-semibold batch-total">0</span> / {{ (int) $item->quantity_requested }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
                        <button type="button" id="cancelReceiveModalBtn" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">Receive Order</button>
                    </div>
                </form>
            </div>
        </div>

        <template id="batchRowTemplate">
            <div class="batch-row rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
                <div class="flex justify-end mb-3">
                    <button type="button" class="remove-batch-row rounded-lg border border-red-200 text-red-600 hover:bg-red-50 px-3 py-2">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-11 gap-3">
                <div class="md:col-span-4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Batch Number</label>
                    <input type="text" data-field="batch_number" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" required>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Quantity</label>
                    <input type="number" min="1" data-field="quantity" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 batch-quantity-input" required>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Expiry Date</label>
                    <input type="date" data-field="expiry_date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" required>
                </div>
                <div class="md:col-span-4"></div>
                </div>
            </div>
        </template>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('receiveOrderModal');
                const template = document.getElementById('batchRowTemplate');

                const toggleModal = (show) => {
                    modal.classList.toggle('hidden', !show);
                    modal.classList.toggle('flex', show);
                    document.body.classList.toggle('overflow-hidden', show);
                };

                const updateBatchGroupTotals = (group) => {
                    const total = Array.from(group.querySelectorAll('.batch-quantity-input'))
                        .reduce((sum, input) => sum + Number(input.value || 0), 0);
                    const totalLabel = group.closest('[data-order-item-card]').querySelector('.batch-total');
                    totalLabel.textContent = String(total);
                };

                const syncBatchRowNames = (group) => {
                    const itemId = group.dataset.itemId;
                    group.querySelectorAll('.batch-row').forEach((row, index) => {
                        row.querySelector('[data-field="batch_number"], input[name*="[batch_number]"]').name = `items[${itemId}][batches][${index}][batch_number]`;
                        row.querySelector('[data-field="quantity"], input[name*="[quantity]"]').name = `items[${itemId}][batches][${index}][quantity]`;
                        row.querySelector('[data-field="expiry_date"], input[name*="[expiry_date]"]').name = `items[${itemId}][batches][${index}][expiry_date]`;
                        row.querySelector('.remove-batch-row').classList.toggle('hidden', group.querySelectorAll('.batch-row').length === 1);
                    });
                    updateBatchGroupTotals(group);
                };

                document.getElementById('openReceiveModalBtn').addEventListener('click', () => toggleModal(true));
                document.getElementById('closeReceiveModalBtn').addEventListener('click', () => toggleModal(false));
                document.getElementById('cancelReceiveModalBtn').addEventListener('click', () => toggleModal(false));

                document.querySelectorAll('.add-batch-row').forEach((button) => {
                    button.addEventListener('click', function () {
                        const group = document.getElementById(this.dataset.batchesTarget);
                        if (!group || !template) {
                            return;
                        }

                        group.appendChild(template.content.cloneNode(true));
                        syncBatchRowNames(group);
                    });
                });

                document.querySelectorAll('.batch-group').forEach((group) => {
                    syncBatchRowNames(group);
                    group.addEventListener('input', function () {
                        updateBatchGroupTotals(group);
                    });

                    group.addEventListener('click', function (event) {
                        const removeButton = event.target.closest('.remove-batch-row');
                        if (!removeButton) {
                            return;
                        }

                        const rows = group.querySelectorAll('.batch-row');
                        if (rows.length === 1) {
                            return;
                        }

                        removeButton.closest('.batch-row').remove();
                        syncBatchRowNames(group);
                    });
                });

                document.getElementById('receiveOrderForm').addEventListener('submit', function (event) {
                    let invalid = false;

                    document.querySelectorAll('.batch-group').forEach((group) => {
                        const requiredQty = Number(group.dataset.requiredQty || 0);
                        const total = Array.from(group.querySelectorAll('.batch-quantity-input'))
                            .reduce((sum, input) => sum + Number(input.value || 0), 0);

                        if (total !== requiredQty) {
                            invalid = true;
                        }
                    });

                    if (invalid) {
                        event.preventDefault();
                        Swal.fire({
                            title: 'Quantity Mismatch',
                            text: 'The total batch quantity for each product must exactly match the ordered quantity.',
                            icon: 'warning',
                            confirmButtonText: 'OK',
                        });
                    }
                });
            });
        </script>
    @endif
</x-app-layout>
