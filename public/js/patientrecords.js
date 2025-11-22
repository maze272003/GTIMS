document.addEventListener('DOMContentLoaded', function () {

    // ==============================================================
    // 1. GLOBAL VARIABLES & HELPERS
    // ==============================================================
    const tableContainer = document.getElementById('table-container');
    const filterModal = document.getElementById('filterModal');
    
    // Helper: Clear Validation Errors
    function clearValidation(modal) {
        const errorMessages = modal.querySelectorAll('.error-message');
        errorMessages.forEach(error => error.remove());
    }

    // Helper: AJAX Fetch Table Data (Pagination & Search)
    function fetchTableData(url) {
        if(!tableContainer) return;
        
        tableContainer.style.opacity = '0.5'; // Loading effect

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            tableContainer.innerHTML = html;
            tableContainer.style.opacity = '1';
            // Update URL without reload
            window.history.pushState(null, '', url);
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            tableContainer.style.opacity = '1';
        });
    }

    // Helper: Initialize Searchable Dropdown (For Add Modal)
    function initSearchableMedicine(group) {
        const input = group.querySelector('.search-med-input');
        const dropdown = group.querySelector('.dropdown-options');
        const hidden = group.querySelector('.med-name-hidden');
        const options = dropdown.querySelectorAll('.option');

        // Show on focus
        input.addEventListener('focus', () => dropdown.classList.remove('hidden'));

        // Filter logic
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

            if (visibleCount > 0 || term === '') {
                dropdown.classList.remove('hidden');
            } else {
                dropdown.classList.add('hidden');
            }

            if (term === '') hidden.value = '';
        });

        // Select option
        options.forEach(opt => {
            opt.addEventListener('click', () => {
                input.value = opt.dataset.label;
                hidden.value = opt.dataset.id;
                dropdown.classList.add('hidden');
            });
        });

        // Hide on click outside (handled globally below in Delegation)
    }


    // ==============================================================
    // 2. MAIN EVENT DELEGATION (HANDLE ALL CLICKS HERE)
    // ==============================================================
    document.addEventListener('click', function (e) {

        // --- A. PAGINATION LINKS ---
        const paginationLink = e.target.closest('.pagination-links a');
        if (paginationLink && tableContainer.contains(paginationLink)) {
            e.preventDefault();
            fetchTableData(paginationLink.getAttribute('href'));
            return;
        }

        // --- B. VIEW MEDICATIONS MODAL ---
        const viewBtn = e.target.closest('.view-medications-btn');
        if (viewBtn) {
            const row = viewBtn.closest('tr');
            const name = row.dataset.patientName;
            const medications = JSON.parse(row.dataset.medications || '[]');
            
            const modal = document.getElementById('viewmedicationsmodal');
            const tbody = document.getElementById('view-medications-tbody');
            const title = document.getElementById('view-med-title');
            
            // User Level Logic
            // Siguraduhing may default value kung undefined para di mag error
            const userLevel = (window.currentUserLevel !== undefined) ? window.currentUserLevel : 0; 

            title.innerHTML = `Medications for <span class="text-red-700 capitalize italic">${name}</span>`;
            tbody.innerHTML = '';

            if (medications.length > 0) {
                medications.forEach(med => {
                    let actionCell = '';
                    
                    // Only show Edit button if NOT Level 4
                    if (userLevel != 4) {
                        actionCell = `
                        <td class="p-3 text-center">
                            <button class="edit-med-item bg-green-100 text-green-700 p-1.5 rounded hover:bg-green-600 hover:text-white transition-all text-xs">
                                <i class="fa-regular fa-pen-to-square"></i> Edit
                            </button>
                        </td>`;
                    } else {
                        actionCell = `
                        <td class="p-3 text-center">
                            <div class="relative inline-flex justify-center group">
                                <span class="text-gray-400 cursor-help"><i class="fa-regular fa-lock text-sm"></i></span>
                            </div>
                        </td>`;
                    }

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="p-3 text-sm text-gray-700">${med.batch || 'N/A'}</td>
                        <td class="p-3 text-sm text-gray-700 font-medium">
                            <div>
                                <p class="font-semibold text-gray-700">${med.medication}</p>
                                <p class="italic text-gray-500">${med.brand}</p>
                            </div>
                        </td>
                        <td class="p-3 text-sm text-gray-700">${med.form}, ${med.strength}</td>
                        <td class="p-3 text-sm text-gray-700 text-center font-semibold">${med.quantity}</td>
                        ${actionCell}
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-500">No medications recorded.</td></tr>';
            }

            modal.classList.remove('hidden');
            return;
        }

        // --- C. EDIT RECORD MODAL ---
        const editBtn = e.target.closest('.editrecordbtn');
        if (editBtn) {
            const row = editBtn.closest('tr');
            const modal = document.getElementById('editrecordmodal');

            // Populate Fields
            document.getElementById('edit-record-id').value = row.dataset.recordId;
            document.getElementById('edit-patient-name').value = row.dataset.patientName;
            document.getElementById('edit-purok').value = row.dataset.purok;
            document.getElementById('edit-category').value = row.dataset.category;
            document.getElementById('edit-date-dispensed').value = row.dataset.dateDispensed;
            
            const barangaySelect = document.getElementById('edit-barangay_id');
            if(barangaySelect) barangaySelect.value = row.dataset.barangayId;

            document.getElementById('edit-record-title').textContent = `Edit #${row.dataset.recordId} – ${row.dataset.patientName}`;
            
            modal.classList.remove('hidden');
            return;
        }

        // --- D. ADD RECORD MODAL TRIGGER ---
        const addBtn = e.target.closest('#adddispensationbtn');
        if (addBtn) {
            document.getElementById('adddispensationmodal').classList.remove('hidden');
            return;
        }

        // --- E. FILTER MODAL TRIGGER ---
        const filterBtn = e.target.closest('#openFilterModal');
        if (filterBtn) {
            filterModal.classList.remove('hidden');
            return;
        }

        // --- F. CLOSE MODALS (Buttons & Backdrops) ---
        // Close buttons
        if (e.target.closest('#closeadddispensationmodal')) {
            const m = document.getElementById('adddispensationmodal');
            m.classList.add('hidden');
            clearValidation(m);
        }
        if (e.target.closest('#closeeditrecordmodal')) {
            const m = document.getElementById('editrecordmodal');
            m.classList.add('hidden');
            clearValidation(m);
        }
        if (e.target.closest('#closeviewmedmodal')) {
            document.getElementById('viewmedicationsmodal').classList.add('hidden');
        }
        if (e.target.closest('#closeFilterModal')) {
            filterModal.classList.add('hidden');
        }

        // Close on Backdrop Click
        if (e.target.id === 'adddispensationmodal') {
            e.target.classList.add('hidden');
            clearValidation(e.target);
        }
        if (e.target.id === 'editrecordmodal') {
            e.target.classList.add('hidden');
            clearValidation(e.target);
        }
        if (e.target.id === 'viewmedicationsmodal') {
            e.target.classList.add('hidden');
        }
        if (e.target.id === 'filterModal') {
            e.target.classList.add('hidden');
        }

        // --- G. DROPDOWN CLOSE (Click Outside) ---
        // Kung nag click sa labas ng .medication-group, isara ang dropdowns
        if (!e.target.closest('.medication-group')) {
            document.querySelectorAll('.dropdown-options').forEach(el => el.classList.add('hidden'));
        }
    });


    // ==============================================================
    // 3. STATIC INITIALIZATIONS (Things that don't change on AJAX)
    // ==============================================================

    // --- Init First Row Searchable in Add Modal ---
    const firstGroup = document.querySelector('.medication-group');
    if (firstGroup) initSearchableMedicine(firstGroup);

    // --- Add More Medication Logic ---
    const addMoreBtn = document.getElementById('add-more-medication');
    const medContainer = document.getElementById('medication-container');
    let medIndex = 1;

    if (addMoreBtn && medContainer) {
        addMoreBtn.addEventListener('click', () => {
            const template = medContainer.querySelector('.medication-group');
            // Clone the node
            const clone = template.cloneNode(true);
            
            // Clean up clone values
            const searchInput = clone.querySelector('.search-med-input');
            const hiddenInput = clone.querySelector('.med-name-hidden');
            const qtyInput = clone.querySelector('input[type="number"]');
            
            searchInput.value = '';
            hiddenInput.value = '';
            qtyInput.value = '';
            
            // Fix Names for Laravel Array Validation
            // Note: Assuming your first row is medications[0], we increment index
            // Adjust name attributes based on your controller needs
            hiddenInput.name = `medications[${medIndex}][name]`;
            qtyInput.name = `medications[${medIndex}][quantity]`;

            // Add Remove Button
            // Check if remove button already exists (in case template had one)
            let removeBtn = clone.querySelector('.remove-med-btn');
            if (!removeBtn) {
                removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-med-btn bg-red-500 text-white p-2 rounded-lg mt-2 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit text-sm ml-2';
                removeBtn.innerHTML = '<i class="fa-regular fa-trash mr-1"></i> Remove';
                clone.appendChild(removeBtn);
            }

            // Append
            medContainer.appendChild(clone);
            
            // Activate Search Logic for New Row
            initSearchableMedicine(clone);
            medIndex++;
        });

        // Event Delegation for Remove Button inside Container
        medContainer.addEventListener('click', (e) => {
            if (e.target.closest('.remove-med-btn')) {
                const group = e.target.closest('.medication-group');
                // Don't remove the very first row if it's the only one (optional guard)
                if(medContainer.querySelectorAll('.medication-group').length > 1) {
                    group.remove();
                } else {
                    // Optional: Just clear values if it's the last one
                     group.querySelector('.search-med-input').value = '';
                     group.querySelector('.med-name-hidden').value = '';
                     group.querySelector('input[type="number"]').value = '';
                }
            }
        });
    }

    // --- Edit Form SweetAlert Submission ---
    const updateBtn = document.getElementById('update-dispensation-btn');
    const editForm = document.getElementById('edit-dispensation-form');

    if (updateBtn && editForm) {
        updateBtn.addEventListener('click', () => {
            const patientName = document.getElementById('edit-patient-name').value.trim();
            const barangayId = document.getElementById('edit-barangay_id').value;
            const purok = document.getElementById('edit-purok').value.trim();
            const category = document.getElementById('edit-category').value;
            const dateDispensed = document.getElementById('edit-date-dispensed').value;

            if (!patientName || !barangayId || !purok || !category || !dateDispensed) {
                Swal.fire({
                    title: 'Incomplete Data',
                    text: 'Please fill in all required fields.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: "This action can't be undone.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing...',
                        didOpen: () => Swal.showLoading()
                    });
                    editForm.submit();
                }
            });
        });
    }

    // --- Clear Filters Logic ---
    const clearFilterBtn = document.getElementById('clearFilters');
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', () => {
            const baseUrl = new URL(window.location.href);
            const params = baseUrl.searchParams;
            ['from_date', 'to_date', 'category', 'barangay_id', 'page'].forEach(p => params.delete(p));
            if (!params.has('branch_filter')) params.delete('page');
            window.location.href = baseUrl.toString();
        });
    }

    // --- Search Input Debounce ---
    const searchInput = document.getElementById('patientrecords-search-input');
    let debounceTimer;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = this.value;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('search', query); // Make sure Controller handles 'search'
                currentUrl.searchParams.set('page', 1);
                fetchTableData(currentUrl.toString());
            }, 500);
        });
    }

});