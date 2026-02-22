<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.holds.index') }}" class="hover:underline">Holds</a> /
                        <span class="text-red-700 dark:text-red-300 font-medium">#{{ $hold->id }}</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Hold #{{ $hold->id }}</h2>
                </div>

                <div class="flex gap-3">
                    @if($hold->status === 'pending')
                        <form action="{{ route('admin.holds.approve', $hold) }}" method="POST" class="inline">
                            @csrf
                            @method('PUT')
                            <button type="button"
                                class="approve-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                        </form>
                    @endif

                    @if(in_array($hold->status, ['pending', 'approved']))
                        <form action="{{ route('admin.holds.release', $hold) }}" method="POST" class="inline">
                            @csrf
                            @method('PUT')
                            <button type="button"
                                class="release-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-unlock"></i> Release
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('admin.holds.index') }}"
                       class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Branch</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->branch->name ?? '-' }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Location</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->barangay->barangay_name ?? '-' }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Type</p>
                        @if($hold->type === 'reservation')
                            <x-badge variant="info">{{ ucfirst($hold->type) }}</x-badge>
                        @elseif($hold->type === 'quarantine')
                            <x-badge variant="warning">{{ ucfirst($hold->type) }}</x-badge>
                        @else
                            <x-badge variant="danger">{{ ucfirst($hold->type) }}</x-badge>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                        @if($hold->status === 'approved')
                            <x-badge variant="success">{{ ucfirst($hold->status) }}</x-badge>
                        @elseif($hold->status === 'pending')
                            <x-badge variant="warning">{{ ucfirst($hold->status) }}</x-badge>
                        @elseif($hold->status === 'released')
                            <x-badge variant="info">{{ ucfirst($hold->status) }}</x-badge>
                        @else
                            <x-badge variant="default">{{ ucfirst($hold->status) }}</x-badge>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Reason Code</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->reason_code ?? '-' }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Created By</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->creator->name ?? '-' }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->created_at?->format('M d, Y H:i') ?? '-' }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Expires At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $hold->expires_at ? \Carbon\Carbon::parse($hold->expires_at)->format('M d, Y H:i') : 'Never' }}
                        </p>
                    </div>

                    <div class="lg:col-span-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Remarks</p>
                        <p class="text-gray-700 dark:text-gray-300">{{ $hold->remarks ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Hold Items</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium">Batch Number</th>
                                <th class="py-3 px-4 font-medium text-center">Quantity Held</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($hold->items ?? [] as $item)
                                @php
                                    $product = $item->product;
                                    $inv = $item->inventory;
                                @endphp

                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                        {{ $product->generic_name ?? $product->name ?? '-' }}
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        <button type="button"
                                            class="open-batch-modal text-purple-700 dark:text-purple-400 font-bold underline decoration-dotted"
                                            data-product="{{ $product->generic_name ?? $product->name ?? '-' }}"
                                            data-batch="{{ $inv->batch_number ?? '-' }}"
                                            data-branch="{{ $hold->branch->name ?? '-' }}"
                                            data-held="{{ $item->quantity ?? '-' }}"
                                            data-qty="{{ $inv->quantity ?? '-' }}"
                                            data-expiry="{{ $inv?->expiry_date ? \Carbon\Carbon::parse($inv->expiry_date)->format('M d, Y') : 'N/A' }}"
                                            data-location="{{ $hold->barangay->barangay_name ?? '-' }}"
                                            data-remarks="{{ $inv->remarks ?? '-' }}"
                                        >
                                            {{ $inv->batch_number ?? '-' }}
                                        </button>
                                    </td>

                                    <td class="px-4 py-3 text-sm text-center font-bold text-gray-900 dark:text-white">
                                        {{ $item->quantity }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No items found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Status History</h3>

                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

                    @forelse($hold->statusHistory ?? [] as $history)
                        @php $st = $history->new_status; @endphp

                        <div class="relative pl-10 pb-6 last:pb-0">
                            <div class="absolute left-2.5 w-3 h-3 rounded-full
                                @if($st === 'approved') bg-green-500
                                @elseif($st === 'pending') bg-yellow-500
                                @elseif($st === 'released') bg-blue-500
                                @else bg-gray-400 @endif"></div>

                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($st === 'approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                    @elseif($st === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                    @elseif($st === 'released') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                    {{ ucfirst($st ?? '-') }}
                                </span>

                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    by {{ $history->changer->name ?? 'System' }}
                                </span>

                                <span class="text-xs text-gray-400">
                                    {{ $history->created_at?->format('M d, Y H:i') ?? '-' }}
                                </span>
                            </div>

                            @if($history->reason)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $history->reason }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="pl-10 text-gray-500 dark:text-gray-400 text-sm">No status history available.</p>
                    @endforelse
                </div>
            </div>

        </main>
    </div>

    <div id="batchModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 relative">
            <button id="closeBatchModal" class="absolute top-3 right-3 text-gray-500 hover:text-black dark:hover:text-white">x</button>

            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Inventory Details</h3>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Product</p>
                    <p id="modalProduct" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Batch</p>
                    <p id="modalBatch" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Branch</p>
                    <p id="modalBranch" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Held Qty</p>
                    <p id="modalHeld" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Available Qty</p>
                    <p id="modalQty" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Expiry</p>
                    <p id="modalExpiry" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div class="col-span-2">
                    <p class="text-gray-500 dark:text-gray-400">Location</p>
                    <p id="modalLocation" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
                <div class="col-span-2">
                    <p class="text-gray-500 dark:text-gray-400">Remarks</p>
                    <p id="modalRemarks" class="font-semibold text-gray-900 dark:text-white"></p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" id="closeBatchModalBtn"
                        class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.approve-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Approve Hold?',
                    text: 'This hold will be approved.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Confirm',
                    cancelButtonText: 'Cancel'
                }).then(r => {
                    if (r.isConfirmed) {
                        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('.release-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('form');
                Swal.fire({
                    title: 'Release Hold?',
                    text: 'This will release all held items back to inventory.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Release',
                    cancelButtonText: 'Cancel'
                }).then(r => {
                    if (r.isConfirmed) {
                        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        form.submit();
                    }
                });
            });
        });

        const modal = document.getElementById('batchModal');
        const closeX = document.getElementById('closeBatchModal');
        const closeBtn = document.getElementById('closeBatchModalBtn');

        function openModalFromButton(btn) {
            document.getElementById('modalProduct').textContent = btn.dataset.product || '-';
            document.getElementById('modalBatch').textContent = btn.dataset.batch || '-';
            document.getElementById('modalBranch').textContent = btn.dataset.branch || '-';
            document.getElementById('modalHeld').textContent = btn.dataset.held || '-';
            document.getElementById('modalQty').textContent = btn.dataset.qty || '-';
            document.getElementById('modalExpiry').textContent = btn.dataset.expiry || '-';
            document.getElementById('modalLocation').textContent = btn.dataset.location || '-';
            document.getElementById('modalRemarks').textContent = btn.dataset.remarks || '-';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.querySelectorAll('.open-batch-modal').forEach(btn => {
            btn.addEventListener('click', () => openModalFromButton(btn));
        });

        closeX.addEventListener('click', closeModal);
        closeBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });
    </script>
</x-app-layout>
