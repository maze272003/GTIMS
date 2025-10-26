<div id="addstockmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="modal bg-white rounded-lg w-full max-w-lg p-5">
      <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
        <p id="add-stock-title" class="text-xl font-medium text-gray-600">Add Stock</p>
        <button id="closeaddstockmodal" class="p-2 rounded-full hover:bg-gray-100">
          <i class="fa-regular fa-xmark"></i>
        </button>
      </div>

      <form action="{{ route('admin.inventory.addstock') }}" method="POST">
        @csrf
        @method('POST')
        <input type="hidden" id="selected-product-id" name="product_id" value="{{ old('product_id') }}">

        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="batchnumber" class="text-sm font-semibold text-gray-600">Batch Number:</label>
            <input type="text" name="batchnumber" id="batchnumber" placeholder="Enter Batch Number" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('batchnumber') }}">
            @error('batchnumber', 'addstock')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
          <div class="w-1/2">
            <label for="quantity" class="text-sm font-semibold text-gray-600">Quantity:</label>
            <input type="number" name="quantity" id="quantity" placeholder="Enter Quantity" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('quantity') }}">
            @error('quantity', 'addstock')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
        </div>

        <div class="w-full mt-2">
          <label for="expiry" class="text-sm font-semibold text-gray-600">Expiry Date:</label>
          <input type="date" name="expiry" id="expiry" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('expiry') }}">
          @error('expiry', 'addstock')
            <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
          @enderror
        </div>

        <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
          <i class="fa-regular fa-check"></i>
          <span>Submit</span>
        </button>
      </form>
    </div>
  </div>