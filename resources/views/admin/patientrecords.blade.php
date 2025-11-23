<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        
        @if(in_array(auth()->user()->user_level_id, [1, 2, 3, 4]))
            {{-- AUTHORIZED VIEW --}}
            <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
                
                {{-- HEADER --}}
                <div class="mb-6 pt-16 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Reports</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <span class="hidden sm:inline text-sm text-gray-500 dark:text-gray-400">Current Unit:</span>
                        <span class="px-3 py-1 rounded-full text-sm font-bold border flex items-center shadow-sm bg-blue-50 text-blue-700">
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
                            </div>
                            <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded-full">
                                <i class="fa-regular fa-user text-2xl text-blue-600 dark:text-blue-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
                    <button id="adddispensationbtn" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all text-gray-700 dark:text-gray-300">
                        <i class="fa-regular fa-plus mr-2"></i> Record New Dispensation
                    </button>
                </div>

                {{-- TABLE CONTAINER --}}
                <div class="mt-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    
                    {{-- Header: Search, Filter --}}
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="relative w-full sm:w-1/3">
                            <i class="fa-regular fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                            {{-- AJAX SEARCH INPUT --}}
                            <input type="text" id="ajax-search-input" placeholder="Search records..." class="w-full pl-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            @if(in_array(auth()->user()->user_level_id, [1, 2]) && isset($branches)) 
                                <form method="GET" action="{{ route('admin.patientrecords') }}" class="flex items-center">
                                    <div class="relative">
                                        <i class="fa-regular fa-filter absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs"></i>
                                        <select name="branch_filter" onchange="this.form.submit()" class="pl-8 p-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none bg-white cursor-pointer">
                                            <option value="all" {{ ($currentFilter ?? 'all') == 'all' ? 'selected' : '' }}>All Branches</option>
                                            @foreach($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ ($currentFilter ?? '') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </form>
                            @endif
                            <button class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-2.5 border border-gray-300 rounded-lg text-gray-700">
                                <i class="fa-regular fa-file-export text-lg text-green-600"></i>
                                <span class="ml-2 hidden sm:inline">Export CSV</span>
                            </button>
                        </div>
                    </div>

                    {{-- AJAX TABLE CONTENT --}}
                    <div id="table-container">
                        @include('admin.partials.patientrecords_table')
                    </div>
                </div>

                {{-- ================= MODALS SECTION ================= --}}

                {{-- 1. Add Dispensation Modal --}}
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
                                <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Patient Name:</label>
                                <input type="text" name="patient-name" placeholder="Enter Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none bg-white dark:bg-gray-700 dark:text-gray-100" value="{{ old('patient-name') }}">
                                @error('patient-name', 'adddispensation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <div class="w-1/2">
                                    <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Barangay:</label>
                                    <select name="barangay_id" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none bg-white dark:bg-gray-700 dark:text-gray-100">
                                        <option value="" disabled selected>Select</option>
                                        @foreach ($barangays as $barangay)
                                            <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('barangay_id', 'adddispensation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div class="w-1/2">
                                    <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Purok:</label>
                                    <input type="text" name="purok" placeholder="Purok" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none bg-white dark:bg-gray-700 dark:text-gray-100" value="{{ old('purok') }}">
                                    @error('purok', 'adddispensation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="w-full mt-3">
                                <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Category:</label>
                                <select name="category" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none bg-white dark:bg-gray-700 dark:text-gray-100">
                                    <option value="Adult">Adult</option>
                                    <option value="Child">Child</option>
                                    <option value="Senior">Senior</option>
                                </select>
                            </div>

                            {{-- Medication Logic (simplified for brevity, ensure your JS handles 'add-more') --}}
                            <div class="mt-3" id="medication-container">
                                <div class="medication-group flex gap-2 items-end">
                                    <div class="flex-1">
                                        <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Medicine:</label>
                                        <div class="relative">
                                            <input type="text" class="search-med-input mt-1 p-2 w-full border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:text-gray-100" placeholder="Search...">
                                            <div class="dropdown-options absolute z-50 w-full bg-white border border-gray-300 rounded-lg mt-1 max-h-60 overflow-y-auto hidden shadow-lg">
                                                @foreach ($products as $inventory)
                                                    <div class="option p-2 hover:bg-gray-100 cursor-pointer" data-id="{{ $inventory->id }}" data-label="{{ $inventory->product->generic_name }} - {{ $inventory->product->brand_name }} ({{ $inventory->batch_number }})">
                                                        {{ $inventory->product->generic_name }} - {{ $inventory->product->brand_name }} ({{ $inventory->batch_number }})
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <input type="hidden" name="medications[0][name]" class="med-name-hidden">
                                    </div>
                                    <div class="w-28">
                                        <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Qty:</label>
                                        <input type="number" name="medications[0][quantity]" placeholder="Qty" class="mt-1 p-2 w-full border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:text-gray-100">
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add-more-medication" class="text-sm text-blue-600 mt-2">+ Add More</button>

                            <div class="mt-3">
                                <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Date:</label>
                                <input type="date" name="date-dispensed" class="mt-1 p-2 w-full border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:text-gray-100" value="{{ old('date-dispensed') }}">
                                @error('date-dispensed', 'adddispensation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            {{-- SIGNATURE TRIGGER --}}
                            <div class="mt-4">
                                <label class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2 block">Signature:</label>
                                <div class="flex items-center gap-3">
                                    <button type="button" id="open-signature-modal" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg flex items-center gap-2 hover:bg-gray-300">
                                        <i class="fa-solid fa-pen-nib"></i> Tap to Sign
                                    </button>
                                    <span id="signature-status" class="hidden text-green-600 text-sm font-bold"><i class="fa-solid fa-check"></i> Signed</span>
                                </div>
                                <input type="hidden" name="signature" id="signature-input">
                                @error('signature', 'adddispensation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 w-fit hover:bg-blue-600">Submit</button>
                        </form>
                    </div>
                </div>

                {{-- 2. FULL SCREEN SIGNATURE MODAL --}}
                <div id="signature-modal" class="fixed inset-0 z-[60] bg-white dark:bg-gray-900 hidden flex flex-col">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Sign Below</h3>
                        <button type="button" id="cancel-signature" class="text-gray-500 hover:text-red-500">
                            <i class="fa-solid fa-xmark text-2xl"></i>
                        </button>
                    </div>
                    <div class="flex-1 relative bg-gray-50 dark:bg-gray-800 touch-none" id="canvas-container">
                        <canvas id="signature-pad" class="block w-full h-full"></canvas>
                    </div>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-between items-center gap-4">
                        <button type="button" id="clear-signature" class="px-4 py-2 text-red-600 border border-red-600 rounded-lg">Clear</button>
                        <button type="button" id="save-signature" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg">Done</button>
                    </div>
                </div>

                {{-- 3. Edit Modal (Reinstated) --}}
                <div id="editrecordmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5">
                        <div class="flex items-center justify-between border-b pb-3 mb-4">
                            <p class="text-xl font-medium">Edit Dispensation</p>
                            <button id="closeeditrecordmodal"><i class="fa-regular fa-xmark"></i></button>
                        </div>
                        <form id="edit-dispensation-form" action="{{ route('admin.patientrecords.update') }}" method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" id="edit-record-id" name="id">
                            <div class="w-full mb-3">
                                <label class="text-sm font-semibold">Patient Name:</label>
                                <input type="text" name="patient-name" id="edit-patient-name" class="mt-1 p-2 w-full border rounded-lg">
                            </div>
                            {{-- Add other edit fields as needed based on your original code --}}
                            <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5">Update</button>
                        </form>
                    </div>
                </div>

                {{-- 4. View Modal (Reinstated) --}}
                <div id="viewmedicationsmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl p-5">
                        <div class="flex items-center justify-between border-b pb-3 mb-4">
                            <p class="text-xl font-medium">Medications Dispensed</p>
                            <button id="closeviewmedmodal"><i class="fa-regular fa-xmark"></i></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full"><tbody id="view-medications-tbody"></tbody></table>
                        </div>
                    </div>
                </div>

            </main>
        @else
            {{-- UNAUTHORIZED VIEW --}}
            <main class="pt-20 p-4 min-h-screen flex flex-col items-center justify-center">
                <h1 class="text-3xl font-bold">Unauthorized Access</h1>
            </main>
        @endif
    </div>

    {{-- SCRIPTS --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="{{ asset('js/patientrecords.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // --- AJAX SEARCH & PAGINATION ---
            const searchInput = document.getElementById('ajax-search-input');
            const tableContainer = document.getElementById('table-container');
            let typingTimer;

            if(searchInput && tableContainer) {
                searchInput.addEventListener('keyup', function () {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => fetchRecords(this.value), 500);
                });

                tableContainer.addEventListener('click', function (e) {
                    const link = e.target.closest('.pagination-links a');
                    if (link) {
                        e.preventDefault();
                        const url = link.getAttribute('href');
                        const searchValue = searchInput.value;
                        const finalUrl = url + (url.includes('?') ? '&' : '?') + 'search=' + encodeURIComponent(searchValue);
                        fetchRecords(null, finalUrl);
                    }
                });
            }

            function fetchRecords(query = null, url = null) {
                let fetchUrl = url || "{{ route('admin.patientrecords') }}";
                if (!url && query) fetchUrl += "?search=" + encodeURIComponent(query);

                fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.text())
                .then(html => {
                    tableContainer.innerHTML = html;
                    // Note: Re-bind view/edit/delete buttons here if they rely on direct event listeners
                })
                .catch(error => console.error('Error:', error));
            }

            // --- SIGNATURE PAD LOGIC ---
            const signatureModal = document.getElementById('signature-modal');
            const openSigModalBtn = document.getElementById('open-signature-modal');
            const closeSigModalBtn = document.getElementById('cancel-signature');
            const saveSigBtn = document.getElementById('save-signature');
            const clearSigBtn = document.getElementById('clear-signature');
            const canvas = document.getElementById('signature-pad');
            const hiddenInput = document.getElementById('signature-input');
            const sigStatus = document.getElementById('signature-status');
            const canvasContainer = document.getElementById('canvas-container');

            let signaturePad;

            if (canvas) {
                signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)'
                });

                function resizeCanvas() {
                    if(signatureModal.classList.contains('hidden')) return;
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvasContainer.offsetWidth * ratio;
                    canvas.height = canvasContainer.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    signaturePad.clear(); 
                }

                if(openSigModalBtn) {
                    openSigModalBtn.addEventListener('click', function() {
                        signatureModal.classList.remove('hidden');
                        resizeCanvas();
                    });
                }

                if(closeSigModalBtn) closeSigModalBtn.addEventListener('click', () => signatureModal.classList.add('hidden'));
                if(clearSigBtn) clearSigBtn.addEventListener('click', () => signaturePad.clear());

                if(saveSigBtn) {
                    saveSigBtn.addEventListener('click', function () {
                        if (signaturePad.isEmpty()) {
                            alert("Please provide a signature.");
                        } else {
                            const data = signaturePad.toDataURL('image/png');
                            if(hiddenInput) hiddenInput.value = data;
                            if(sigStatus) sigStatus.classList.remove('hidden');
                            
                            openSigModalBtn.innerHTML = '<i class="fa-solid fa-pen-nib"></i> Resign';
                            openSigModalBtn.classList.add('bg-blue-100', 'text-blue-700');
                            
                            signatureModal.classList.add('hidden');
                        }
                    });
                }
                window.addEventListener("resize", resizeCanvas);
            }
        });
    </script>
</x-app-layout>