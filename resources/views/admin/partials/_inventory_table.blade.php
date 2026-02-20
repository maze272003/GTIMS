<table class="w-full pagination-links text-sm text-left">
    <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
        <tr>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">#</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Batch Number</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Product Details</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Quantity</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Status</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Expiry Date</th>

            @if (auth()->user()->hasPermission('inventory.edit') && auth()->user()->branch_id != 2)
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Actions</th>
            @endif
        </tr>
    </thead>
    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="inventory-table-body">
        @if ($inventories->isEmpty())
            <tr>
                <td colspan="{{ auth()->user()->hasPermission('inventory.edit') && auth()->user()->branch_id != 2 ? 7 : 6 }}"
                    class="p-3 text-center text-sm text-gray-500 dark:text-gray-400">
                    No inventory records available
                </td>
            </tr>
        @else
            @foreach ($inventories as $inventory)
                <tr data-stock-id="{{ $inventory->id }}"
                    data-batch="{{ $inventory->batch_number }}"
                    data-brand="{{ $inventory->product->brand_name }}"
                    data-product="{{ $inventory->product->generic_name }}"
                    data-form="{{ $inventory->product->form }}"
                    data-strength="{{ $inventory->product->strength }}"
                    data-quantity="{{ $inventory->quantity }}"
                    data-expiry="{{ $inventory->expiry_date?->format('Y-m-d') }}"
                    data-branch-id="{{ $inventory->branch_id }}">
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                        {{ $loop->iteration + ($inventories->currentPage() - 1) * $inventories->perPage() }}
                    </td>
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left font-semibold">
                        {{ $inventory->batch_number }}
                    </td>
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                        <div class="flex gap-4">
                            <div>
                                <p class="font-semibold text-gray-700 dark:text-gray-200">{{ $inventory->product->generic_name }}</p>
                                <p class="italic text-gray-500 dark:text-gray-400">{{ $inventory->product->brand_name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-700 dark:text-gray-200">{{ $inventory->product->strength }}</p>
                                <p class="italic text-gray-500 dark:text-gray-400">{{ $inventory->product->form }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="p-3 text-sm text-left font-semibold {{ $inventory->quantity < 100 ? 'text-yellow-700 dark:text-yellow-300' : ($inventory->quantity == 0 ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300') }}">
                        {{ $inventory->quantity }}
                    </td>
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300">
                        @if ($inventory->quantity < 100 && $inventory->quantity > 0)
                            <p class="p-2 border-l-2 border-yellow-500 dark:border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300 font-semibold text-center rounded">Low Stock</p>
                        @elseif ($inventory->quantity == 0)
                            <p class="p-2 border-l-2 border-red-500 dark:border-red-400 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-semibold text-center rounded">Out of Stock</p>
                        @else
                            <p class="p-2 border-l-2 border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-semibold text-center rounded">In Stock</p>
                        @endif
                    </td>
                    <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-center font-semibold">
                        {{ \Carbon\Carbon::parse($inventory->expiry_date)->format('M d, Y') }}
                    </td>

                    @if (auth()->user()->hasPermission('inventory.edit') && auth()->user()->branch_id != 2)
                        <td class="p-3 flex">
                            <div class="flex gap-2 w-full">
                                <button type="button" class="edit-stock-btn w-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 dark:hover:bg-blue-800 hover:text-white font-semibold text-sm text-center">
                                    <i class="fa-regular fa-pen-to-square mr-2"></i>
                                    Edit Stock
                                </button>

                                @if (auth()->user()->hasPermission('inventory.transfer'))
                                    <button type="button"
                                            class="transfer-stock-btn w-full bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-purple-600 dark:hover:bg-purple-800 hover:text-white font-semibold text-sm text-center"
                                            data-stock-id="{{ $inventory->id }}"
                                            data-batch="{{ $inventory->batch_number }}"
                                            data-product="{{ $inventory->product->generic_name }} {{ $inventory->product->brand_name }}"
                                            data-strength="{{ $inventory->product->strength }}"
                                            data-form="{{ $inventory->product->form }}"
                                            data-quantity="{{ $inventory->quantity }}"
                                            data-branch="{{ $inventory->branch_id == 1 ? 'RHU 1' : 'RHU 2' }}"
                                            data-branch-id="{{ $inventory->branch_id }}">
                                            <i class="fa-regular fa-share-from-square mr-2"></i>
                                        Transfer
                                    </button>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        @endif
    </tbody>
</table>

<!-- Modern & Professional Pagination with ... and First/Last Page -->
<div class="p-4 border-t bg-white dark:bg-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4 border-gray-200 dark:border-gray-700">
    <p class="text-sm text-gray-600 dark:text-gray-400 order-2 sm:order-1">
        Showing {{ $inventories->firstItem() ?? 0 }} to {{ $inventories->lastItem() ?? 0 }} of {{ $inventories->total() }} results
    </p>

    <div class="flex flex-wrap justify-center sm:justify-end gap-2 order-1 sm:order-2">
        {{-- Previous Button --}}
        @if ($inventories->onFirstPage())
            <span class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 dark:text-gray-500 cursor-not-allowed whitespace-nowrap">
                Previous
            </span>
        @else
            <a href="{{ $inventories->previousPageUrl() }}"
               class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 whitespace-nowrap pagination-link transition">
                Previous
            </a>
        @endif

        {{-- First Page --}}
        @if ($inventories->currentPage() > 3)
            <a href="{{ $inventories->url(1) }}" class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 whitespace-nowrap pagination-link">1</a>
            @if ($inventories->currentPage() > 4)
                <span class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">...</span>
            @endif
        @endif

        {{-- Page Numbers (Current ±2) --}}
        @foreach (range(max(1, $inventories->currentPage() - 2), min($inventories->lastPage(), $inventories->currentPage() + 2)) as $page)
            @if ($page === $inventories->currentPage())
                <span class="px-4 py-2 text-sm bg-red-700 dark:bg-red-600 text-white rounded-lg font-semibold shadow-sm whitespace-nowrap">
                    {{ $page }}
                </span>
            @else
                <a href="{{ $inventories->url($page) }}"
                   class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 whitespace-nowrap pagination-link transition">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        {{-- Last Page + Ellipses --}}
        @if ($inventories->currentPage() < $inventories->lastPage() - 2)
            @if ($inventories->currentPage() < $inventories->lastPage() - 3)
                <span class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">...</span>
            @endif
            <a href="{{ $inventories->url($inventories->lastPage()) }}"
               class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 whitespace-nowrap pagination-link">
                {{ $inventories->lastPage() }}
            </a>
        @endif

        {{-- Next Button --}}
        @if ($inventories->hasMorePages())
            <a href="{{ $inventories->nextPageUrl() }}"
               class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 whitespace-nowrap pagination-link transition">
                Next
            </a>
        @else
            <span class="px-4 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 dark:text-gray-500 cursor-not-allowed whitespace-nowrap">
                Next
            </span>
        @endif
    </div>
</div>

{{-- Transfer modal and JS are defined in the parent inventory.blade.php --}}
{{-- Do not duplicate them here to avoid multiple elements with the same ID --}}