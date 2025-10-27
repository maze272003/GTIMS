<table class="min-w-full table-auto border-collapse">
    <thead class="bg-gray-200 text-gray-700 sticky top-0">
        <tr>
            <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">#</th>
            <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200 text-center">Action</th>
            <th class="p-4 text-gray-600 uppercase text-xs font-bold text-center tracking-wider border-b border-gray-200">User</th>
            <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">Details</th>
            <th class="p-4 text-gray-600 uppercase text-xs font-bold text-left tracking-wider border-b border-gray-200">Date</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @forelse($historyLogs as $index => $log)
            <tr class="text-gray-700 hover:bg-gray-50 transition duration-100">
                <td class="p-4 text-sm font-medium">
                    {{ ($historyLogs->currentPage() - 1) * $historyLogs->perPage() + $loop->iteration }}
                </td>
                <td class="p-4 text-sm text-center">
                    @php
                        $badgeColor = match(strtoupper($log->action)) {
                            'PRODUCT REGISTERED' => 'bg-green-100 text-green-800',
                            'PRODUCT UPDATED' => 'bg-blue-100 text-blue-800',
                            'PRODUCT ARCHIVED' => 'bg-red-100 text-red-800',
                            'PRODUCT UNARCHIVED' => 'bg-yellow-100 text-yellow-800',
                            'STOCK ADDED' => 'bg-green-100 text-green-800',
                            'STOCK UPDATED' => 'bg-blue-100 text-blue-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    @endphp
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badgeColor }}">
                        {{ strtoupper($log->action) }}
                    </span>
                </td>
                <td class="p-4 text-sm text-center font-medium">
                    {{ $log->user_name ?? 'System' }}
                </td>
                <td class="p-4 text-sm text-gray-500">
                    @php
                        $maxLength = 100;
                        $isLong = strlen($log->description) > $maxLength;
                        $shortText = $isLong ? substr($log->description, 0, $maxLength) . '...' : $log->description;
                    @endphp
                    <span class="log-description">{{ $shortText }}</span>
                    @if($isLong)
                        <button 
                            class="text-blue-400 no-underline font-bold hover:underline text-sm ml-1 view-more-btn" 
                            data-full="{{ e($log->description) }}"
                        >
                            View More
                        </button>
                    @endif
                </td>
                <td class="p-4 text-sm">
                    {{ $log->created_at->format('F j, Y h:i A') }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="p-4 text-center text-gray-500">
                    No history logs found for the selected filters.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="p-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
    <p class="text-sm text-gray-600 mr-3">
        Showing {{ $historyLogs->firstItem() ?? 0 }} to {{ $historyLogs->lastItem() ?? 0 }} of {{ $historyLogs->total() }} results
    </p>

    <div class="flex space-x-2 pagination">
        {{-- 
            Important: ensures all filters persist through pagination.
            Laravel automatically merges query parameters from ->withQueryString()
        --}}
        {{ $historyLogs->appends(request()->query())->links() }}
    </div>
</div>