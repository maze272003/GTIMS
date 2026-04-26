document.addEventListener('DOMContentLoaded', function () {

    // ==============================================================
    // 1. VARIABLES & SELECTORS
    // ==============================================================
    const filterModal = document.getElementById('filterModal');
    
    // ==============================================================
    // 2. HELPER FUNCTIONS
    // ==============================================================

    // Clear Validation Errors in Modals
    function clearValidation(modal) {
        if (!modal) return;
        const errorMessages = modal.querySelectorAll('.error-message');
        errorMessages.forEach(error => error.remove());
    }

    // Initialize Searchable Dropdown (Medication Search)
    function initSearchableMedicine(group) {
        const input = group.querySelector('.search-med-input');
        const dropdown = group.querySelector('.dropdown-options');
        const hidden = group.querySelector('.med-name-hidden');
        const options = dropdown.querySelectorAll('.option');

        // Toggle dropdown on focus
        input.addEventListener('focus', () => dropdown.classList.remove('hidden'));
        
        // Hide dropdown when clicking outside (Specific to this instance)
        document.addEventListener('click', (e) => {
            if (!group.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Filter Options
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

            if (visibleCount > 0) dropdown.classList.remove('hidden');
            else dropdown.classList.add('hidden');

            // Reset hidden ID if input is cleared
            if (term === '') hidden.value = '';
        });

        // Option Selection
        options.forEach(opt => {
            opt.addEventListener('click', () => {
                input.value = opt.dataset.label; // Show readable name
                hidden.value = opt.dataset.id;   // Store ID
                dropdown.classList.add('hidden');
            });
        });
    }

    // ==============================================================
    // 3. EVENT LISTENERS (INPUTS & FORMS)
    // ==============================================================

    // Search Input Listener
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            const url = buildUrl(); // Rebuild URL with new search term
            fetchTableData(url);
        }, 500));
    }

    // Filter Modal: Apply Button
    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const url = buildUrl();
            fetchTableData(url);
            document.getElementById('filterModal').classList.add('hidden');
        });
    }

    // Filter Modal: Clear Button
    const clearFilterBtn = document.getElementById('clearFilters');
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', () => {
            if(filterForm) filterForm.reset();
            const url = buildUrl(); // Rebuilds URL without form data
            fetchTableData(url);
        });
    }

    // ==============================================================
    // 4. MAIN CLICK DELEGATION (Modals, Pagination, Dynamic Actions)
    // ==============================================================
    document.addEventListener('click', function (e) {
        const target = e.target;

        // --- A. VIEW MEDICATIONS MODAL ---
        const viewBtn = e.target.closest('.view-medications-btn');
        if (viewBtn) {
            const row = viewBtn.closest('tr');
            const name = row.dataset.patientName;
            // Parse JSON safely
            let medications = [];
            try {
                medications = JSON.parse(row.dataset.medications);
            } catch (err) { console.error('JSON Parse error', err); }
            
            const tbody = document.getElementById('view-medications-tbody');
            const title = document.getElementById('view-med-title');
            
            title.innerHTML = `Medications for <span class="text-red-700 capitalize italic">${name}</span>`;
            tbody.innerHTML = '';

            if (medications.length > 0) {
                medications.forEach(med => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="p-3 text-sm text-gray-700">${med.batch || 'N/A'}</td>
                        <td class="p-3 text-sm text-gray-700">
                            <div>
                                <p class="font-semibold text-gray-700">${med.medication}</p>
                                <p class="italic text-gray-500">${med.brand}</p>
                            </div>
                        </td>
                        <td class="p-3 text-sm text-gray-700">${med.form}, ${med.strength}</td>
                        <td class="p-3 text-sm text-gray-700 text-center font-semibold">${med.quantity}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-gray-500">No medications recorded.</td></tr>';
            }

            document.getElementById('viewmedicationsmodal').classList.remove('hidden');
            return;
        }

        // --- B. EDIT RECORD MODAL ---
        const editBtn = e.target.closest('.editrecordbtn');
        if (editBtn) {
            const row = editBtn.closest('tr');
            const d = row.dataset;

            document.getElementById('edit-record-id').value = d.recordId;
            document.getElementById('edit-patient-name').value = d.patientName;
            document.getElementById('edit-purok').value = d.purok;
            document.getElementById('edit-category').value = d.category;
            document.getElementById('edit-date-dispensed').value = d.dateDispensed;
            
            const barangaySelect = document.getElementById('edit-barangay_id');
            if(barangaySelect) barangaySelect.value = d.barangayId;

            document.getElementById('edit-record-title').textContent = `Edit #${row.dataset.recordId} - ${row.dataset.patientName}`;
            
            document.getElementById('editrecordmodal').classList.remove('hidden');
            return;
        }

        // --- C. ADD RECORD MODAL TRIGGER ---
        const addBtn = e.target.closest('#adddispensationbtn');
        if (addBtn) {
            document.getElementById('adddispensationmodal').classList.remove('hidden');
            return;
        }

        // --- D. FILTER MODAL TRIGGER ---
        const filterBtn = e.target.closest('#openFilterModal');
        if (filterBtn) {
            filterModal.classList.remove('hidden');
            return;
        }

        // --- E. CLOSE MODALS ---
        if (e.target.closest('#closeadddispensationmodal')) {
            const m = document.getElementById('adddispensationmodal');
            m.classList.add('hidden');
            clearValidation(m);
        }
        if (target.closest('#closeeditrecordmodal')) {
            document.getElementById('editrecordmodal').classList.add('hidden');
            clearValidation(document.getElementById('editrecordmodal'));
        }
        if (target.closest('#closeviewmedmodal')) {
            document.getElementById('viewmedicationsmodal').classList.add('hidden');
        }
        if (target.closest('#closeFilterModal')) {
            document.getElementById('filterModal').classList.add('hidden');
        }

        // F. BACKDROP CLICK CLOSE
        if (target.id === 'adddispensationmodal') {
            target.classList.add('hidden');
            clearValidation(target);
        }
        if (target.id === 'editrecordmodal') {
            target.classList.add('hidden');
            clearValidation(target);
        }
        if (target.id === 'viewmedicationsmodal') {
            target.classList.add('hidden');
        }
        if (target.id === 'filterModal') {
            target.classList.add('hidden');
        }
    });


    // ==============================================================
    // 5. DYNAMIC MEDICATION ROWS (Add/Remove)
    // ==============================================================
    const medContainer = document.getElementById('medication-container');
    const addMoreBtn = document.getElementById('add-more-medication');
    
    // Initialize the first static row
    const firstGroup = document.querySelector('.medication-group');
    if (firstGroup) initSearchableMedicine(firstGroup);

    let medIndex = 1;

    if (addMoreBtn && medContainer) {
        addMoreBtn.addEventListener('click', () => {
            const template = medContainer.querySelector('.medication-group');
            // Clone the node
            const clone = template.cloneNode(true);

            // Clear values in the clone
            const input = clone.querySelector('.search-med-input');
            const hidden = clone.querySelector('.med-name-hidden');
            const qty = clone.querySelector('input[type="number"]');

            input.value = '';
            hidden.value = '';
            qty.value = '';

            // Update attributes for Laravel Array Validation
            hidden.name = `medications[${medIndex}][name]`;
            qty.name = `medications[${medIndex}][quantity]`;

            // Add Remove Button if it doesn't exist
            if (!clone.querySelector('.remove-med-btn')) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-med-btn bg-red-500 text-white p-2 rounded-lg mt-2 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit text-sm ml-auto block';
                removeBtn.innerHTML = '<i class="fa-regular fa-trash mr-1"></i> Remove';
                clone.querySelector('.w-28').appendChild(removeBtn); // Append below qty or adjust placement
            }

            // Append to container
            medContainer.appendChild(clone);
            
            // Re-initialize the search logic for this specific new row
            initSearchableMedicine(clone);
            medIndex++;
        });

        // Event delegation for Remove button
        medContainer.addEventListener('click', (e) => {
            if (e.target.closest('.remove-med-btn')) {
                const group = e.target.closest('.medication-group');
                // Ensure at least one row remains
                if(medContainer.querySelectorAll('.medication-group').length > 1) {
                    group.remove();
                } else {
                    // Just clear inputs if it's the last one
                    group.querySelector('.search-med-input').value = '';
                    group.querySelector('.med-name-hidden').value = '';
                    group.querySelector('input[type="number"]').value = '';
                }
            }
        });
    }


    // ==============================================================
    // 6. SWEETALERT SUBMISSIONS
    // ==============================================================

    // --- ADD DISPENSATION ---
    const addBtn = document.getElementById('add-dispensation-btn');
    const addForm = document.getElementById('add-dispensation-form');

    if (addBtn && addForm) {
        addBtn.addEventListener('click', function() {
            const inputs = addForm.querySelectorAll('input:not([type="hidden"]), select');
            let allFilled = true;

            inputs.forEach(input => {
                // Simple validation: check if empty
                if (input.value.trim() === '') allFilled = false;
            });

            // Check if hidden medication IDs are filled
            const medIds = addForm.querySelectorAll('.med-name-hidden');
            medIds.forEach(id => { if(id.value === '') allFilled = false; });

            if (!allFilled) {
                Swal.fire({
                    title: 'Incomplete Form',
                    text: 'Please fill in all required fields (including Medicine selection).',
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
                text: "Please confirm if you want to proceed.",
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
                        text: "Saving record...",
                        allowOutsideClick: false,
                        customClass: {
                            container: 'swal-container',
                            popup: 'swal-popup',
                            title: 'swal-title',
                            htmlContainer: 'swal-content',
                            cancelButton: 'swal-cancel-button',
                            icon: 'swal-icon'
                        },
                        didOpen: () => Swal.showLoading()
                    });
                    addForm.submit();
                }
            });
        });
    }
    // Search Debounce (GET query persistence)
    const searchInput = document.getElementById('patientrecords-search-input');
    const searchForm = document.getElementById('patientrecords-search-form');
    const searchPageInput = document.getElementById('patientrecords-search-page');
    let debounceTimer;
    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (searchPageInput) {
                    searchPageInput.value = '1';
                }
                searchForm.submit();
            }, 500);
        });
    }

});

// add dispensation sweet alert 

const addDispensationBtn = document.getElementById('add-dispensation-btn');
if (addDispensationBtn) {
    addDispensationBtn.addEventListener('click', function () {
        const form = document.getElementById('add-dispensation-form');
        if (!form) {
            return;
        }

        const inputs = form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], select');
        let allFilled = true;

        inputs.forEach(input => {
            if (input.value.trim() === '') {
                allFilled = false;
            }
        });

        if (!allFilled) {
            Swal.fire({
                title: 'Incomplete Form',
                text: 'Please fill in all required fields before submitting.',
                icon: 'warning',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
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
            text: "Please confirm if you want to proceed.",
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
