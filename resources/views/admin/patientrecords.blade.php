<x-app-layout>
    <body class="bg-gray-50">
        <x-admin.sidebar/>
        <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
            <x-admin.header/>
            <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
                <div class="mb-6 pt-16">
                    <p class="text-sm text-gray-600">Home / <span class="text-red-700 font-medium">Reports</span></p>
                </div>
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 font-medium">Total Product Dispensed</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalProductsDispensed ?? 0 }}</p>
                                <p class="text-xs text-green-600 mt-1 flex items-center">
                                    <i class="fa-regular fa-arrow-trend-up mr-1"></i> Medications dispensed
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
                                <p class="text-sm text-gray-600 font-medium">Total People Served</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalPeopleServed ?? 0 }}</p>
                                <p class="text-xs text-blue-600 mt-1 flex items-center">
                                    <i class="fa-regular fa-users mr-1"></i> Patients served
                                </p>
                            </div>
                            <div class="bg-blue-100 p-4 rounded-full">
                                <i class="fa-regular fa-user text-2xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Action Buttons -->
                <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
                    <button id="adddispensationbtn" class="bg-white inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
                        <i class="fa-regular fa-plus mr-2"></i> Record New Dispensation
                    </button>
                </div>
                <!-- Records Table -->
                <div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="relative w-full sm:w-1/2">
                            <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" placeholder="Search records..." class="w-full pl-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm">
                        </div>
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
                                    <th class="p-3 text-gray-700 uppercase text-sm text-left tracking-wide">Resident Details</th>
                                    <th class="p-3 text-gray-700 uppercase text-sm text-center tracking-wide">Resident Category</th>
                                    <th class="p-3 text-gray-700 uppercase text-sm tracking-wide">Date Dispensed</th>
                                    <th class="p-3 text-gray-700 uppercase text-sm text-center tracking-wide">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($patientrecords as $patientrecord)
                                    <tr data-record-id="{{ $patientrecord->id }}"
                                        data-patient-name="{{ $patientrecord->patient_name }}"
                                        data-barangay="{{ $patientrecord->barangay }}"
                                        data-purok="{{ $patientrecord->purok }}"
                                        data-category="{{ $patientrecord->category }}"
                                        data-medications="{{ json_encode($patientrecord->dispensedMedications->map(function ($med) {
                                            return [
                                                'batch' => $med->batch_number,
                                                'medication' => $med->generic_name,
                                                'brand' => $med->brand_name,
                                                'form' => $med->form,
                                                'strength' => $med->strength,
                                                'quantity' => $med->quantity,
                                            ];
                                        })->toArray()) }}">
                                        <td class="p-3 text-sm text-gray-700 text-left">{{ $loop->iteration }}</td>
                                        <td class="p-3 text-sm text-gray-700 text-left">
                                            <div>
                                                <p class="font-semibold text-gray-700 capitalize">{{ $patientrecord->patient_name }}</p>
                                                <p class="italic text-gray-500 capitalize">{{ $patientrecord->barangay }}, {{ $patientrecord->purok }}</p>
                                            </div>
                                        </td>
                                        <td class="p-3 text-sm text-gray-700 text-center">{{ $patientrecord->category }}</td>
                                        <td class="p-3 text-sm text-gray-700 text-center">
                                            <p class="font-semibold">{{ $patientrecord->date_dispensed->format('F j, Y') }}</p>
                                            <p class="italic text-gray-500">{{ $patientrecord->date_dispensed->format('h:mm A') }}
                                        </td>
                                        <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                                            <button class="view-medications-btn bg-blue-100 text-blue-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 hover:text-white font-semibold text-sm" data-record-id="{{ $patientrecord->id }}">
                                                <i class="fa-regular fa-eye mr-1"></i>View All
                                            </button>
                                            <button class="editrecordbtn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm" data-record-id="{{ $patientrecord->id }}">
                                                <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                                            </button>
                                            <button class="deleterecordbtn bg-red-100 text-red-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-red-600 hover:text-white font-semibold text-sm" data-record-id="{{ $patientrecord->id }}">
                                                <i class="fa-regular fa-trash mr-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-3 text-center text-gray-500">No records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Add Dispensation Modal --}}
                <div class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden overflow-auto" id="adddispensationmodal">
                    <div class="modal bg-white rounded-lg w-full max-w-lg p-5 h-fit max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                            <p class="text-xl font-medium text-gray-600">Record New Dispensation</p>
                            <button id="closeadddispensationmodal" class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fa-regular fa-xmark"></i>
                            </button>
                        </div>
                        <form id="add-dispensation-form" action="{{ route('admin.patientrecords.adddispensation') }}" method="POST" class="mt-5">
                            @csrf
                            <div class="w-full">
                                <label for="patient-name" class="text-sm font-semibold text-gray-600">Patient Name:</label>
                                <input type="text" name="patient-name" id="patient-name" placeholder="Enter Patient Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('patient-name') }}">
                                @error('patient-name', 'adddispensation')
                                    <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                    <script>
                                        document.getElementById('adddispensationmodal').classList.remove('hidden');
                                    </script>
                                @enderror
                            </div>
                            <div class="flex gap-2 mt-3">
                                <div class="w-1/2">
                                    <label for="barangay" class="text-sm font-semibold text-gray-600">Barangay:</label>
                                    <input type="text" name="barangay" id="barangay" placeholder="Enter Barangay" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('barangay') }}">
                                    @error('barangay', 'adddispensation')
                                        <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-1/2">
                                    <label for="purok" class="text-sm font-semibold text-gray-600">Purok:</label>
                                    <input type="text" name="purok" id="purok" placeholder="Enter Purok" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('purok') }}">
                                    @error('purok', 'adddispensation')
                                        <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="w-full mt-3">
                                <label for="category" class="text-sm font-semibold text-gray-600">Category:</label>
                                <select name="category" id="category" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                    <option value="" disabled {{ old('category') ? '' : 'selected' }}>Select Category</option>
                                    <option value="Adult" {{ old('category') == 'Adult' ? 'selected' : '' }}>Adult</option>
                                    <option value="Child" {{ old('category') == 'Child' ? 'selected' : '' }}>Child</option>
                                    <option value="Senior" {{ old('category') == 'Senior' ? 'selected' : '' }}>Senior</option>
                                </select>
                                @error('category', 'adddispensation')
                                    <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="mt-3" id="medication-container">
                                <div class="medication-group flex gap-2 items-end">
                                    <div class="flex-1">
                                        <label for="medication-0" class="text-sm font-semibold text-gray-600">Medicine:</label>
                                        <select name="medications[0][name]" id="medication-0" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                            <option value="" disabled selected>Select Medicine</option>
                                            @foreach ($products as $inventory)
                                                <option value="{{ $inventory->id }}" {{ old('medications.0.name') == $inventory->id ? 'selected' : '' }}>
                                                    {{ $inventory->product->generic_name ?? 'N/A' }} - {{ $inventory->product->brand_name ?? 'N/A' }} ({{ $inventory->product->form ?? 'N/A' }}, {{ $inventory->product->strength ?? 'N/A' }}) ({{ $inventory->batch_number ?? 'N/A' }}) - Available: {{ $inventory->quantity ?? 0 }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('medications.0.name', 'adddispensation')
                                            <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-28">
                                        <label for="quantity-0" class="text-sm font-semibold text-gray-600">Qty:</label>
                                        <input type="number" name="medications[0][quantity]" id="quantity-0" placeholder="Qty" min="1" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('medications.0.quantity') }}">
                                        @error('medications.0.quantity', 'adddispensation')
                                            <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add-more-medication" class="bg-white border border-gray-300 p-2 rounded-lg mt-3 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit text-sm">
                                <i class="fa-regular fa-plus mr-1"></i> Add More
                            </button>
                            <div class="mt-3">
                                <label for="date-dispensed" class="text-sm font-semibold text-gray-600">Date Dispensed:</label>
                                <input type="date" name="date-dispensed" id="date-dispensed" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" value="{{ old('date-dispensed') }}">
                                @error('date-dispensed', 'adddispensation')
                                    <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                                @enderror
                            </div>
                            @error('medications', 'adddispensation')
                                <p class="mt-1 text-sm text-red-600 error-message">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                                <i class="fa-regular fa-check mr-1"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>
                {{-- End Add Modal --}}
                {{-- Edit Dispensation Modal --}}
                <div id="editrecordmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white rounded-lg w-full max-w-lg p-5">
                        <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                            <p id="edit-record-title" class="text-xl font-medium text-gray-600">Edit Dispensation</p>
                            <button id="closeeditrecordmodal" class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fa-regular fa-xmark"></i>
                            </button>
                        </div>
                        <form id="edit-dispensation-form" action="#">
                            <input type="hidden" id="edit-record-id">
                            <div class="flex gap-2 mb-3">
                                <div class="flex-1">
                                    <label for="edit-patient-name" class="text-sm font-semibold text-gray-600">Patient Name:</label>
                                    <input type="text" name="patient-name" id="edit-patient-name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                            </div>
                            <div class="flex gap-2 mb-3">
                                <div class="w-1/2">
                                    <label for="edit-barangay" class="text-sm font-semibold text-gray-600">Barangay:</label>
                                    <input type="text" name="barangay" id="edit-barangay" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                <div class="w-1/2">
                                    <label for="edit-purok" class="text-sm font-semibold text-gray-600">Purok:</label>
                                    <input type="text" name="purok" id="edit-purok" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit-category" class="text-sm font-semibold text-gray-600">Category:</label>
                                <select name="category" id="edit-category" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                    <option value="Senior">Senior</option>
                                    <option value="Child">Child</option>
                                    <option value="Adult">Adult</option>
                                </select>
                            </div>
                            <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                                <i class="fa-regular fa-check mr-1"></i> Update
                            </button>
                        </form>
                    </div>
                </div>
                {{-- End Edit Modal --}}
                {{-- View Medications Modal --}}
                <div id="viewmedicationsmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white rounded-lg w-full max-w-4xl p-5 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                            <p id="view-med-title" class="text-xl font-medium text-gray-600">Medications Dispensed</p>
                            <button id="closeviewmedmodal" class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fa-regular fa-xmark"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-700">Batch Number</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-700">Medication Details</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-700">Form & Strength</th>
                                        <th class="p-3 text-center text-sm font-semibold text-gray-700">Quantity</th>
                                        <th class="p-3 text-center text-sm font-semibold text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="view-medications-tbody" class="divide-y divide-gray-200">
                                    {{-- JavaScript will populate this table--}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                {{-- End View Medications Modal --}}
            </main>
        </div>
    </body>
    <script src="{{ asset('js/patientrecords.js') }}"></script>
</x-app-layout>