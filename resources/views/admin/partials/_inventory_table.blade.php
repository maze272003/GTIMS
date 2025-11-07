<table class="w-full pagination-links text-sm text-left">
    <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
        <tr>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">#</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Batch Number</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Product Details</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Quantity</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Status</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Expiry Date</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Actions</th>
        </tr>
    </thead>
    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
        @if ($inventories->isEmpty())
            <tr>
                <td colspan="7" class="p-3 text-center text-sm text-gray-500 dark:text-gray-400">No inventory records available</td>
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
                data-expiry="{{ $inventory->expiry_date }}">
                <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left">
                    {{ $loop->iteration + ($inventories->currentPage() - 1) * $inventories->perPage() }}
                </td>
                <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-left font-semibold">{{ $inventory->batch_number }}</td>
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
                        <p class="p-2 border-l-2 border-yellow-500 dark:border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300 font-semibold text-center">Low Stock</p>
                    @elseif ($inventory->quantity == 0)
                        <p class="p-2 border-l-2 border-red-500 dark:border-red-400 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-semibold text-center">Out of Stock</p>
                    @else
                        <p class="p-2 border-l-2 border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-semibold text-center">In Stock</p>
                    @endif 
                </td>
                <td class="p-3 text-sm text-gray-700 dark:text-gray-300 text-center font-semibold">
                    {{ \Carbon\Carbon::parse($inventory->expiry_date)->format('M d, Y') }}
                </td>
                <td class="p-3">
                    <button class="edit-stock-btn bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 dark:hover:bg-blue-800 hover:text-white font-semibold text-sm">
                        <i class="fa-regular fa-pen-to-square mr-1"></i>
                        Edit Stock
                    </button>
                </td>
            </tr> 
            @endforeach
        @endif
    </tbody>
</table>

<div class="p-4 border-t bg-white dark:bg-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4 border-gray-200 dark:border-gray-700">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Showing {{ $inventories->firstItem() ?? 0 }} to {{ $inventories->lastItem() ?? 0 }} of {{ $inventories->total() }} results
    </p>
    {{-- Nagdagdag ako ng class="pagination-links" dito para sa JavaScript --}}
    <div class="flex space-x-2 pagination-links">
        @if ($inventories->onFirstPage())
            <span class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 dark:text-gray-500 cursor-not-allowed">Previous</span>
        @else
            <a href="{{ $inventories->previousPageUrl() }}" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Previous</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($inventories->links()->elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 dark:text-gray-500 cursor-default">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $inventories->currentPage())
                        <span class="px-3 py-2 text-sm bg-red-700 dark:bg-red-600 text-white rounded-lg">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($inventories->hasMorePages())
            <a href="{{ $inventories->nextPageUrl() }}" class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Next</a>
        @else
            <span class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 dark:text-gray-500 cursor-not-allowed">Next</span>
        @endif
    </div>
</div>


{{-- dito kona lagay script ng nasa inventory.blade.php para hindi kalat --}}

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Error Handling Script (yung dati mong script) ---
    @if ($errors->addproduct->any())
        document.getElementById('addnewproductmodal').classList.remove('hidden');
    @elseif ($errors->addstock->any() && old('product_id', null, 'addstock'))
        document.getElementById('viewallproductsmodal').classList.remove('hidden');
        document.getElementById('addstockmodal').classList.remove('hidden');
    @elseif ($errors->updateproduct->any() && old('product_id', null, 'updateproduct'))
        document.getElementById('viewallproductsmodal').classList.remove('hidden');
        document.getElementById('editproductmodal').classList.remove('hidden');
    @elseif ($errors->editstock->any() && old('inventory_id', null, 'editstock'))
        document.getElementById('editstockmodal').classList.remove('hidden');
    @endif

    // --- AJAX Script para sa Search at Pagination ---

    const tableContainer = document.getElementById('inventory-data-container');
    
    // --- Debounce Function (para hindi mag-request sa bawat type) ---
    let debounceTimer;
    function debounce(func, delay) {
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func.apply(context, args), delay);
        }
    }

    // --- Pangunahing Function para mag-Fetch ng Data ---
    function fetchInventory(url) {
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            tableContainer.innerHTML = html;
            // I-update ang URL sa browser
            window.history.pushState({path: url}, '', url);
            
            // Dito mo i-re-attach ang listeners para sa "Edit Stock" buttons
            // Halimbawa: initEditStockButtonListeners();
        })
        .catch(error => console.error('Error fetching data:', error));
    }

    // --- 1. AJAX Pagination Handler ---
    // Gagamit tayo ng 'event delegation' para gumana pa rin kahit magpalit ang HTML
    tableContainer.addEventListener('click', function (event) {
        // Tignan kung ang pinindot ay isang <a> tag sa loob ng .pagination-links
        if (event.target.tagName === 'A' && event.target.closest('.pagination-links')) {
            event.preventDefault(); // Pigilan ang full page reload
            let url = event.target.href;
            fetchInventory(url);
        }
    });

    // --- 2. AJAX Search Handler ---
    const searchInput = document.getElementById('inventory-search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(function (event) {
            const searchValue = event.target.value;
            
            // Kunin ang base URL ng page (walang query string)
            const baseUrl = window.location.origin + window.location.pathname;
            
            // Gumawa ng bagong URL na may search query.
            // Ito ay awtomatikong magre-reset sa page 1, na tama para sa bagong search.
            const url = new URL(baseUrl);
            url.searchParams.set('search', searchValue);

            fetchInventory(url.href);
        }, 300)); // 300ms delay bago mag-search
    }

    // --- 3. Browser Back/Forward Button Handler ---
    window.addEventListener('popstate', function () {
        // Kunin ang data para sa URL na pinuntahan (mula sa history)
        fetchInventory(location.href); 
    });
});
</script>