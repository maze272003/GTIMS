<div class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden" id="addnewproductmodal">
    <div class="modal bg-white rounded-lg w-full max-w-lg p-5">
      <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
        <p class="text-xl font-medium text-gray-600">Add New Product</p>
        <button id="closeaddnewproductmodal" class="p-2 rounded-full hover:bg-gray-100">
          <i class="fa-regular fa-xmark"></i>
        </button>
      </div>

      <form action="{{ route('admin.inventory.addproduct') }}" class="mt-5" method="POST" id="add-product-form">
        @csrf
        <div class="flex gap-2">
          <div class="w-1/2">
            <label for="generic_name" class="text-sm font-semibold text-gray-600">Product Name (Generic):</label>
            <input type="text" name="generic_name" id="generic_name" placeholder="Enter Generic Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('generic_name') }}">
            @error('generic_name' , 'addproduct')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
          <div class="w-1/2">
            <label for="brand" class="text-sm font-semibold text-gray-600">Brand Name:</label>
            <input type="text" name="brand_name" id="brand_name" placeholder="Enter Brand Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('brand_name') }}">
            @error('brand_name', 'addproduct')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
        </div>

        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="form" class="text-sm font-semibold text-gray-600">Form:</label>
            <input type="text" name="form" id="form" placeholder="Form" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('form') }}">
            @error('form', 'addproduct')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
          <div class="w-1/2">
            <label for="strength" class="text-sm font-semibold text-gray-600">Strength:</label>
            <input type="text" name="strength" id="strength" placeholder="500mg" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('strength') }}">
            @error('strength', 'addproduct')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
        </div>

        <button id="add-product-btn" type="button" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
          <i class="fa-regular fa-check"></i>
          <span>Submit</span>
        </button>
      </form>
    </div>
  </div>