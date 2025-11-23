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

                {{-- STATS CARDS --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Products Dispensed</p>
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
                            <a href="{{ route('admin.patientrecords.exportExcel') }}" class="bg-white dark:bg-gray-800 inline-flex items-center justify-center p-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                <i class="fa-regular fa-file-excel text-lg text-green-600"></i>
                                <span class="ml-2 hidden sm:inline">Export Excel</span>
                            </a>
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

                            {{-- Medication Logic Container --}}
                            <div class="mt-3" id="medication-container">
                                <div class="medication-group flex gap-2 items-end mb-2">
                                    <div class="flex-1">
                                        <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Medicine:</label>
                                        {{-- Note: Simplified select for brevity. Use your existing search logic or Select2 here --}}
                                        <select name="medications[0][name]" class="mt-1 p-2 w-full border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:text-gray-100">
                                            @foreach ($products as $inventory)
                                                 <option value="{{ $inventory->id }}">{{ $inventory->product->generic_name }} ({{ $inventory->quantity }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-20">
                                        <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Qty:</label>
                                        <input type="number" name="medications[0][quantity]" value="1" min="1" class="mt-1 p-2 w-full border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:text-gray-100">
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add-more-medication" class="text-sm text-blue-600 mt-1 hover:underline">+ Add More</button>

                            <div class="mt-3">
                                <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Date:</label>
                                <input type="date" name="date-dispensed" class="mt-1 p-2 w-full border border-gray-300 rounded-lg bg-white dark:bg-gray-700 dark:text-gray-100" value="{{ date('Y-m-d') }}">
                            </div>

                            {{-- SIGNATURE INPUT --}}
                            <div class="mt-4 border-t pt-4">
                                <label class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2 block">Patient Signature:</label>
                                <div class="flex items-center gap-3">
                                    <button type="button" id="open-signature-modal" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg flex items-center gap-2 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        <i class="fa-solid fa-pen-nib"></i> Tap to Sign
                                    </button>
                                    <span id="signature-status" class="hidden text-green-600 text-sm font-bold flex items-center gap-1">
                                        <i class="fa-solid fa-check-circle"></i> Signed
                                    </span>
                                </div>
                                <input type="hidden" name="signature" id="signature-input">
                                @error('signature', 'adddispensation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg mt-5 w-full hover:bg-blue-700 shadow-lg shadow-blue-500/30">Submit Record</button>
                        </form>
                    </div>
                </div>

                {{-- 2. DRAWING SIGNATURE MODAL (For Input) --}}
                <div id="signature-modal" class="fixed inset-0 z-[60] bg-white dark:bg-gray-900 hidden flex flex-col">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Sign Below</h3>
                        <button type="button" id="cancel-signature" class="text-gray-500 hover:text-red-500">
                            <i class="fa-solid fa-xmark text-2xl"></i>
                        </button>
                    </div>
                    <div class="flex-1 relative bg-gray-50 dark:bg-gray-800 touch-none" id="canvas-container">
                        <canvas id="signature-pad" class="block w-full h-full cursor-crosshair"></canvas>
                    </div>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-between items-center gap-4">
                        <button type="button" id="clear-signature" class="px-4 py-2 text-red-600 border border-red-600 rounded-lg hover:bg-red-50">Clear</button>
                        <button type="button" id="save-signature" class="flex-1 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Done</button>
                    </div>
                </div>

                {{-- 3. EDIT RECORD MODAL --}}
                <div id="editrecordmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5">
                        <div class="flex items-center justify-between border-b pb-3 mb-4">
                            <p class="text-xl font-medium text-gray-800 dark:text-gray-200">Edit Dispensation</p>
                            <button id="closeeditrecordmodal" class="text-gray-500 hover:text-gray-700"><i class="fa-regular fa-xmark"></i></button>
                        </div>
                        <form id="edit-dispensation-form" action="{{ route('admin.patientrecords.update') }}" method="POST">
                            @csrf @method('PUT')
                            <input type="hidden" id="edit-record-id" name="id">
                            <div class="w-full mb-3">
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Patient Name:</label>
                                <input type="text" name="patient-name" id="edit-patient-name" class="mt-1 p-2 w-full border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600">
                            </div>
                            <div class="flex gap-2">
                                <div class="w-1/2">
                                     <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Barangay:</label>
                                     <select name="barangay_id" id="edit-barangay" class="mt-1 p-2 w-full border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                        @foreach ($barangays as $barangay)
                                            <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                                        @endforeach
                                     </select>
                                </div>
                                <div class="w-1/2">
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Purok:</label>
                                    <input type="text" name="purok" id="edit-purok" class="mt-1 p-2 w-full border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                </div>
                            </div>
                             <div class="w-full mt-3">
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Category:</label>
                                <select name="category" id="edit-category" class="mt-1 p-2 w-full border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                    <option value="Adult">Adult</option>
                                    <option value="Child">Child</option>
                                    <option value="Senior">Senior</option>
                                </select>
                            </div>
                             <div class="w-full mt-3">
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Date:</label>
                                <input type="date" name="date-dispensed" id="edit-date" class="mt-1 p-2 w-full border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600">
                            </div>

                            <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 w-full hover:bg-blue-600">Update Record</button>
                        </form>
                    </div>
                </div>

                {{-- 4. VIEW MEDICATIONS MODAL --}}
                <div id="viewmedicationsmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl p-5">
                        <div class="flex items-center justify-between border-b pb-3 mb-4">
                            <p class="text-xl font-medium text-gray-800 dark:text-gray-200">Medications Dispensed</p>
                            <button id="closeviewmedmodal" class="text-gray-500 hover:text-gray-700"><i class="fa-regular fa-xmark"></i></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-400">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase">
                                    <tr>
                                        <th class="px-4 py-2">Batch</th>
                                        <th class="px-4 py-2">Product</th>
                                        <th class="px-4 py-2">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="view-medications-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- 5. VIEW SIGNATURE MODAL (DISPLAY IMAGE) --}}
                <div id="viewSignatureModal" class="fixed inset-0 z-[70] bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 hidden transition-opacity duration-300">
                    <div class="modal-content bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all scale-95 opacity-0">
                        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                                <i class="fa-solid fa-file-signature text-blue-600 mr-2"></i> Patient Signature
                            </h3>
                            <button type="button" id="closeViewSignatureModal" class="text-gray-400 hover:text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg p-1.5 inline-flex items-center justify-center focus:outline-none transition-colors">
                                <span class="sr-only">Close modal</span>
                                <i class="fa-solid fa-xmark text-xl"></i>
                            </button>
                        </div>
                        <div class="p-6 flex items-center justify-center bg-gray-100 dark:bg-gray-900/50 min-h-[300px] relative">
                            <img id="signatureImageDisplay" src="" alt="Patient Signature" class="max-w-full max-h-[60vh] object-contain border-2 border-white dark:border-gray-700 shadow-sm rounded-lg">
                            <div id="sig-loading" class="absolute inset-0 flex items-center justify-center hidden">
                                <i class="fa-solid fa-circle-notch fa-spin text-3xl text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        @else
            {{-- UNAUTHORIZED VIEW --}}
            <main class="pt-20 p-4 min-h-screen flex flex-col items-center justify-center">
                <h1 class="text-3xl font-bold text-red-600">Unauthorized Access</h1>
            </main>
        @endif
    </div>

    {{-- SCRIPTS --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="{{ asset('js/patientrecords.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            // --- 1. AJAX SEARCH & PAGINATION ---
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
                })
                .catch(error => console.error('Error:', error));
            }

            // --- 2. MODAL TOGGLES (Add, Edit, View Meds) ---
            const addModal = document.getElementById('adddispensationmodal');
            const openAddBtn = document.getElementById('adddispensationbtn');
            const closeAddBtn = document.getElementById('closeadddispensationmodal');
            
            if(openAddBtn) openAddBtn.addEventListener('click', () => addModal.classList.remove('hidden'));
            if(closeAddBtn) closeAddBtn.addEventListener('click', () => addModal.classList.add('hidden'));

            // Edit and View Meds logic typically resides in your external JS file or here
            // using Event Delegation similar to the signature logic below.

            // --- 3. SIGNATURE PAD (DRAWING) LOGIC ---
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
                            openSigModalBtn.classList.remove('bg-gray-100', 'text-gray-700');
                            openSigModalBtn.classList.add('bg-blue-100', 'text-blue-700', 'border-blue-300');
                            
                            signatureModal.classList.add('hidden');
                        }
                    });
                }
                window.addEventListener("resize", resizeCanvas);
            }

            // --- 4. VIEW SIGNATURE MODAL (DISPLAY) LOGIC ---
            const viewSigModal = document.getElementById('viewSignatureModal');
            const viewSigModalContent = viewSigModal ? viewSigModal.querySelector('.modal-content') : null;
            const closeViewSigBtn = document.getElementById('closeViewSignatureModal');
            const sigImageDisplay = document.getElementById('signatureImageDisplay');
            const sigLoading = document.getElementById('sig-loading');

            function openSignatureModal(imageSrc) {
                if (!viewSigModal || !sigImageDisplay) return;

                sigImageDisplay.src = '';
                sigImageDisplay.classList.add('opacity-0');
                if(sigLoading) sigLoading.classList.remove('hidden');

                viewSigModal.classList.remove('hidden');
                setTimeout(() => {
                    viewSigModalContent.classList.remove('scale-95', 'opacity-0');
                    viewSigModalContent.classList.add('scale-100', 'opacity-100');
                }, 10);

                sigImageDisplay.src = imageSrc;
                sigImageDisplay.onload = function() {
                    if(sigLoading) sigLoading.classList.add('hidden');
                    sigImageDisplay.classList.remove('opacity-0');
                    sigImageDisplay.classList.add('transition-opacity', 'duration-300');
                };
            }

            function closeSignatureModal() {
                if (!viewSigModal || !viewSigModalContent) return;
                viewSigModalContent.classList.remove('scale-100', 'opacity-100');
                viewSigModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                     viewSigModal.classList.add('hidden');
                     sigImageDisplay.src = ''; 
                }, 300);
            }

            // Event Delegation for Table Buttons (Handles AJAX reloads)
            if (tableContainer) {
                tableContainer.addEventListener('click', function(e) {
                    const btn = e.target.closest('.view-signature-btn');
                    if (btn) {
                        const src = btn.getAttribute('data-src');
                        if (src) openSignatureModal(src);
                    }
                });
            }

            if (closeViewSigBtn) closeViewSigBtn.addEventListener('click', closeSignatureModal);
            if (viewSigModal) {
                 viewSigModal.addEventListener('click', function(e) {
                    if (e.target === viewSigModal) closeSignatureModal();
                });
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && viewSigModal && !viewSigModal.classList.contains('hidden')) {
                    closeSignatureModal();
                }
            });

        });
    </script>
</x-app-layout>