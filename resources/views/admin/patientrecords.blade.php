<x-app-layout>
    <x-slot name="title">
        Patients Records - General Tinio
    </x-slot>
<body class="bg-gray-50">
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-600">Home / <span class="text-red-700 font-medium">Reports</span></p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-medium">Total Product Dispensed</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">1,524</p>
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
                            <p class="text-3xl font-bold text-gray-900 mt-2">342</p>
                            <p class="text-xs text-blue-600 mt-1 flex items-center">
                                <i class="fa-regular fa-users mr-1"></i> Patients served
                            </p>
                        </div>
                        <div class="bg-blue-100 p-4 rounded-full">
                            <i class="fa-regular fa-user text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>
                {{-- <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 font-medium">Expired Dispensed</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">3</p>
                            <p class="text-xs text-red-600 mt-1 flex items-center">
                                <i class="fa-regular fa-ban mr-1"></i> Must be reviewed
                            </p>
                        </div>
                        <div class="bg-red-100 p-4 rounded-full">
                            <i class="fa-regular fa-calendar-xmark text-2xl text-red-600"></i>
                        </div>
                    </div>
                </div> --}}
            </div>
            <div class="mt-6 flex flex-col sm:flex-row gap-3 w-full justify-end">
                <button id="adddispensationbtn" class="bg-white inline-flex items-center justify-center px-5 py-2.5 border border-gray-300 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200">
                    <i class="fa-regular fa-plus mr-2"></i> Record New Dispensation
                </button>
            </div>
            <div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="relative w-1/2">
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
                                <th class="p-3 text-gray-700 uppercase text-sm text-center tracking-wide">Category (Senior/Adult/Child)</th>
                                <th class="p-3 text-gray-700 uppercase text-sm tracking-wide">Date Dispensed</th>
                                <th class="p-3 text-gray-700 uppercase text-sm text-center tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr data-record-id="1" data-patient-name="Juan Dela Cruz" data-barangay="Poblacion" data-purok="Purok 1" data-medications='[{"medication":"Ceftriaxone","brand":"Arcimet","form":"Tablet","strength":"500mg","quantity":10}]' data-total-quantity="10" data-date="2025-10-01">
                                <td class="p-3 text-sm text-gray-700 text-left">1</td>
                                <td class="p-3 text-sm text-gray-700 text-left">
                                    <div>
                                        <p class="font-semibold text-gray-700">Juan Dela Cruz</p>
                                        <p class="italic text-gray-500">Poblacion, Purok 1</p>
                                    </div>
                                </td>
                                <td class="p-3 text-sm text-gray-700 text-center">10</td>
                                <td class="p-3 text-sm text-gray-700 text-center">October 1, 2025 10:00 AM</td>
                                <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                                    <button class="view-medications-btn bg-blue-100 text-blue-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-eye mr-1"></i>View
                                    </button>
                                    <button class="editrecordbtn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                                    </button>
                                    <button class="deleterecordbtn bg-red-100 text-red-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-red-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-trash mr-1"></i>Delete
                                    </button>
                                </td>
                            </tr>
                            <tr data-record-id="2" data-patient-name="Maria Santos" data-barangay="San Pedro" data-purok="Purok 2" data-medications='[{"medication":"Amoxicillin","brand":"Pfizer","form":"Capsule","strength":"250mg","quantity":15}]' data-total-quantity="15" data-date="2025-10-05">
                                <td class="p-3 text-sm text-gray-700 text-left">2</td>
                                <td class="p-3 text-sm text-gray-700 text-left">
                                    <div>
                                        <p class="font-semibold text-gray-700">Maria Santos</p>
                                        <p class="italic text-gray-500">San Pedro, Purok 2</p>
                                    </div>
                                </td>
                                <td class="p-3 text-sm text-gray-700 text-center">15</td>
                                <td class="p-3 text-sm text-gray-700 text-center">October 5, 2025 11:30 AM</td>
                                <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                                    <button class="view-medications-btn bg-blue-100 text-blue-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-eye mr-1"></i>View
                                    </button>
                                    <button class="editrecordbtn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                                    </button>
                                    <button class="deleterecordbtn bg-red-100 text-red-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-red-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-trash mr-1"></i>Delete
                                    </button>
                                </td>
                            </tr>
                            <tr data-record-id="3" data-patient-name="Pedro Reyes" data-barangay="Santa Rosa" data-purok="Purok 3" data-medications='[{"medication":"Paracetamol","brand":"Unilab","form":"Tablet","strength":"500mg","quantity":20}]' data-total-quantity="20" data-date="2025-10-10">
                                <td class="p-3 text-sm text-gray-700 text-left">3</td>
                                <td class="p-3 text-sm text-gray-700 text-left">
                                    <div>
                                        <p class="font-semibold text-gray-700">Pedro Reyes</p>
                                        <p class="italic text-gray-500">Santa Rosa, Purok 3</p>
                                    </div>
                                </td>
                                <td class="p-3 text-sm text-gray-700 text-center">20</td>
                                <td class="p-3 text-sm text-gray-700 text-center">October 10, 2025 12:30 PM</td>
                                <td class="p-3 flex items-center justify-center gap-2 font-semibold">
                                    <button class="view-medications-btn bg-blue-100 text-blue-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-blue-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-eye mr-1"></i>View
                                    </button>
                                    <button class="editrecordbtn bg-green-100 text-green-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-green-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
                                    </button>
                                    <button class="deleterecordbtn bg-red-100 text-red-700 p-2 rounded-lg hover:-translate-y-1 hover:shadow-md transition-all duration-200 hover:bg-red-600 hover:text-white font-semibold text-sm">
                                        <i class="fa-regular fa-trash mr-1"></i>Delete
                                    </button>
                                </td>
                            </tr>
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
                    <form id="add-dispensation-form" action="#" class="mt-5">
                        <div class="flex gap-2">
                            <div class="w-1/2">
                                <label for="patient-name" class="text-sm font-semibold text-gray-600">Patient Name:</label>
                                <input type="text" name="patient-name" id="patient-name" placeholder="Enter Patient Name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                            <div class="w-1/2">
                                <label for="barangay" class="text-sm font-semibold text-gray-600">Barangay:</label>
                                <input type="text" name="barangay" id="barangay" placeholder="Enter Barangay" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label for="purok" class="text-sm font-semibold text-gray-600">Purok:</label>
                            <input type="text" name="purok" id="purok" placeholder="Enter Purok" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="mt-2" id="medication-container">
                            <div class="medication-group flex gap-2">
                                <div class="w-1/2">
                                    <label for="medication-0" class="text-sm font-semibold text-gray-600">Medication:</label>
                                    <select name="medications[0][name]" id="medication-0" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                        <option value="" disabled selected>Select Medication</option>
                                        <option value="Ceftriaxone|Arcimet|Tablet|500mg">Ceftriaxone - Arcimet (Tablet, 500mg)</option>
                                        <option value="Amoxicillin|Pfizer|Capsule|250mg">Amoxicillin - Pfizer (Capsule, 250mg)</option>
                                        <option value="Paracetamol|Unilab|Tablet|500mg">Paracetamol - Unilab (Tablet, 500mg)</option>
                                    </select>
                                </div>
                                <div class="w-1/2">
                                    <label for="quantity-0" class="text-sm font-semibold text-gray-600 mt-2">Quantity:</label>
                                    <input type="number" name="medications[0][quantity]" id="quantity-0" placeholder="Enter Quantity" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                        <button type="button" id="add-more-medication" class="bg-white-500 border border-gray-300 p-2 rounded-lg mt-3 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                            <i class="fa-regular fa-plus"></i>
                            <span>Add More</span>
                        </button>
                        <div class="mt-2">
                            <label for="date-dispensed" class="text-sm font-semibold text-gray-600">Date Dispensed:</label>
                            <input type="date" name="date-dispensed" id="date-dispensed" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                            <i class="fa-regular fa-check"></i>
                            <span>Submit</span>
                        </button>
                    </form>
                </div>
            </div>
            {{-- End Add Dispensation Modal --}}

            {{-- edit dispensation modal --}}
            <div id="editrecordmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                <div class="modal bg-white rounded-lg w-full max-w-lg p-5">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-4">
                        <p id="edit-record-title" class="text-xl font-medium text-gray-600">Edit Resident Credentials</p>
                        <button id="closeeditrecordmodal" class="p-2 rounded-full hover:bg-gray-100">
                            <i class="fa-regular fa-xmark"></i>
                        </button>
                    </div>
                    <form id="edit-dispensation-form" action="#">
                        <input type="hidden" id="edit-record-id">
                        <div class="mb-4">
                            <p class="text-sm font-medium text-gray-700">
                                <span id="edit-record-details"></span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <div class="w-1/2">
                                <label for="edit-patient-name" class="text-sm font-semibold text-gray-600">Patient Name:</label>
                                <input type="text" name="patient-name" id="edit-patient-name" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                            <div class="w-1/2">
                                <label for="edit-barangay" class="text-sm font-semibold text-gray-600">Barangay:</label>
                                <input type="text" name="barangay" id="edit-barangay" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label for="edit-purok" class="text-sm font-semibold text-gray-600">Purok:</label>
                            <input type="text" name="purok" id="edit-purok" class="mt-1 p-2 w-full border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        <button type="submit" class="bg-blue-500 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                            <i class="fa-regular fa-check"></i>
                            <span>Update</span>
                        </button>
                    </form>
                </div>
            </div>
            {{-- end edit dispensation modal --}}
        </main>
    </div>
</body>
{{-- <x-admin.loader /> --}}
</x-app-layout>
<script src="{{asset('js/patientrecords.js')}}"></script>
{{-- <script src="{{ asset('js/spa-navigation.js') }}"></script> --}}


<script>
    // Add Record
    function addRecord() {
        const adddispensationmodal = document.getElementById('adddispensationmodal');
        const closedispensationmodal = document.getElementById('closeadddispensationmodal');
        const adddispensationbtn = document.getElementById('adddispensationbtn');

        adddispensationbtn.addEventListener('click', () => {
            adddispensationmodal.classList.remove('hidden');
        });

        closedispensationmodal.addEventListener('click', () => {
            adddispensationmodal.classList.add('hidden');
        });
    }

    // Medication Actions
    function setupMedicationActions() {
        const medicationContainer = document.getElementById('medication-container');
        const addMoreButton = document.getElementById('add-more-medication');
        let medicationIndex = 1; 

        addMoreButton.addEventListener('click', () => {
            const newMedicationGroup = medicationContainer.cloneNode(true);
            newMedicationGroup.querySelector('select').id = `medication-${medicationIndex}`;
            newMedicationGroup.querySelector('select').name = `medications[${medicationIndex}][name]`;
            newMedicationGroup.querySelector('input[type="number"]').id = `quantity-${medicationIndex}`;
            newMedicationGroup.querySelector('input[type="number"]').name = `medications[${medicationIndex}][quantity]`;
            
            newMedicationGroup.querySelector('select').value = '';
            newMedicationGroup.querySelector('input[type="number"]').value = '';

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'bg-red-500 text-white p-2 rounded-lg mt-2 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit';
            removeButton.innerHTML = '<i class="fa-regular fa-trash"></i> <span>Remove</span>';
            removeButton.addEventListener('click', () => {
                newMedicationGroup.remove();
            });

            newMedicationGroup.appendChild(removeButton);
            medicationContainer.parentNode.insertBefore(newMedicationGroup, medicationContainer.nextSibling);
            medicationIndex++;
        });
    }

    // Edit Record only get the patient name and barangay and purok
    function editRecord() {
        const editrecordmodal = document.getElementById('editrecordmodal');
        const closerecordmodal = document.getElementById('closeeditrecordmodal');

        document.querySelectorAll('.editrecordbtn').forEach(button => {
            button.addEventListener('click', () => {
                editrecordmodal.classList.remove('hidden');
            }
            );
        });

        closerecordmodal.addEventListener('click', () => {
            editrecordmodal.classList.add('hidden');
        });
        
    }

    document.addEventListener('DOMContentLoaded', () => {
        addRecord();
        setupMedicationActions();
        editRecord();
    });
</script>