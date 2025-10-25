@php
  use Carbon\Carbon;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>General Tinio - Inventory System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.1.0/css/all.css">
  <link rel="icon" type="image/png" href="{{ asset('images/gtlogo.png') }}">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  {{-- sweetalert --}}
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">

  <x-admin.sidebar/>

  <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
    <x-admin.header/>
    <main class="pt-20 p-4 lg:p-8 min-h-screen">
      <div class="mb-6 pt-16">
        <p class="text-sm text-gray-600">Home / <span class="text-red-700 font-medium">Inventory</span></p>
      </div>

      {{-- card --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">In Stock</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{ $inventories->where('quantity', '>=', 100)->count() }}
              </p>
              <p class="text-xs text-green-600 mt-1 flex items-center">
                <i class="fa-regular fa-arrow-trend-up mr-1"></i> Currently in stock
              </p>
            </div>
            <div class="bg-green-100 p-4 rounded-full">
              <i class="fa-regular fa-boxes-stacked text-2xl text-green-600"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Low Stock</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ $inventories->where('quantity', '<', 100)->count() }}</p>
              <p class="text-xs text-orange-600 mt-1 flex items-center">
                <i class="fa-regular fa-triangle-exclamation mr-1"></i> Requires attention
              </p>
            </div>
            <div class="bg-orange-100 p-4 rounded-full">
              <i class="fa-regular fa-exclamation text-2xl text-orange-600"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Expired Stock</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{-- where expiry date is less from today--}}
                {{ $inventories->where('expiry_date', '<', Carbon::now())->count() }}
              </p>
              <p class="text-xs text-red-600 mt-1 flex items-center">
                <i class="fa-regular fa-ban mr-1"></i>Must be removed
              </p>
            </div>
            <div class="bg-red-100 p-4 rounded-full">
              <i class="fa-regular fa-xl fa-calendar-xmark text-red-600"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Nearly Expired</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{ $inventories->where('expiry_date', '>', Carbon::now())->where('expiry_date', '<', Carbon::now()->addDays(30))->count() }}
              </p>
              <p class="text-xs text-yellow-600 mt-1 flex items-center">
                <i class="fa-regular fa-hourglass-half mr-1"></i>Expires in 30 days
              </p>
            </div>
            <div class="bg-yellow-100 p-4 rounded-full">
              <i class="fa-regular fa-clock text-2xl text-yellow-600"></i>
            </div>
          </div>
        </div>
      </div>
      {{-- end card --}}

      {{-- buttons --}}
      <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
        <button id="addnewproductbtn" class="bg-white inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
          <i class="fa-regular fa-plus mr-2"></i> Register New Product
        </button>
        <button id="viewallproductsbtn" class="bg-white inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
          <i class="fa-regular fa-eye mr-2"></i> View All Products
        </button>
      </div>
      {{-- end buttons --}}

      {{-- table --}}
      <div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
          <div class="relative w-1/2">
            <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" placeholder="Search products..." class="w-full pl-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
          </div>
          
          {{-- export button --}}
          <button class="bg-white inline-flex items-center justify-center p-2 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
            <i class="fa-regular fa-file-export text-lg text-green-600"></i>
            <span class="ml-2">Export into CSV</span>
          </button>
        </div>

        <div class="overflow-x-auto p-5">
          <table class="w-full">
            <thead class="sticky top-0 bg-gray-200">
              <tr>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">#</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">Batch Number</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">Product Details</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">Quantity</th>
                <th class="p-3 text-gray-700 uppercase text-sm tracking-wide">Status</th>
                <th class="p-3 text-gray-700 uppercase text-sm tracking-wide">Expiry Date</th>
                <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              @foreach ($inventories as $inventory)
              <tr data-stock-id="{{ $inventory->id }}"
                  data-batch="{{ $inventory->batch_number }}"
                  data-brand="{{ $inventory->product->brand_name }}"
                  data-product="{{ $inventory->product->generic_name }}"
                  data-form="{{ $inventory->product->form }}"
                  data-strength="{{ $inventory->product->strength }}"
                  data-quantity="{{ $inventory->quantity }}"
                  data-expiry="{{ $inventory->expiry_date }}">
                <td class="p-3 text-sm text-gray-700 text-left">{{ $loop->iteration }}</td>
                <td class="p-3 text-sm text-gray-700 text-left font-semibold">{{ $inventory->batch_number }}</td>
                <td class="p-3 text-sm text-gray-700 text-left">
                  <div class="flex gap-4">
                    <div>
                      <p class="font-semibold text-gray-700">{{ $inventory->product->brand_name }}</p>
                      <p class="italic text-gray-500">{{ $inventory->product->generic_name }}</p>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-700">{{ $inventory->product->strength }}</p>
                      <p class="italic text-gray-500">{{ $inventory->product->form }}</p>
                    </div>
                  </div>
                </td>
                <td class="p-3 text-sm text-left font-semibold {{ $inventory->quantity < 100 ? 'text-yellow-700' : ($inventory->quantity == 0 ? 'text-red-700' : 'text-green-700') }}">{{ $inventory->quantity }}</td>   
                <td class="p-3 text-sm text-gray-700">
                  @if ($inventory->quantity < 100)
                    <p class="p-2 border-l-2 border-yellow-500 bg-yellow-50 text-yellow-700 font-semibold text-center">Low Stock</p>
                  @elseif ($inventory->quantity == 0)
                    <p class="p-2 border-l-2 border-red-500 bg-red-50 text-red-700 font-semibold text-center">Out of Stock</p>
                  @elseif ($inventory->quantity > 100)
                    <p class="p-2 border-l-2 border-green-500 bg-green-50 text-green-700 font-semibold text-center">In Stock</p>
                  @endif 
                </td>
                <td class="p-3 text-sm text-gray-700 text-center font-semibold">{{ Carbon::parse($inventory->expiry_date)->format('M d, Y') }}</td>
                <td class="p-3">
                  <button class="edit-stock-btn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                    <i class="fa-regular fa-pen-to-square mr-1"></i>Edit Stock
                  </button>
                </td>
              </tr> 
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      {{-- end table--}}
    </main>
  </div>

  {{-- modals --}}

  {{-- add new product modal--}}
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
            <label for="brand" class="text-sm font-semibold text-gray-600">Brand Name:</label>
            <input type="text" name="brand_name" id="brand_name" placeholder="Enter Brand Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('brand_name') }}">
            @error('brand_name' , 'addproduct')
              <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
            @enderror
          </div>
          <div class="w-1/2">
            <label for="generic_name" class="text-sm font-semibold text-gray-600">Product Name:</label>
            <input type="text" name="generic_name" id="generic_name" placeholder="Enter Product Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('generic_name') }}">
            @error('generic_name', 'addproduct')
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
  {{-- end add new product modal--}}

  {{-- view all products modal--}}
  <div class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden" id="viewallproductsmodal">
    <div class="modal bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto p-6">
      <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4 sticky top-0 bg-white z-10">
        <p class="text-xl font-medium text-gray-700">All Products</p>
        <button id="closeviewallproductsmodal" class="p-2 rounded-full hover:bg-gray-100">
          <i class="fa-regular fa-xmark text-xl"></i>
        </button>
      </div>

      <div class="overflow-x-auto h-fit max-h-[70vh]">
        <table class="w-full text-sm text-left">
          <thead class="bg-gray-200 text-gray-700 uppercase text-xs sticky top-0">
            <tr>
              <th class="p-3">#</th>
              <th class="p-3">Product Details</th>
              <th class="p-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            @if ($products->isEmpty())
              <tr>
                <td colspan="3" class="p-3 text-center">No Products Available</td>
              </tr>
            @endif
            @foreach ($products as $product)
            <tr class="hover:bg-gray-50"
                data-product-id="{{ $product->id }}"
                data-brand="{{ $product->brand_name }}"
                data-product="{{ $product->generic_name }}"
                data-form="{{ $product->form }}"
                data-strength="{{ $product->strength }}">
              <td class="p-3">{{ $loop->iteration }}</td>
              <td class="p-3">
                <div class="flex gap-4">
                  <div>
                    <p class="font-semibold text-gray-700">{{ $product->brand_name }}</p>
                    <p class="italic text-gray-500">{{ $product->generic_name }}</p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-700">{{ $product->strength }}</p>
                    <p class="italic text-gray-500">{{ $product->form }}</p>
                  </div>
                </div>
              </td>
              <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                <button class="add-stock-btn bg-blue-100 text-blue-700 p-2 rounded-lg hover:-translate-y-1 duration-300 hover:bg-blue-600 hover:text-white transition-all mr-2">
                  <i class="fa-regular fa-plus mr-1"></i>Add Stock
                </button>
                <button class="edit-product-btn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 duration-300 hover:bg-green-600 hover:text-white transition-all">
                  <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                </button>
                <button class="delete-product-btn bg-red-100 text-red-700 p-2 rounded-lg hover:-translate-y-1 duration-300 hover:bg-red-600 hover:text-white transition-all">
                  <i class="fa-regular fa-trash mr-1"></i>Delete
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  {{-- end view all products modal--}}

  {{-- add stock modal --}}
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
  {{-- end add stock modal --}}

  {{-- edit product modal --}}
  <div class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden" id="editproductmodal">
      <div class="modal bg-white rounded-lg w-full max-w-lg p-5">
          <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
              <p class="text-xl font-medium text-gray-600">Edit Product</p>
              <button id="closeeditproductmodal" class="p-2 rounded-full hover:bg-gray-100">
                  <i class="fa-regular fa-xmark"></i>
              </button>
          </div>
          <form action="{{ route('admin.inventory.updateproduct') }}" method="POST" class="mt-5" id="edit-product-form">
              @csrf
              @method('PUT')
              <input type="hidden" name="product_id" id="edit-product-id" value="{{ old('product_id') }}">
              <div class="flex gap-2">
                  <div class="w-1/2">
                      <label for="edit-brand" class="text-sm font-semibold text-gray-600">Brand Name:</label>
                      <input type="text" name="brand_name" id="edit-brand" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('brand_name') }}" placeholder="Enter Brand Name">
                      @error('brand_name', 'updateproduct')
                          <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
                  <div class="w-1/2">
                      <label for="edit-product" class="text-sm font-semibold text-gray-600">Product Name:</label>
                      <input type="text" name="generic_name" id="edit-product" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('generic_name') }}" placeholder="Enter Product Name">
                      @error('generic_name', 'updateproduct')
                          <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
              </div>
              <div class="flex gap-2 mt-2">
                  <div class="w-1/2">
                      <label for="edit-form" class="text-sm font-semibold text-gray-600">Form:</label>
                      <input type="text" name="form" id="edit-form" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('form') }}" placeholder="Vials">
                      @error('form', 'updateproduct')
                          <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
                  <div class="w-1/2">
                      <label for="edit-strength" class="text-sm font-semibold text-gray-600">Strength:</label>
                      <input type="text" name="strength" id="edit-strength" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('strength') }}" placeholder="500mg">
                      @error('strength', 'updateproduct')
                          <p class="text-red-500 text-sm mt-1 error-message">{{ $message }}</p>
                      @enderror
                  </div>
              </div>
              <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                  <i class="fa-regular fa-check"></i>
                  <span>Update</span>
              </button>
          </form>
      </div>
  </div>
  {{-- end edit product modal --}}

  {{-- edit stock modal --}}
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
  {{-- end edit stock modal --}}
</body>
</html>

<script src="{{ asset('js/inventory.js') }}"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
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
  });
</script>