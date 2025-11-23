<table class="w-full pagination-links text-sm text-left">
    <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
        <tr>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">#</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Batch Number</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Product Details</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Quantity</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Status</th>
            <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Expiry Date</th>

            @if (auth()->user()->user_level_id != 4 && auth()->user()->branch_id != 2)
                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm text-left tracking-wide">Actions</th>
            @endif
        </tr>
    </thead>
    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="inventory-table-body">
        @if ($inventories->isEmpty())
            <tr>
                <td colspan="{{ auth()->user()->user_level_id != 4 && auth()->user()->branch_id != 2 ? 7 : 6 }}"
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

                    @if (auth()->user()->user_level_id != 4 && auth()->user()->branch_id != 2)
                        <td class="p-3 flex">
                            <div class="flex gap-2 w-full">
                                <button type="button" class="edit-stock-btn w-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 dark:hover:bg-blue-800 hover:text-white font-semibold text-sm text-center">
                                    <i class="fa-regular fa-pen-to-square mr-2"></i>
                                    Edit Stock
                                </button>

                                @if (auth()->user()->user_level_id <= 2)
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

<!-- Transfer Stock Modal-->
<div id="transferstockmodal" class="hidden fixed bg-black/60 w-full h-screen top-0 left-0 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
    <div class="modal bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md transform transition-all">
        <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Transfer Stock</h3>
            <button type="button" class="close-modal text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fa-regular fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="{{ route('admin.inventory.transferstock') }}" method="POST" id="transfer-form">
            @csrf
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Product</label>
                    <p id="transfer-product-name" class="text-lg font-medium text-red-600 dark:text-white mt-1"></p>
                    <input type="hidden" name="inventory_id" id="transfer-inventory-id">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold">Batch No.</label>
                        <p id="transfer-batch" class="font-bold text-purple-700 dark:text-purple-400"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold">Current Branch</label>
                        <p id="transfer-current-branch" class="font-medium"></p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Available Quantity</label>
                    <p id="transfer-available-qty" class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1"></p>
                </div>

                <div>
                    <label for="transfer_qty" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Quantity to Transfer <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="quantity" id="transfer_qty" min="1" required
                           class="w-full mt-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700">
                    <p class="text-xs text-red-500 mt-1 hidden" id="transfer-error">Not enough stock!</p>
                </div>

                <div>
                    <label for="destination_branch" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Transfer To Branch <span class="text-red-500">*</span>
                    </label>
                    <select name="destination_branch" id="destination_branch" required
                            class="w-full mt-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700">
                        <option value="1">RHU 1</option>
                        <option value="2">RHU 2</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 p-6 border-t dark:border-gray-700">
                <button type="button" class="close-modal px-6 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-medium">
                    Cancel
                </button>
                <button type="button" id="confirm-transfer-btn"
                        class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium shadow-md hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Transfer Stock
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableContainer = document.querySelector('#inventory-data-container') || document.body; // fallback
    const modal = document.getElementById('transferstockmodal');

    // Re-attach Transfer Button Listeners (dapat lagi ginagawa pagkatapos mag-load ng bagong content)
    function attachTransferButtonListeners() {
        document.querySelectorAll('.transfer-stock-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const data = this.dataset;

                document.getElementById('transfer-inventory-id').value = data.stockId;
                document.getElementById('transfer-product-name').textContent = data.product + ' ' + data.strength + ' ' + data.form;
                document.getElementById('transfer-batch').textContent = data.batch;
                document.getElementById('transfer-current-branch').textContent = data.branch;
                document.getElementById('transfer-available-qty').textContent = data.quantity;

                // Auto-select opposite branch
                document.getElementById('destination_branch').value = data.branchId == 1 ? 2 : 1;

                modal.classList.remove('hidden');
            });
        });
    }

    // Close modal
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', () => modal.classList.add('hidden'));
    });

    // Initial attach
    attachTransferButtonListeners();

    // Debounce
    function debounce(func, delay) {
        let timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(() => func.apply(this, arguments), delay);
        };
    }

    // Main AJAX Fetch
    function fetchInventory(url) {
        fetch(url + (url.includes('?') ? '&' : '?') + 'ajax=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(html => {
            // Palitan lang ang table + pagination
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.querySelector('table');
            const newPagination = doc.querySelector('.p-4.border-t');

            if (newTable && newPagination) {
                document.querySelector('table').outerHTML = newTable.outerHTML;
                document.querySelector('.p-4.border-t').outerHTML = newPagination.outerHTML;
            }

            // Re-attach ang listeners pagkatapos palitan
            attachTransferButtonListeners();

            // Update URL without reload
            history.pushState({}, '', url);
        })
        .catch(err => console.error(err));
    }

    // Pagination Click (Delegation)
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a.pagination-link');
        if (link && link.href) {
            e.preventDefault();
            fetchInventory(link.href);
        }
    });

    // Search Input
    const searchInput = document.getElementById('inventory-search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(function () {
            const val = this.value.trim();
            const base = window.location.pathname;
            const url = val ? `${base}?search=${encodeURIComponent(val)}` : base;
            fetchInventory(url);
        }, 400));
    }

    // Back/Forward Button
    window.addEventListener('popstate', () => fetchInventory(location.href));
});

document.getElementById('confirm-transfer-btn').addEventListener('click', function() {
    const form = document.getElementById('transfer-form');
    const inputs = form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]');
    let allFilled = true;

    inputs.forEach(input => {
      if (input.value.trim() === '') {
        allFilled = false;
      }
    });

    if (!allFilled) {
      Swal.fire({
        title: 'Incomplete Form',
        text: 'Please fill in all required fields before submitting.',
        icon: 'warning',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        customClass: {
          container: 'swal-container',
          popup: 'swal-popup',
          title: 'swal-title',
          htmlContainer: 'swal-content',
          confirmButton: 'swal-confirm-button',
          icon: 'swal-icon'
        }
      });
      return;
    }

    Swal.fire({
      title: 'Are you sure?',
      text: "This action can't be undone. Please confirm if you want to proceed.",
      icon: 'info',
      showCancelButton: true,
      cancelButtonText: 'Cancel',
      confirmButtonText: 'Confirm',
      allowOutsideClick: false,
      customClass: {
        container: 'swal-container',
        popup: 'swal-popup',
        title: 'swal-title',
        htmlContainer: 'swal-content',
        confirmButton: 'swal-confirm-button',
        cancelButton: 'swal-cancel-button',
        icon: 'swal-icon'
      }
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'Processing...',
          text: "Please wait, your request is being processed.",
          allowOutsideClick: false,
          customClass: {
            container: 'swal-container',
            popup: 'swal-popup',
            title: 'swal-title',
            htmlContainer: 'swal-content',
            cancelButton: 'swal-cancel-button',
            icon: 'swal-icon'
          },
          didOpen: () => {
            Swal.showLoading();
          }
        });
        form.submit();
      }
    });
  });
</script>