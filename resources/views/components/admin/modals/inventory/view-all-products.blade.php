<div class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden" id="viewallproductsmodal">
      <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto p-6">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4 sticky top-0 bg-white dark:bg-gray-800 z-10">
              <p class="text-xl font-medium text-gray-700 dark:text-gray-300">All Products</p>
              <button id="closeviewallproductsmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                  <i class="fa-regular fa-xmark text-xl text-gray-600 dark:text-gray-400"></i>
              </button>
          </div>

          <div class="overflow-x-auto h-fit max-h-[70vh]">
              <table class="w-full text-sm text-left">
                  <thead class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase text-xs sticky top-0">
                      <tr>
                          <th class="p-3">#</th>
                          <th class="p-3">Product Details</th>
                          @if (auth()->user()->user_level_id != 4)
                          <th class="p-3 text-center">Actions</th>
                        @endif
                      </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                      @if ($products->isEmpty())
                          <tr>
                              <td colspan="3" class="p-3 text-center text-gray-500 dark:text-gray-400">No Products Available</td>
                          </tr>
                      @else
                          @foreach ($products as $product)
                              <tr class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                  data-product-id="{{ $product->id }}"
                                  data-brand="{{ $product->brand_name }}"
                                  data-product="{{ $product->generic_name }}"
                                  data-form="{{ $product->form }}"
                                  data-strength="{{ $product->strength }}">
                                  <td class="p-3 text-gray-700 dark:text-gray-300">{{ $loop->iteration }}</td>
                                  <td class="p-3 text-gray-700 dark:text-gray-300">
                                      <div class="flex gap-4">
                                          <div>
                                              <p class="font-semibold text-gray-700 dark:text-gray-200">{{ $product->generic_name }}</p>
                                              <p class="italic text-gray-500 dark:text-gray-400">{{ $product->brand_name }}</p>
                                          </div>
                                          <div>
                                              <p class="font-semibold text-gray-700 dark:text-gray-200">{{ $product->strength }}</p>
                                              <p class="italic text-gray-500 dark:text-gray-400">{{ $product->form }}</p>
                                          </div>
                                      </div>
                                  </td>
                                  @if (auth()->user()->user_level_id != 4)
                                  <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                                      <button class="add-stock-btn bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 p-2 rounded-lg hover:-translate-y-1 duration-300 hover:bg-blue-600 dark:hover:bg-blue-800 hover:text-white transition-all mr-2">
                                          <i class="fa-regular fa-plus mr-1"></i>Add Stock
                                      </button>
                                      <button class="edit-product-btn bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 p-2 rounded-lg hover:-translate-y-1 duration-300 hover:bg-green-600 dark:hover:bg-green-800 hover:text-white transition-all">
                                          <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                                      </button>
                                      <form action="{{ route('admin.inventory.archiveproduct') }}" method="POST" class="inline" id="archiveproductform">
                                          @csrf
                                          @method('PUT')
                                          <input type="hidden" name="product_id" value="{{ $product->id }}">
                                          <button type="button" id="archiveproductbtn" class="delete-product-btn bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 p-2 rounded-lg hover:-translate-y-1 duration-300 hover:bg-red-600 dark:hover:bg-red-800 hover:text-white transition-all">
                                              <i class="fa-regular fa-trash mr-1"></i>Archive
                                          </button>
                                      </form>
                                  </td>
                                  @endif
                              </tr>
                          @endforeach
                      @endif
                  </tbody>
              </table>
          </div>

          {{-- <div class="p-2 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
              <p class="text-sm text-gray-600">
                  Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} results
              </p>
              <div class="flex space-x-2">
                  @if ($products->onFirstPage())
                      <span class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">Previous</span>
                  @else
                      <a href="{{ $products->previousPageUrl() }}" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">Previous</a>
                  @endif

                  @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                      @if ($page == $products->currentPage())
                          <span class="px-3 py-2 text-sm bg-red-700 text-white rounded-lg">{{ $page }}</span>
                      @else
                          <a href="{{ $url }}" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">{{ $page }}</a>
                      @endif
                  @endforeach

                  @if ($products->hasMorePages())
                      <a href="{{ $products->nextPageUrl() }}" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">Next</a>
                  @else
                      <span class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">Next</span>
                  @endif
              </div>
          </div> --}}
      </div>
  </div>

  <script>
    const archiveProductBtns = document.querySelectorAll('.delete-product-btn');
    archiveProductBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const form = btn.closest('form');
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
    });
  </script>
