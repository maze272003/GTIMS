<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.requests.index') }}" class="hover:underline">Requests</a> / <span class="text-red-700 dark:text-red-300 font-medium">#{{ $inventoryRequest->id }}</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Request #{{ $inventoryRequest->id }}</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($inventoryRequest->status === 'draft')
                        <form action="{{ route('admin.requests.update', $inventoryRequest->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="submit">
                            <button type="button" class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm" data-confirm="Submit this request?">
                                <i class="fa-solid fa-paper-plane mr-1"></i> Submit
                            </button>
                        </form>
                    @endif
                    @if($inventoryRequest->status === 'submitted')
                        <form action="{{ route('admin.requests.update', $inventoryRequest->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="review">
                            <button type="button" class="action-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm" data-confirm="Mark as under review?">
                                <i class="fa-solid fa-eye mr-1"></i> Review
                            </button>
                        </form>
                    @endif
                    @if($inventoryRequest->status === 'under_review')
                        <form action="{{ route('admin.requests.update', $inventoryRequest->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="approve">
                            <button type="button" class="action-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm" data-confirm="Approve this request?">
                                <i class="fa-solid fa-check mr-1"></i> Approve
                            </button>
                        </form>
                        <form action="{{ route('admin.requests.update', $inventoryRequest->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="deny">
                            <button type="button" class="action-btn bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm" data-confirm="Deny this request?">
                                <i class="fa-solid fa-xmark mr-1"></i> Deny
                            </button>
                        </form>
                    @endif
                    @if($inventoryRequest->status === 'approved')
                        <form action="{{ route('admin.requests.update', $inventoryRequest->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="fulfill">
                            <button type="button" class="action-btn bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm" data-confirm="Mark as fulfilled?">
                                <i class="fa-solid fa-box-check mr-1"></i> Fulfill
                            </button>
                        </form>
                    @endif
                    @if(in_array($inventoryRequest->status, ['fulfilled', 'denied']))
                        <form action="{{ route('admin.requests.update', $inventoryRequest->id) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="close">
                            <button type="button" class="action-btn bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-sm" data-confirm="Close this request?">
                                <i class="fa-solid fa-lock mr-1"></i> Close
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.requests.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center gap-2">
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

            {{-- Request Header --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Branch</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $inventoryRequest->branch->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Department</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $inventoryRequest->department ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Priority</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($inventoryRequest->priority === 'urgent') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                            @elseif($inventoryRequest->priority === 'high') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300
                            @elseif($inventoryRequest->priority === 'normal') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                            {{ ucfirst($inventoryRequest->priority) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($inventoryRequest->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                            @elseif($inventoryRequest->status === 'submitted') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                            @elseif($inventoryRequest->status === 'under_review') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                            @elseif($inventoryRequest->status === 'denied') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                            @elseif($inventoryRequest->status === 'fulfilled') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300
                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                            {{ ucwords(str_replace('_', ' ', $inventoryRequest->status)) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Requester</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $inventoryRequest->requester->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $inventoryRequest->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Remarks</p>
                        <p class="text-gray-700 dark:text-gray-300">{{ $inventoryRequest->remarks ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Items Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Requested Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <th class="py-3 px-4 font-medium">Product</th>
                                <th class="py-3 px-4 font-medium text-center">Qty Requested</th>
                                <th class="py-3 px-4 font-medium text-center">Available</th>
                                <th class="py-3 px-4 font-medium text-center">Allow Sub.</th>
                                <th class="py-3 px-4 font-medium">Substitution Suggestion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($inventoryRequest->items ?? [] as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">{{ $item->product->generic_name ?? $item->product->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-center font-bold">{{ $item->quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="{{ ($item->available_quantity ?? 0) >= $item->quantity ? 'text-green-600' : 'text-red-600' }} font-bold">
                                            {{ $item->available_quantity ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        @if($item->allow_substitution)
                                            <span class="text-green-600"><i class="fa-solid fa-check"></i></span>
                                        @else
                                            <span class="text-gray-400"><i class="fa-solid fa-minus"></i></span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $item->substitution_suggestion ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {{-- Comments Section --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Comments</h3>
                    </div>
                    <div class="p-4 max-h-80 overflow-y-auto space-y-4">
                        @forelse($inventoryRequest->comments ?? [] as $comment)
                            <div class="flex gap-3">
                                <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-red-700 dark:text-red-300">{{ strtoupper(substr($comment->user->name ?? '?', 0, 1)) }}</span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $comment->user->name ?? 'Unknown' }}</span>
                                        <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $comment->body }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No comments yet.</p>
                        @endforelse
                    </div>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                        <form action="{{ route('admin.requests.comment', $inventoryRequest->id) }}" method="POST" class="flex gap-2">
                            @csrf
                            <input type="text" name="body" required placeholder="Add a comment..." class="flex-1 border dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500">
                            <button type="submit" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Attachments Section --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Attachments</h3>
                    </div>
                    <div class="p-4 max-h-60 overflow-y-auto space-y-2">
                        @forelse($inventoryRequest->attachments ?? [] as $attachment)
                            <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-paperclip text-gray-400"></i>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $attachment->filename }}</span>
                                </div>
                                <a href="{{ asset('storage/' . $attachment->path) }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No attachments.</p>
                        @endforelse
                    </div>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                        <form action="{{ route('admin.requests.attach', $inventoryRequest->id) }}" method="POST" enctype="multipart/form-data" class="flex gap-2">
                            @csrf
                            <input type="file" name="attachment" required class="flex-1 text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-red-50 file:text-red-700 hover:file:bg-red-100 dark:file:bg-red-900 dark:file:text-red-300">
                            <button type="submit" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fa-solid fa-upload"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Status History Timeline --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Status History</h3>
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                    @forelse($inventoryRequest->statusHistory ?? [] as $history)
                        <div class="relative pl-10 pb-6 last:pb-0">
                            <div class="absolute left-2.5 w-3 h-3 rounded-full
                                @if($history->status === 'approved') bg-green-500
                                @elseif($history->status === 'submitted') bg-yellow-500
                                @elseif($history->status === 'under_review') bg-blue-500
                                @elseif($history->status === 'denied') bg-red-500
                                @elseif($history->status === 'fulfilled') bg-purple-500
                                @else bg-gray-400 @endif"></div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($history->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                    @elseif($history->status === 'submitted') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                    @elseif($history->status === 'under_review') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                    @elseif($history->status === 'denied') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                                    @elseif($history->status === 'fulfilled') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                    {{ ucwords(str_replace('_', ' ', $history->status)) }}
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
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const form = this.closest('form');
            const msg = this.dataset.confirm || 'Are you sure?';
            Swal.fire({ title: 'Confirm', text: msg, icon: 'info', showCancelButton: true, confirmButtonText: 'Confirm', cancelButtonText: 'Cancel' })
                .then(r => { if (r.isConfirmed) { Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }); form.submit(); } });
        });
    });
    </script>
</x-app-layout>
