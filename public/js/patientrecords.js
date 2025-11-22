function clearValidation(modal) {
    const errorMessages = modal.querySelectorAll('.error-message');
    errorMessages.forEach(error => error.remove());
}

/* ========================================
   1. ADD RECORD MODAL
   ======================================== */
function addRecord() {
    const modal = document.getElementById('adddispensationmodal');
    const close = document.getElementById('closeadddispensationmodal');
    const btn = document.getElementById('adddispensationbtn');

    btn.addEventListener('click', () => modal.classList.remove('hidden'));
    close.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
        clearValidation(modal);
    });
}

/* ========================================
   2. SEARCHABLE MEDICINE DROPDOWN INIT
   ======================================== */
function initSearchableMedicine(group) {
    const input = group.querySelector('.search-med-input');
    const dropdown = group.querySelector('.dropdown-options');
    const hidden = group.querySelector('.med-name-hidden');
    const options = dropdown.querySelectorAll('.option');

    // Show dropdown on focus
    input.addEventListener('focus', () => {
        dropdown.classList.remove('hidden');
    });

    // Filter options on input
    input.addEventListener('input', () => {
        const term = input.value.toLowerCase().trim();
        let visibleCount = 0;

        options.forEach(opt => {
            const label = opt.dataset.label.toLowerCase();
            if (label.includes(term) || term === '') {
                opt.style.display = '';
                visibleCount++;
            } else {
                opt.style.display = 'none';
            }
        });

        // Show dropdown if there's a match or empty
        if (visibleCount > 0 || term === '') {
            dropdown.classList.remove('hidden');
        } else {
            dropdown.classList.add('hidden');
        }

        // Clear hidden if no exact match
        if (term === '') {
            hidden.value = '';
        }
    });

    // Select option on click
    options.forEach(opt => {
        opt.addEventListener('click', () => {
            input.value = opt.dataset.label;
            hidden.value = opt.dataset.id;
            dropdown.classList.add('hidden');
            input.blur(); // Optional: lose focus after select
        });
    });

    // Hide dropdown on click outside
    document.addEventListener('click', (e) => {
        if (!group.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

/* ========================================
   3. ADD/REMOVE MEDICATION ROWS
   ======================================== */
function setupMedicationActions() {
    const container = document.getElementById('medication-container');
    const addBtn = document.getElementById('add-more-medication');
    let index = 1;

    // Init first row
    const firstGroup = container.querySelector('.medication-group');
    if (firstGroup) {
        initSearchableMedicine(firstGroup);
    }

    addBtn.addEventListener('click', () => {
        const template = container.querySelector('.medication-group');
        const clone = template.cloneNode(true);

        // Clear values in clone
        const searchInput = clone.querySelector('.search-med-input');
        const hiddenInput = clone.querySelector('.med-name-hidden');
        const qtyInput = clone.querySelector('input[type="number"]');
        searchInput.value = '';
        hiddenInput.value = '';
        qtyInput.value = '';

        // Update names and ids for clone
        searchInput.name = `medications[${index}][name_display]`; // Display name (not submitted, just for UI)
        hiddenInput.name = `medications[${index}][name]`;
        qtyInput.name = `medications[${index}][quantity]`;
        qtyInput.id = `quantity-${index}`;

        // Add remove button
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'bg-red-500 text-white p-2 rounded-lg mt-2 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit text-sm ml-2';
        removeBtn.innerHTML = '<i class="fa-regular fa-trash mr-1"></i> Remove';
        removeBtn.addEventListener('click', () => clone.remove());
        clone.appendChild(removeBtn);

        // Append and init searchable
        container.appendChild(clone);
        initSearchableMedicine(clone);
        index++;
    });
}

/* ========================================
   4. EDIT RECORD
   ======================================== */
function editRecord() {
    const modal = document.getElementById('editrecordmodal');
    const closeBtn = document.getElementById('closeeditrecordmodal');

    closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
        clearValidation(modal);
    });

    const form = document.getElementById('edit-dispensation-form');

    document.querySelectorAll('.editrecordbtn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            if (!row) return;

            const id = row.dataset.recordId;
            const name = row.dataset.patientName;
            const barangayId = row.dataset.barangayId;
            const purok = row.dataset.purok;
            const category = row.dataset.category;
            const dateDispensed = row.dataset.dateDispensed;

            document.getElementById('edit-record-id').value = id;
            document.getElementById('edit-patient-name').value = name;
            document.getElementById('edit-barangay_id').value = barangayId;
            document.getElementById('edit-purok').value = purok;
            document.getElementById('edit-category').value = category;
            document.getElementById('edit-date-dispensed').value = dateDispensed;

            document.getElementById('edit-record-title').textContent = `Edit #${id} – ${name}`;

            modal.classList.remove('hidden');
        });
    });

    // sweet alert before submitting the edit form
    const updateBtn = document.getElementById('update-dispensation-btn');
    updateBtn.addEventListener('click', (e) => {

        // if input has no data, do not proceed
        const patientName = document.getElementById('edit-patient-name').value.trim();
        const barangayId = document.getElementById('edit-barangay_id').value;
        const purok = document.getElementById('edit-purok').value.trim();
        const category = document.getElementById('edit-category').value;
        const dateDispensed = document.getElementById('edit-date-dispensed').value;

        if (patientName === '' || barangayId === '' || purok === '' || category === '' || dateDispensed === '') {
            Swal.fire({
                title: 'Incomplete Data',
                text: 'Please fill in all required fields before updating the record.',
                icon: 'warning',
                confirmButtonText: 'OK',
                customClass: {
                    container: 'swal-container',
                    popup: 'swal-popup',
                    title: 'swal-title',
                    htmlContainer: 'swal-content',
                    confirmButton: 'swal-confirm-button',
                    icon: 'swal-icon'
                }
            });
            return;
        }
        Swal.fire({
            title: 'Are you sure?',
            text: "This action can't be undone. Please confirm if you want to proceed.",
            icon: 'info',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            confirmButtonText: 'Confirm',
            allowOutsideClick: false,
            customClass: {
                container: 'swal-container',
                popup: 'swal-popup',
                title: 'swal-title',
                htmlContainer: 'swal-content',
                confirmButton: 'swal-confirm-button',
                cancelButton: 'swal-cancel-button',
                icon: 'swal-icon'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing...',
                    text: "Please wait, your request is being processed.",
                    allowOutsideClick: false,
                    customClass: {
                        container: 'swal-container',
                        popup: 'swal-popup',
                        title: 'swal-title',
                        htmlContainer: 'swal-content',
                        cancelButton: 'swal-cancel-button',
                        icon: 'swal-icon'
                    },
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                form.submit();
            }
        });
    });
}
/* ========================================
   4. VIEW MEDICATIONS MODAL - UPDATED
   ======================================== */
function viewMedications() {
    const modal = document.getElementById('viewmedicationsmodal');
    const closeBtn = document.getElementById('closeviewmedmodal');
    const tbody = document.getElementById('view-medications-tbody');
    const title = document.getElementById('view-med-title');

    closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    document.querySelectorAll('.view-medications-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const name = row.dataset.patientName;
            const medications = JSON.parse(row.dataset.medications || '[]');

            title.innerHTML = `Medications for <span class="text-red-700 capitalize italic">${name}</span>`;
            tbody.innerHTML = '';

            // GET USER LEVEL - ITO ANG IMPORTANTE
            const userLevel = window.currentUserLevel; // Kunin mula sa global variable
            console.log('Current User Level:', userLevel); // Para ma-debug

            medications.forEach(med => {
                const tr = document.createElement('tr');
                
                // BUILD THE ROW - HIDE BUTTON FOR LEVEL 4
                let rowHTML = `
                    <td class="p-3 text-sm text-gray-700">${med.batch || 'N/A'}</td>
                    <td class="p-3 text-sm text-gray-700 font-medium">
                        <div>
                            <p class="font-semibold text-gray-700">${med.medication}</p>
                            <p class="italic text-gray-500">${med.brand}</p>
                        </div>
                    </td>
                    <td class="p-3 text-sm text-gray-700">${med.form}, ${med.strength}</td>
                    <td class="p-3 text-sm text-gray-700 text-center font-semibold">${med.quantity}</td>
                `;

                // ADD BUTTON COLUMN ONLY IF NOT LEVEL 4
                if (userLevel != 4) {
                    rowHTML += `
                    <td class="p-3 text-center">
                        <button class="edit-med-item bg-green-100 text-green-700 p-1.5 rounded hover:bg-green-600 hover:text-white transition-all text-xs">
                            <i class="fa-regular fa-pen-to-square"></i> Edit
                        </button>
                    </td>
                    `;
                } else {
                rowHTML += `
                <td class="p-3 text-center">
                    <div class="relative inline-flex justify-center group">
                        <span class="text-gray-400 cursor-help transition-colors duration-200 hover:text-gray-600">
                            <i class="fa-regular fa-lock text-sm"></i>
                        </span>
                        
                        <!-- Tooltip - positioned to left -->
                        <div class="absolute right-full top-1/2 transform -translate-y-1/2 mr-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50">
                            <div class="bg-gray-800 text-white text-xs rounded py-1.5 px-3 whitespace-nowrap shadow-lg">
                                Only admins can use this action
                                <div class="absolute top-1/2 left-full transform -translate-y-1/2 border-4 border-transparent border-l-gray-800"></div>
                            </div>
                        </div>
                    </div>
                </td>
                `;
            }

                tr.innerHTML = rowHTML;
                tbody.appendChild(tr);
            });

            modal.classList.remove('hidden');
        });
    });
}

const filterModal = document.getElementById('filterModal');
const openBtn = document.getElementById('openFilterModal');
const closeBtn = document.getElementById('closeFilterModal');
const clearBtn = document.getElementById('clearFilters');
const form = document.getElementById('filterForm');

// Open modal
openBtn?.addEventListener('click', () => {
    filterModal.classList.remove('hidden');
});

// Close modal
closeBtn?.addEventListener('click', () => {
    filterModal.classList.add('hidden');
});

// Close when clicking outside
filterModal?.addEventListener('click', (e) => {
    if (e.target === filterModal) {
        filterModal.classList.add('hidden');
    }
});

// Clear all filters and redirect to clean URL
clearBtn?.addEventListener('click', () => {
    const baseUrl = new URL(window.location.href);
    const params = baseUrl.searchParams;

    // Remove all filter params except branch_filter (for admin)
    ['from_date', 'to_date', 'category', 'barangay_id', 'page'].forEach(param => {
        params.delete(param);
    });

    // If no filters left, remove page too
    if (!params.has('branch_filter')) {
        params.delete('page');
    }

    window.location.href = baseUrl.toString();
});

// Optional: Allow ESC key to close
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !filterModal.classList.contains('hidden')) {
        filterModal.classList.add('hidden');
    }
});

/* ========================================
   6. INIT
   ======================================== */
document.addEventListener('DOMContentLoaded', () => {
    addRecord();
    setupMedicationActions();
    editRecord();
    viewMedications();
});