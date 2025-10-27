<div id="history-table">
    <table class="w-full pagination-links text-sm text-left">
        <thead class="sticky top-0 bg-gray-200">
            <tr>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">#</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-center tracking-wide">Action</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-center tracking-wide">User</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">Details</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">
                    <button id="sortDateBtn"
                            class="flex items-center gap-1 select-none hover:text-blue-600 transition-colors duration-150">
                        Date
                        @php $currentSort = request('sort', 'desc'); @endphp
                        @if ($currentSort === 'asc')
                            <i class="fas fa-arrow-up text-xs"></i>
                        @else
                            <i class="fas fa-arrow-down text-xs"></i>
                        @endif
                    </button>
                </th>
            </tr>
        </thead>

        <tbody id="history-tbody" class="bg-white divide-y divide-gray-200">
            @forelse($historyLogs as $log)
                <tr class="hover:bg-gray-50 transition-colors duration-150 text-gray-700">
                    <td class="p-3 text-sm font-medium text-gray-900">
                        {{ $loop->iteration + ($historyLogs->currentPage() - 1) * $historyLogs->perPage() }}
                    </td>

                    <td class="p-3 text-center">
                        @php
                            $badgeStyles = match(strtoupper($log->action)) {
                                'PRODUCT REGISTERED', 'STOCK ADDED' => 'bg-emerald-100 text-emerald-700',
                                'PRODUCT UPDATED', 'STOCK UPDATED' => 'bg-blue-100 text-blue-700',
                                'PRODUCT ARCHIVED' => 'bg-red-100 text-red-700',
                                'PRODUCT UNARCHIVED' => 'bg-amber-100 text-amber-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full {{ $badgeStyles }}">
                            {{ strtoupper($log->action) }}
                        </span>
                    </td>

                    <td class="p-3 text-sm text-center font-medium text-gray-800">
                        {{ $log->user_name ?? 'System' }}
                    </td>

                    <td class="p-3 text-sm text-gray-600">
                        @php
                            $maxLength = 60;
                            $isLong    = strlen($log->description) > $maxLength;
                            $shortText = $isLong ? substr($log->description, 0, $maxLength) . '...' : $log->description;
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="log-description">{{ $shortText }}</span>

                            @if($isLong)
                                <button class="view-more-btn text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline transition-colors duration-150 flex items-center gap-1"
                                        data-full="{{ e($log->description) }}"
                                        title="View full description">
                                    <i class="fas fa-expand-alt text-xs"></i>
                                    View More
                                </button>
                            @endif
                        </div>
                    </td>

                    <td class="p-3 text-sm text-gray-700 whitespace-nowrap">
                        {{ $log->created_at->format('M j, Y g:i A') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-3 text-center text-sm text-gray-500">
                        <div class="flex flex-col items-center py-6">
                            <i class="fas fa-history text-3xl text-gray-300 mb-2"></i>
                            <p class="text-sm font-medium">No history logs found</p>
                            <p class="text-xs text-gray-400 mt-1">Try adjusting your filters.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="p-4 border-t bg-white flex flex-col sm:flex-row justify-between items-center gap-4">
        <p class="text-sm text-gray-600">
            Showing {{ $historyLogs->firstItem() ?? 0 }} to {{ $historyLogs->lastItem() ?? 0 }} of {{ $historyLogs->total() }} results
        </p>

        <div class="flex space-x-2 pagination-links">
            @if ($historyLogs->onFirstPage())
                <span class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">Previous</span>
            @else
                <a href="{{ $historyLogs->previousPageUrl() }}"
                   class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">Previous</a>
            @endif

            @foreach ($historyLogs->links()->elements as $element)
                @if (is_string($element))
                    <span class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-400 cursor-default">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $historyLogs->currentPage())
                            <span class="px-3 py-2 text-sm bg-red-700 text-white rounded-lg">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($historyLogs->hasMorePages())
                <a href="{{ $historyLogs->nextPageUrl() }}"
                   class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">Next</a>
            @else
                <span class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">Next</span>
            @endif
        </div>
    </div>
</div>

<div id="viewMoreModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Full Description</h3>
            <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p id="modalDescription" class="text-sm text-gray-700 whitespace-pre-line"></p>
    </div>
</div>