<div id="editstockmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="modal bg-white rounded-lg w-full max-w-lg p-5">
      <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
        <p id="edit-stock-title" class="text-xl font-medium text-gray-600">Edit Stock</p>
        <button id="closeeditstockmodal" class="p-2 rounded-full hover:bg-gray-100">
          <i class="fa-regular fa-xmark"></i>
        </button>
      </div>
      <form action="{{ route('admin.inventory.editstock') }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" id="edit-stock-id" name="inventory_id" value="{{ old('inventory_id') }}">
        <div class="mb-4">
          <p class="text-sm font-medium text-gray-700">
            <span id="edit-stock-product"></span>
          </p>
        </div>
        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="edit-batchnumber" class="text-sm font-semibold text-gray-600">Batch Number:</label>
            <input type="text" name="batchnumber" id="edit-batchnumber" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('batchnumber') }}" placeholder="Enter Batch Number">
            @error('batchnumber', 'editstock')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
          <div class="w-1/2">
            <label for="edit-quantity" class="text-sm font-semibold text-gray-600">Quantity:</label>
            <input type="number" name="quantity" id="edit-quantity" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('quantity') }}" placeholder="Enter Quantity">
            @error('quantity', 'editstock')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
        </div>
        <div class="w-full mt-2">
          <label for="edit-expiry" class="text-sm font-semibold text-gray-600">Expiry Date:</label>
          <input type="date" name="expiry" id="edit-expiry" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('expiry') }}" placeholder="Enter Expiry Date">
          @error('expiry', 'editstock')
            <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
          @enderror
        </div>
        <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
          <i class="fa-regular fa-check"></i>
          <span>Update</span>
        </button>
      </form>
    </div>
  </div>