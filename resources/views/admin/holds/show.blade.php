<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.holds.index') }}" class="hover:underline">Holds</a> / <span class="text-red-700 dark:text-red-300 font-medium">#{{ $hold->id }}</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Hold #{{ $hold->id }}</h2>
                </div>
                <div class="flex gap-3">
                    @if($hold->status === 'pending')
                        <form action="{{ route('admin.holds.update', $hold->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="approve">
                            <button type="button" class="approve-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                        </form>
                    @endif
                    @if(in_array($hold->status, ['pending', 'approved']))
                        <form action="{{ route('admin.holds.update', $hold->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="release">
                            <button type="button" class="release-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-unlock"></i> Release
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.holds.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div id="successAlert" class="fixed top-24 right-5 border-l-4 border-green-500 bg-white text-green-700 py-3 px-6 rounded-lg shadow-lg z-50 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-2xl"></i>
                    <div><p class="font-bold">Success!</p><p class="text-black">{{ session('success') }}</p></div>
                </div>
                <script>setTimeout(() => { const a = document.getElementById('successAlert'); if (a) a.remove(); }, 4000);</script>
            @endif

            {{-- Hold Header --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Branch</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->branch->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Type</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($hold->type === 'reservation') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                            @elseif($hold->type === 'quarantine') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 @endif">
                            {{ ucfirst($hold->type) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($hold->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                            @elseif($hold->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                            @elseif($hold->status === 'released') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                            {{ ucfirst($hold->status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Reason Code</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->reason_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Created By</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->creator->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Expires At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $hold->expires_at ? \Carbon\Carbon::parse($hold->expires_at)->format('M d, Y H:i') : 'Never' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Remarks</p>
                        <p class="text-gray-700 dark:text-gray-300">{{ $hold->remarks ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Hold Items --}}
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
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">{{ $item->product->generic_name ?? $item->product->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-purple-700 dark:text-purple-400 font-bold">{{ $item->inventory->batch_number ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center font-bold text-gray-900 dark:text-white">{{ $item->quantity }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Status History Timeline --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Status History</h3>
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                    @forelse($hold->statusHistory ?? [] as $history)
                        <div class="relative pl-10 pb-6 last:pb-0">
                            <div class="absolute left-2.5 w-3 h-3 rounded-full
                                @if($history->status === 'approved') bg-green-500
                                @elseif($history->status === 'pending') bg-yellow-500
                                @elseif($history->status === 'released') bg-blue-500
                                @else bg-gray-400 @endif"></div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($history->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                    @elseif($history->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                    @elseif($history->status === 'released') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                    {{ ucfirst($history->status) }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">by {{ $history->user->name ?? 'System' }}</span>
                                <span class="text-xs text-gray-400">{{ $history->created_at->format('M d, Y H:i') }}</span>
                            </div>
                            @if($history->remarks)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $history->remarks }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="pl-10 text-gray-500 dark:text-gray-400 text-sm">No status history available.</p>
                    @endforelse
                </div>
            </div>

        </main>
    </div>

    <script>
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const form = this.closest('form');
            Swal.fire({ title: 'Approve Hold?', text: 'This hold will be approved.', icon: 'info', showCancelButton: true, confirmButtonText: 'Confirm' })
                .then(r => { if (r.isConfirmed) { Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); form.submit(); } });
        });
    });
    document.querySelectorAll('.release-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const form = this.closest('form');
            Swal.fire({ title: 'Release Hold?', text: 'This will release all held items back to inventory.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Release' })
                .then(r => { if (r.isConfirmed) { Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); form.submit(); } });
        });
    });
    </script>
</x-app-layout>
