@php
  use Carbon\Carbon;
@endphp
<x-app-layout>
    <x-slot name="title">
      Product Movements - General Tinio
    </x-slot>
{{-- this block of code is transferded to app layout --}}
{{-- <body class="bg-gray-50"> --}}
  {{-- <x-admin.sidebar/> --}}

  {{-- <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
    <x-admin.header/> --}}
    <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
      <div class="mb-6 pt-16">
        {{-- BREADCRUMB --}}
        <p class="text-sm text-gray-600">Home / <span class="text-red-700 font-medium">Product Movements</span></p>
      </div>

      {{-- NEW STAT CARDS --}}
      {{-- Note: You will need to pass these variables from your ProductMovementController --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Movements Today</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{-- $movementsTodayCount ?? '0' --}}
                0 
              </p>
              <p class="text-xs text-blue-600 mt-1 flex items-center">
                <i class="fa-regular fa-clock mr-1"></i> In the last 24 hours
              </p>
            </div>
            <div class="bg-blue-100 p-4 rounded-full">
              <i class="fa-regular fa-xl fa-clock text-blue-600"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Items IN Today</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{-- number_format($itemsInToday ?? 0) --}}
                0
              </p>
              <p class="text-xs text-green-600 mt-1 flex items-center">
                <i class="fa-regular fa-arrow-down-to-bracket mr-1"></i> Total stock received
              </p>
            </div>
            <div class="bg-green-100 p-4 rounded-full">
              <i class="fa-regular fa-arrow-down-to-bracket text-2xl text-green-600"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-medium">Items OUT Today</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">
                {{-- number_format($itemsOutToday ?? 0) --}}
                0
              </p>
              <p class="text-xs text-red-600 mt-1 flex items-center">
                <i class="fa-regular fa-arrow-up-from-bracket mr-1"></i> Total stock dispatched
              </p>
            </div>
            <div class="bg-red-100 p-4 rounded-full">
                <i class="fa-regular fa-arrow-up-from-bracket text-2xl text-red-600"></i>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600 font-medium">Total Movements</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">
                  {{-- number_format($movements->total() ?? 0) --}}
                  {{ number_format($movements->total()) }}
                </p>
                <p class="text-xs text-gray-600 mt-1 flex items-center">
                  <i class="fa-regular fa-boxes-stacked mr-1"></i> All time records
                </p>
              </div>
              <div class="bg-gray-100 p-4 rounded-full">
                <i class="fa-regular fa-boxes-stacked text-2xl text-gray-600"></i>
              </div>
            </div>
          </div>
      </div>
      {{-- end card --}}

      {{-- NEW FILTER SECTION --}}
      <div class="mt-6 bg-white rounded-xl shadow-sm p-6 border border-gray-200">
        <form id="filter-form" action="{{ route('admin.movements') }}" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {{-- Search --}}
                <div class="relative">
                    <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search batch/description..." class="w-full pl-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                </div>
                
                {{-- Product --}}
                <select name="product_id" class="w-full pl-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                            {{ $product->generic_name }} ({{ $product->brand_name }})
                        </option>
                    @endforeach
                </select>

                {{-- Type --}}
                <select name="type" class="w-full pl-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                    <option value="">All Types</option>
                    <option value="IN" @selected(request('type') == 'IN')>IN</option>
                    <option value="OUT" @selected(request('type') == 'OUT')>OUT</option>
                </select>

                {{-- User --}}
                <select name="user_id" class="w-full pl-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date Filters and Buttons --}}
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-4">
                {{-- From Date --}}
                <div>
                    <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" name="from" id="from_date" value="{{ request('from') }}" class="w-full pl-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                </div>
                {{-- To Date --}}
                <div>
                    <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" name="to" id="to_date" value="{{ request('to') }}" class="w-full pl-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                </div>
                {{-- Sort --}}
                 <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                    <select name="sort" id="sort" class="w-full pl-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                        <option value="desc" @selected(request('sort', 'desc') == 'desc')>Newest First</option>
                        <option value="asc" @selected(request('sort') == 'asc')>Oldest First</option>
                    </select>
                </div>
                {{-- Buttons --}}
                <div class="flex items-end space-x-2">
                    <a href="{{ route('admin.movements') }}" class="w-full bg-white inline-flex items-center justify-center px-5 py-3 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
                        Clear
                    </a>
                    <button type="submit" class="w-full bg-red-700 text-white inline-flex items-center justify-center px-5 py-3 border border-transparent rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
                        Apply
                    </button>
                </div>
            </div>
        </form>
      </div>

      {{-- TABLE --}}
      <div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Export Button (from your inventory page) --}}
         <div class="p-4 border-b border-gray-200 flex items-center justify-end">
             <button class="bg-white inline-flex items-center justify-center p-2 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
              <i class="fa-regular fa-file-export text-lg text-green-600"></i>
              <span class="ml-2">Export into CSV</span>
            </button>
         </div>

        <div class="overflow-x-auto p-5" id="movements-data-container">
          @include('admin.partials.movements_table')
        </div>
      </div>
      {{-- end table--}}
    </main>
  </div>

  {{-- Modals and scripts from inventory page are not needed here --}}
</body>
</html>

{{-- You can create a movements.js for AJAX filtering/pagination if needed --}}
{{-- <script src="{{ asset('js/movements.js') }}"></script> --}}
</x-app-layout>