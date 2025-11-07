@php
  use Carbon\Carbon;
@endphp
<x-app-layout>
  <x-admin.sidebar/>

  <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
    <x-admin.header/>
    <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
      <div class="mb-6 pt-16">
        <p class="text-sm text-gray-600 dark:text-gray-400">Home / <span class="text-red-700 dark:text-red-300 font-medium">Inventory</span></p>
      </div>

      {{-- card --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">In Stock</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                {{ $inventorycount->where('quantity', '>=', 100)->count() }}
              </p>
              <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center">
                <i class="fa-regular fa-arrow-trend-up mr-1"></i> Currently in stock
              </p>
            </div>
            <div class="bg-green-100 dark:bg-green-900 p-4 rounded-full">
              <i class="fa-regular fa-boxes-stacked text-2xl text-green-600 dark:text-green-400"></i>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Low Stock</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $inventorycount->where('quantity', '<', 100)->where('quantity', '>', 0)->count() }}</p>
              <p class="text-xs text-orange-600 dark:text-orange-400 mt-1 flex items-center">
                <i class="fa-regular fa-triangle-exclamation mr-1"></i> Requires attention
              </p>
            </div>
            <div class="bg-orange-100 dark:bg-orange-900 p-4 rounded-full">
              <i class="fa-regular fa-exclamation text-2xl text-orange-600 dark:text-orange-400"></i>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Expired Stock</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                {{ $inventorycount->where('expiry_date', '<', Carbon::now())->count() }}
              </p>
              <p class="text-xs text-red-600 dark:text-red-400 mt-1 flex items-center">
                <i class="fa-regular fa-ban mr-1"></i>Must be removed
              </p>
            </div>
            <div class="bg-red-100 dark:bg-red-900 p-4 rounded-full">
              <i class="fa-regular fa-xl fa-calendar-xmark text-red-600 dark:text-red-400"></i>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Nearly Expired</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                {{ $inventorycount->where('expiry_date', '>', Carbon::now())->where('expiry_date', '<', Carbon::now()->addDays(30))->count() }}
              </p>
              <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1 flex items-center">
                <i class="fa-regular fa-hourglass-half mr-1"></i>Expires in 30 days
              </p>
            </div>
            <div class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded-full">
              <i class="fa-regular fa-clock text-2xl text-yellow-600 dark:text-yellow-400"></i>
            </div>
          </div>
        </div>
      </div>
      {{-- end card --}}

      {{-- buttons --}}
      <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
        <button id="addnewproductbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
          <i class="fa-regular fa-plus mr-2"></i> Register New Product
        </button>
        <button id="viewallproductsbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
          <i class="fa-regular fa-eye mr-2"></i> View All Products
        </button>
        <button id="viewarchiveproductsbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
          <i class="fa-regular fa-box-archive mr-2"></i> View Archive Products
        </button>
      </div>
      {{-- end buttons --}}

      {{-- table --}}
      <div class="mt-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <div class="relative w-1/2">
            <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm"></i>
            <input type="text" id="inventory-search-input" placeholder="Search products..." class="w-full pl-10 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ request('search') }}">
          </div>
          
          {{-- export button --}}
          <button class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
            <i class="fa-regular fa-file-export text-lg text-green-600 dark:text-green-400"></i>
            <span class="ml-2">Export into CSV</span>
          </button>
        </div>

        <div class="overflow-x-auto p-5" id="inventory-data-container">
          @include('admin.partials._inventory_table')
      </div>
      {{-- end table--}}
    </main>
  </div>

  {{-- modals --}}
  {{-- view all products modal--}}
  @include('components.admin.modals.inventory.view-all-products', [
      'products' => $products 
  ])
  {{-- view archive products modal --}}
  @include('components.admin.modals.inventory.view-archive-products', [
      'archiveproducts' => $archiveproducts,
  ])

  {{-- view archived stocks modal --}}
  {{-- @include('components.admin.modals.inventory.archived-stocks', [
      'archivedstocks' => $archivedstocks,
  ]) --}}
   @include('components.admin.modals.inventory.archived-stocks')

  {{-- add new product modal--}}
  @include('components.admin.modals.inventory.add-new-product')

  {{-- add stock modal --}}
  @include('components.admin.modals.inventory.add-stock')

  {{-- edit product modal --}}
  @include('components.admin.modals.inventory.edit-product')

  {{-- edit stock modal --}}
  @include('components.admin.modals.inventory.edit-stock')
</x-app-layout>

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