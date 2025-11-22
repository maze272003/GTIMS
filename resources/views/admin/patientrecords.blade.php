<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        
        {{-- Check for Authorization --}}
        @if(in_array(auth()->user()->user_level_id, [1, 2, 3, 4]))
            {{-- AUTHORIZED VIEW --}}
            <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
                
                {{-- HEADER with Branch Label --}}
                <div class="mb-6 pt-16 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Reports</span>
                    </p>

                    {{-- Current Unit Badge --}}
                    <div class="flex items-center gap-2">
                        <span class="hidden sm:inline text-sm text-gray-500 dark:text-gray-400">Current Unit:</span>
                        <span class="px-3 py-1 rounded-full text-sm font-bold border flex items-center shadow-sm
                            {{ auth()->user()->branch_id == 1 ? 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300' : '' }}
                            {{ auth()->user()->branch_id == 2 ? 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-800 dark:text-emerald-300' : '' }}
                            {{ auth()->user()->branch_id >= 3 ? 'bg-purple-50 border-purple-200 text-purple-700 dark:bg-purple-900/30 dark:border-purple-800 dark:text-purple-300' : '' }}
                        ">
                            <i class="fa-regular fa-building-columns mr-2"></i>
                            {{ auth()->user()->branch->name ?? 'Unknown Branch' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Product Dispensed</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $totalProductsDispensed ?? 0 }}</p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center">
                                    <i class="fa-regular fa-arrow-trend-up mr-1"></i> Medications dispensed
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
                                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total People Served</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $totalPeopleServed ?? 0 }}</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 flex items-center">
                                    <i class="fa-regular fa-users mr-1"></i> Patients served
                                </p>
                            </div>
                            <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded-full">
                                <i class="fa-regular fa-user text-2xl text-blue-600 dark:text-blue-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
                    <button id="adddispensationbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
                        <i class="fa-regular fa-plus mr-2"></i> Record New Dispensation
                    </button>
                </div>

                {{-- Records Table Container --}}
                <div id="patientrecords-data-container">
                    <div class="mt-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        
                        {{-- Header: Search, Filter Button, Export --}}
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                            
                            {{-- Search Bar --}}
                            <div class="relative w-full sm:w-1/3">
                                <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm"></i>
                                <input type="text" id="patientrecords-search-input" placeholder="Search records..." class="w-full pl-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400">
                            </div>

                            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                                {{-- === ADMIN FILTER DROPDOWN === --}}
                                @if(in_array(auth()->user()->user_level_id, [1, 2]) && isset($branches)) 
                                    <form method="GET" action="{{ route('admin.patientrecords') }}" class="flex items-center">
                                        <div class="relative">
                                            <i class="fa-regular fa-filter absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 text-xs"></i>
                                            <select name="branch_filter" onchange="this.form.submit()" class="pl-8 p-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer">
                                                <option value="all" {{ ($currentFilter ?? 'all') == 'all' ? 'selected' : '' }}>All Branches</option>
                                                @foreach($branches as $branch)
                                                    <option value="{{ $branch->id }}" {{ ($currentFilter ?? '') == $branch->id ? 'selected' : '' }}>
                                                        {{ $branch->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </form>
                                @endif

                                {{-- NEW FILTER MODAL BUTTON --}}
                                <button type="button" id="openFilterModal" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
                                    <i class="fa-regular fa-sliders-up text-lg mr-2"></i>
                                    <span class="hidden sm:inline">Filter</span>
                                </button>

                                <div class="flex gap-2">
                                    {{-- PDF Button --}}
                                    <a href="{{ route('admin.patientrecords.exportPdf', request()->all()) }}" target="_blank" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
                                        <i class="fa-regular fa-file-pdf text-lg text-red-600 dark:text-red-400"></i>
                                        <span class="ml-2 hidden sm:inline">PDF</span>
                                    </a>

                                    {{-- EXCEL Button --}}
                                    <a href="{{ route('admin.patientrecords.exportExcel', request()->all()) }}" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300">
                                        <i class="fa-regular fa-file-excel text-lg text-green-600 dark:text-green-400"></i>
                                        <span class="ml-2 hidden sm:inline">Excel</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- DYNAMIC TABLE CONTAINER --}}
                        <div id="table-container">
                            @include('admin.partials.patientrecords_table')
                        </div>

                    </div>
                </div>

                {{-- ==================== FILTER MODAL ==================== --}}
                <div id="filterModal" class="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden overflow-y-auto">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-md p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-5">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                                <i class="fa-regular fa-sliders-up mr-2 text-blue-600"></i> Filter Records
                            </h3>
                            <button type="button" id="closeFilterModal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400 text-xl"></i>
                            </button>
                        </div>

                        <form id="filterForm" method="GET" action="{{ route('admin.patientrecords') }}" class="space-y-5">

                            {{-- Preserve branch filter for Admin --}}
                            @if(auth()->user()->user_level_id <= 2)
                                <input type="hidden" name="branch_filter" value="{{ request('branch_filter', 'all') }}">
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                                    <input type="date" name="from_date" value="{{ request('from_date') }}"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                                    <input type="date" name="to_date" value="{{ request('to_date') }}"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                                <select name="category" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">All Categories</option>
                                    <option value="Adult" {{ request('category') == 'Adult' ? 'selected' : '' }}>Adult</option>
                                    <option value="Child" {{ request('category') == 'Child' ? 'selected' : '' }}>Child</option>
                                    <option value="Senior" {{ request('category') == 'Senior' ? 'selected' : '' }}>Senior</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                                <select name="barangay_id" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="">All Barangays</option>
                                    @foreach($barangays as $barangay)
                                        <option value="{{ $barangay->id }}" {{ request('barangay_id') == $barangay->id ? 'selected' : '' }}>
                                            {{ $barangay->barangay_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button type="button" id="clearFilters" class="px-5 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition font-medium">
                                    Clear All Filters
                                </button>
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Add Dispensation Modal --}}
                <div class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden overflow-auto" id="adddispensationmodal">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5 h-fit max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <p class="text-xl font-medium text-gray-600 dark:text-gray-300">Record New Dispensation</p>
                            <button id="closeadddispensationmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400"></i>
                            </button>
                        </div>
                        <form id="add-dispensation-form" action="{{ route('admin.patientrecords.adddispensation') }}" method="POST" class="mt-5">
                            @csrf
                            <div class="w-full">
                                <label for="patient-name" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Patient Name:</label>
                                <input type="text" name="patient-name" id="patient-name" placeholder="Enter Patient Name" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('patient-name') }}">
                                @error('patient-name', 'adddispensation')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                    <script>
                                        document.getElementById('adddispensationmodal').classList.remove('hidden');
                                    </script>
                                @enderror
                            </div>
                            <div class="flex gap-2 mt-3">
                                <div class="w-1/2">
                                    <label for="barangay_id" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Barangay:</label>
                                    <select name="barangay_id" id="barangay_id" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="" disabled {{ old('barangay_id') ? '' : 'selected' }}>Select Barangay</option>
                                        @foreach ($barangays as $barangay)
                                            <option value="{{ $barangay->id }}" {{ old('barangay_id') == $barangay->id ? 'selected' : '' }}>{{ $barangay->barangay_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('barangay_id', 'adddispensation')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-1/2">
                                    <label for="purok" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Purok:</label>
                                    <input type="text" name="purok" id="purok" placeholder="Enter Purok" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('purok') }}">
                                    @error('purok', 'adddispensation')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="w-full mt-3">
                                <label for="category" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Category:</label>
                                <select name="category" id="category" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="" disabled {{ old('category') ? '' : 'selected' }}>Select Category</option>
                                    <option value="Adult" {{ old('category') == 'Adult' ? 'selected' : '' }}>Adult</option>
                                    <option value="Child" {{ old('category') == 'Child' ? 'selected' : '' }}>Child</option>
                                    <option value="Senior" {{ old('category') == 'Senior' ? 'selected' : '' }}>Senior</option>
                                </select>
                                @error('category', 'adddispensation')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="mt-3" id="medication-container">
                                <div class="medication-group flex gap-2 items-end">
                                    <div class="flex-1">
                                        <label for="medication-0" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Medicine:</label>
                                        <div class="relative">
                                            @php
                                                $selected_med_label_0 = '';
                                                $old_med_id_0 = old('medications.0.name', '');
                                                foreach ($products as $inventory) {
                                                    if ($old_med_id_0 == $inventory->id) {
                                                        $selected_med_label_0 = ($inventory->product->generic_name ?? 'N/A') . ' - ' . ($inventory->product->brand_name ?? 'N/A') . ' (' . ($inventory->product->form ?? 'N/A') . ', ' . ($inventory->product->strength ?? 'N/A') . ') (' . ($inventory->batch_number ?? 'N/A') . ') - Available: ' . ($inventory->quantity ?? 0);
                                                        break;
                                                    }
                                                }
                                            @endphp
                                            <input type="text" class="search-med-input mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Search Medicine..." value="{{ $selected_med_label_0 }}">
                                            <div class="dropdown-options absolute z-50 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 max-h-60 overflow-y-auto hidden shadow-lg">
                                                @foreach ($products as $inventory)
                                                    <div class="option p-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer text-gray-900 dark:text-gray-100" 
                                                        data-id="{{ $inventory->id }}" 
                                                        data-label="{{ ($inventory->product->generic_name ?? 'N/A') }} - {{ ($inventory->product->brand_name ?? 'N/A') }} ({{ ($inventory->product->form ?? 'N/A') }}, {{ ($inventory->product->strength ?? 'N/A') }}) ({{ ($inventory->batch_number ?? 'N/A') }}) - Available: {{ ($inventory->quantity ?? 0) }}">
                                                        {{ ($inventory->product->generic_name ?? 'N/A') }} - {{ ($inventory->product->brand_name ?? 'N/A') }} ({{ ($inventory->product->form ?? 'N/A') }}, {{ ($inventory->product->strength ?? 'N/A') }}) ({{ ($inventory->batch_number ?? 'N/A') }}) - Available: {{ ($inventory->quantity ?? 0) }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <input type="hidden" name="medications[0][name]" class="med-name-hidden" value="{{ old('medications.0.name') }}">
                                        @error('medications.0.name', 'adddispensation')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-28">
                                        <label for="quantity-0" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Qty:</label>
                                        <input type="number" name="medications[0][quantity]" id="quantity-0" placeholder="Qty" min="1" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('medications.0.quantity') }}">
                                        @error('medications.0.quantity', 'adddispensation')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add-more-medication" class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 p-2 rounded-lg mt-3 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit text-sm text-gray-700 dark:text-gray-300">
                                <i class="fa-regular fa-plus mr-1"></i> Add More
                            </button>
                            <div class="mt-3">
                                <label for="date-dispensed" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Date Dispensed:</label>
                                <input type="date" name="date-dispensed" id="date-dispensed" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('date-dispensed') }}">
                                @error('date-dispensed', 'adddispensation')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                @enderror
                            </div>
                            @error('medications', 'adddispensation')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="bg-blue-500 dark:bg-blue-600 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                                <i class="fa-regular fa-check mr-1"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Edit Dispensation Modal --}}
                <div id="editrecordmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <p id="edit-record-title" class="text-xl font-medium text-gray-600 dark:text-gray-300">Edit Dispensation</p>
                            <button id="closeeditrecordmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400"></i>
                            </button>
                        </div>
                        <form id="edit-dispensation-form" action="{{ route('admin.patientrecords.update') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" id="edit-record-id" name="id">
                            <div class="w-full mb-3">
                                <label for="edit-patient-name" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Patient Name:</label>
                                <input type="text" name="patient-name" id="edit-patient-name" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @error('patient-name', 'editdispensation')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                    <script>
                                        document.getElementById('editrecordmodal').classList.remove('hidden');
                                    </script>
                                @enderror
                            </div>
                            <div class="flex gap-2 mb-3">
                                <div class="w-1/2">
                                    <label for="edit-barangay_id" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Barangay:</label>
                                    <select name="barangay_id" id="edit-barangay_id" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        <option value="" disabled>Select Barangay</option>
                                        @foreach ($barangays as $barangay)
                                            <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('barangay_id', 'editdispensation')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-1/2">
                                    <label for="edit-purok" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Purok:</label>
                                    <input type="text" name="purok" id="edit-purok" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    @error('purok', 'editdispensation')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-category" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Category:</label>
                                <select name="category" id="edit-category" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    <option value="Adult">Adult</option>
                                    <option value="Child">Child</option>
                                    <option value="Senior">Senior</option>
                                </select>
                                @error('category', 'editdispensation')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit-date-dispensed" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Date Dispensed:</label>
                                <input type="date" name="date-dispensed" id="edit-date-dispensed" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @error('date-dispensed', 'editdispensation')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400 error-message">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="button" id="update-dispensation-btn" class="bg-blue-500 dark:bg-blue-600 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                                <i class="fa-regular fa-check mr-1"></i> Update
                            </button>
                        </form>
                    </div>
                </div>

                {{-- View Medications Modal --}}
                <div id="viewmedicationsmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl p-5 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                            <p id="view-med-title" class="text-xl font-medium text-gray-600 dark:text-gray-300">Medications Dispensed</p>
                            <button id="closeviewmedmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Batch Number</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Medication Details</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Form & Strength</th>
                                        <th class="p-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody id="view-medications-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                                    {{-- JavaScript will populate this table--}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </main>
        @else
            {{-- UNAUTHORIZED VIEW --}}
            <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen flex flex-col items-center justify-center">
                <i class="fa-regular fa-lock text-6xl text-gray-400 mb-4"></i>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Unauthorized Access</h1>
                <p class="text-gray-600">You do not have permission to view this page.</p>
                <a href="{{ route('admin.inventory') }}" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Go to Inventory
                </a>
            </main>
        @endif
    </div>

    <script src="{{ asset('js/patientrecords.js') }}"></script>
</x-app-layout>