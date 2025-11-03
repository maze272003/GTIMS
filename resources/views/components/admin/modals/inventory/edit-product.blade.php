<div class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden" id="editproductmodal">
      <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
              <p class="text-xl font-medium text-gray-600 dark:text-gray-300">Edit Product</p>
              <button id="closeeditproductmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                  <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400"></i>
              </button>
          </div>
          <form action="{{ route('admin.inventory.updateproduct') }}" method="POST" class="mt-5" id="edit-product-form">
              @csrf
              @method('PUT')
              <input type="hidden" name="product_id" id="edit-product-id" value="{{ old('product_id') }}">
              <div class="flex gap-2">
                  <div class="w-1/2">
                      <label for="edit-product" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Product Name (Generic):</label>
                      <input type="text" name="generic_name" id="edit-product" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ old('generic_name') }}" placeholder="Enter Generic Name">
                      @error('generic_name', 'updateproduct')
                          <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
                  <div class="w-1/2">
                      <label for="edit-brand" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Brand Name:</label>
                      <input type="text" name="brand_name" id="edit-brand" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ old('brand_name') }}" placeholder="Enter Brand Name">
                      @error('brand_name', 'updateproduct')
                          <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
              </div>
              <div class="flex gap-2 mt-2">
                  <div class="w-1/2">
                      <label for="edit-form" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Form:</label>
                      <input type="text" name="form" id="edit-form" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ old('form') }}" placeholder="Vials">
                      @error('form', 'updateproduct')
                          <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
                  <div class="w-1/2">
                      <label for="edit-strength" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Strength:</label>
                      <input type="text" name="strength" id="edit-strength" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ old('strength') }}" placeholder="500mg">
                      @error('strength', 'updateproduct')
                          <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
              </div>
              <button type="submit" class="bg-blue-500 dark:bg-blue-600 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                  <i class="fa-regular fa-check"></i>
                  <span>Update</span>
              </button>
          </form>
      </div>
  </div>