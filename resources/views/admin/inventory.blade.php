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
              <p class="text-3xl font-bold text-gray-900 mt-2">1,248</p>
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
              <p class="text-3xl font-bold text-gray-900 mt-2">23</p>
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
              <p class="text-3xl font-bold text-gray-900 mt-2">8</p>
              <p class="text-xs text-red-600 mt-1 flex items-center">
                <i class="fa-regular fa-ban mr-1"></i> Must be removed
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
              <p class="text-3xl font-bold text-gray-900 mt-2">45</p>
              <p class="text-xs text-yellow-600 mt-1 flex items-center">
                <i class="fa-regular fa-hourglass-half mr-1"></i> Expires in 30 days
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
          <i class="f a-regular fa-eye mr-2"></i> View All Products
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
              <tr data-stock-id="1" data-batch="BO-001" data-brand="Arcimet" data-product="Ceftriaxone" data-form="Tablet" data-strength="500mg" data-quantity="100" data-expiry="2026-03-15">
                <td class="p-3 text-sm text-gray-700 text-left">1</td>
                <td class="p-3 text-sm text-gray-700 text-left font-semibold">BO-001</td>
                <td class="p-3 text-sm text-gray-700 text-left">
                  <div class="flex gap-4">
                    <div>
                      <p class="font-semibold text-gray-700">Ceftriaxone</p>
                      <p class="italic text-gray-500">Arcimet</p>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-700">500mg</p>
                      <p class="italic text-gray-500">Tablet</p>
                    </div>
                  </div>
                </td>
                <td class="p-3 text-sm text-left font-semibold text-green-700">100</td>
                <td class="p-3 text-sm text-gray-700">
                  <p class="p-2 border-l-2 border-green-500 bg-green-50 text-green-700 font-semibold text-center">In Stock</p>
                </td>
                <td class="p-3 text-sm text-gray-700 text-center">March 15, 2026</td>
                <td class="p-3">
                  <button class="edit-stock-btn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                    <i class="fa-regular fa-pen-to-square mr-1"></i>Edit Stock
                  </button>
                </td>
              </tr>
              <tr data-stock-id="2" data-batch="BO-002" data-brand="Arcimet" data-product="Ceftriaxone" data-form="Tablet" data-strength="500mg" data-quantity="20" data-expiry="2026-03-15">
                <td class="p-3 text-sm text-gray-700 text-left">2</td>
                <td class="p-3 text-sm text-gray-700 text-left font-semibold">BO-002</td>
                <td class="p-3 text-sm text-gray-700 text-left">
                  <div class="flex gap-4">
                    <div>
                      <p class="font-semibold text-gray-700">Ceftriaxone</p>
                      <p class="italic text-gray-500">Arcimet</p>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-700">500mg</p>
                      <p class="italic text-gray-500">Tablet</p>
                    </div>
                  </div>
                </td>
                <td class="p-3 text-sm text-left font-semibold text-yellow-700">20</td>
                <td class="p-3 text-sm text-gray-700">
                  <p class="p-2 border-l-2 border-yellow-500 bg-yellow-50 text-yellow-700 font-semibold text-center">Low Stock</p>
                </td>
                <td class="p-3 text-sm text-gray-700 text-center">March 15, 2026</td>
                <td class="p-3">
                  <button class="edit-stock-btn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                    <i class="fa-regular fa-pen-to-square mr-1"></i>Edit Stock
                  </button>
                </td>
              </tr>
              <tr data-stock-id="3" data-batch="BO-003" data-brand="Arcimet" data-product="Ceftriaxone" data-form="Tablet" data-strength="500mg" data-quantity="0" data-expiry="2026-03-15">
                <td class="p-3 text-sm text-gray-700 text-left">3</td>
                <td class="p-3 text-sm text-gray-700 text-left font-semibold">BO-003</td>
                <td class="p-3 text-sm text-gray-700 text-left">
                  <div class="flex gap-4">
                    <div>
                      <p class="font-semibold text-gray-700">Ceftriaxone</p>
                      <p class="italic text-gray-500">Arcimet</p>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-700">500mg</p>
                      <p class="italic text-gray-500">Tablet</p>
                    </div>
                  </div>
                </td>
                <td class="p-3 text-sm text-left font-semibold text-red-700">0</td>
                <td class="p-3 text-sm text-gray-700">
                  <p class="p-2 border-l-2 border-red-500 bg-red-50 text-red-700 font-semibold text-center">Out of Stock</p>
                </td>
                <td class="p-3 text-sm text-gray-700 text-center">March 15, 2026</td>
                <td class="p-3">
                  <button class="edit-stock-btn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                    <i class="fa-regular fa-pen-to-square mr-1"></i>Edit Stock
                  </button>
                </td>
              </tr>
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

      <form action="{{route('admin.inventory.addproduct')}}" class="mt-5" method="POST">
        @csrf
        <div class="flex gap-2">
          <div class="w-1/2">
            <label for="brand" class="text-sm font-semibold text-gray-600">Brand Name:</label>
            <input type="text" name="brand_name" id="brand_name" placeholder="Enter Brand Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
          <div class="w-1/2">
            <label for="generic_name" class="text-sm font-semibold text-gray-600">Product Name:</label>
            <input type="text" name="generic_name" id="generic_name" placeholder="Enter Product Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
        </div>

        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="form" class="text-sm font-semibold text-gray-600">Form:</label>
            <input type="text" name="form" id="form" placeholder="Form" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
          <div class="w-1/2">
            <label for="strength" class="text-sm font-semibold text-gray-600">Strength:</label>
            <input type="text" name="strength" id="strength" placeholder="500mg" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
        </div>

        <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
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

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="bg-gray-200 text-gray-700 uppercase text-xs">
            <tr>
              <th class="p-3">#</th>
              <th class="p-3">Product Details</th>
              <th class="p-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr class="hover:bg-gray-50"
                data-product-id="1"
                data-brand="Arcimet"
                data-product="Ceftriaxone"
                data-form="Tablet"
                data-strength="500mg">
              <td class="p-3">1</td>
              <td class="p-3">
                <div class="flex gap-4">
                  <div>
                    <p class="font-semibold text-gray-700">Ceftriaxone</p>
                    <p class="italic text-gray-500">Arcimet</p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-700">500mg</p>
                    <p class="italic text-gray-500">Tablet</p>
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

            <tr class="hover:bg-gray-50"
                data-product-id="2"
                data-brand="Pfizer"
                data-product="Amoxicillin"
                data-form="Capsule"
                data-strength="250mg">
              <td class="p-3">2</td>
              <td class="p-3">
                <div class="flex gap-4">
                  <div>
                    <p class="font-semibold text-gray-700">Amoxicillin</p>
                    <p class="italic text-gray-500">Pfizer</p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-700">250mg</p>
                    <p class="italic text-gray-500">Capsule</p>
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

            <tr class="hover:bg-gray-50"
                data-product-id="3"
                data-brand="Unilab"
                data-product="Paracetamol"
                data-form="Tablet"
                data-strength="500mg">
              <td class="p-3">3</td>
              <td class="p-3">
                <div class="flex gap-4">
                  <div>
                    <p class="font-semibold text-gray-700">Paracetamol</p>
                    <p class="italic text-gray-500">Unilab</p>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-700">500mg</p>
                    <p class="italic text-gray-500">Tablet</p>
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

      <form action="#">
        <input type="hidden" id="selected-product-id">

        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="batchnumber" class="text-sm font-semibold text-gray-600">Batch Number:</label>
            <input type="text" name="batchnumber" id="batchnumber" placeholder="Enter Batch Number" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
          <div class="w-1/2">
            <label for="quantity" class="text-sm font-semibold text-gray-600">Quantity:</label>
            <input type="number" name="quantity" id="quantity" placeholder="Enter Quantity" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
        </div>

        <div class="w-full mt-2">
          <label for="expiry" class="text-sm font-semibold text-gray-600">Expiry Date:</label>
          <input type="date" name="expiry" id="expiry" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
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

      <form action="#" class="mt-5">
        <input type="hidden" id="edit-product-id">

        <div class="flex gap-2">
          <div class="w-1/2">
            <label for="edit-brand" class="text-sm font-semibold text-gray-600">Brand Name:</label>
            <input type="text" name="brand" id="edit-brand" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
          <div class="w-1/2">
            <label for="edit-product" class="text-sm font-semibold text-gray-600">Product Name:</label>
            <input type="text" name="product" id="edit-product" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
        </div>

        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="edit-form" class="text-sm font-semibold text-gray-600">Form:</label>
            <input type="text" name="form" id="edit-form" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
          <div class="w-1/2">
            <label for="edit-strength" class="text-sm font-semibold text-gray-600">Strength:</label>
            <input type="text" name="strength" id="edit-strength" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
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

      <form action="#">
        <input type="hidden" id="edit-stock-id">

        <div class="mb-4">
          <p class="text-sm font-medium text-gray-700">
            <span id="edit-stock-product"></span>
          </p>
        </div>

        <div class="flex gap-2 mt-2">
          <div class="w-1/2">
            <label for="edit-batchnumber" class="text-sm font-semibold text-gray-600">Batch Number:</label>
            <input type="text" name="batchnumber" id="edit-batchnumber" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
          <div class="w-1/2">
            <label for="edit-quantity" class="text-sm font-semibold text-gray-600">Quantity:</label>
            <input type="number" name="quantity" id="edit-quantity" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
          </div>
        </div>

        <div class="w-full mt-2">
          <label for="edit-expiry" class="text-sm font-semibold text-gray-600">Expiry Date:</label>
          <input type="date" name="expiry" id="edit-expiry" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
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