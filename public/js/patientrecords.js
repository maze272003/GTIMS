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
   2. ADD/REMOVE MEDICATION ROWS
   ======================================== */
function setupMedicationActions() {
    const container = document.getElementById('medication-container');
    const addBtn = document.getElementById('add-more-medication');
    let index = 1;

    addBtn.addEventListener('click', () => {
        const template = container.querySelector('.medication-group');
        const clone = template.cloneNode(true);

        clone.querySelector('select').value = '';
        clone.querySelector('input[type="number"]').value = '';

        const select = clone.querySelector('select');
        const input = clone.querySelector('input[type="number"]');
        select.id = `medication-${index}`;
        select.name = `medications[${index}][name]`;
        input.id = `quantity-${index}`;
        input.name = `medications[${index}][quantity]`;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'bg-red-500 text-white p-2 rounded-lg mt-2 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit text-sm';
        removeBtn.innerHTML = '<i class="fa-regular fa-trash mr-1"></i> Remove';
        removeBtn.addEventListener('click', () => clone.remove());
        clone.appendChild(removeBtn);

        container.appendChild(clone);
        index++;
    });
}

/* ========================================
   3. EDIT RECORD
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

            document.getElementById('edit-record-id').value = id;
            document.getElementById('edit-patient-name').value = name;
            document.getElementById('edit-barangay_id').value = barangayId;
            document.getElementById('edit-purok').value = purok;
            document.getElementById('edit-category').value = category;

            document.getElementById('edit-record-title').textContent = `Edit #${id} – ${name}`;

            // Set form action dynamically (assuming route name for update)
            form.action = `/admin/patientrecords/${id}`; // Adjust to your update route, e.g., `{{ route('admin.patientrecords.update', '') }}/${id}` if using Blade

            modal.classList.remove('hidden');
        });
    });

    // Handle form submission (optional: add AJAX or let it submit normally)
    form.addEventListener('submit', (e) => {
        // If you want to handle via JS/AJAX, prevent default and submit here
        // e.preventDefault();
        // ... submit logic
    });
}

/* ========================================
   4. VIEW MEDICATIONS MODAL
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

            medications.forEach(med => {
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
                    <td class="p-3 text-center">
                        <button class="edit-med-item bg-green-100 text-green-700 p-1.5 rounded hover:bg-green-600 hover:text-white transition-all text-xs">
                            <i class="fa-regular fa-pen-to-square"></i> Edit
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            modal.classList.remove('hidden');
        });
    });
}

/* ========================================
   5. INIT
   ======================================== */
document.addEventListener('DOMContentLoaded', () => {
    addRecord();
    setupMedicationActions();
    editRecord();
    viewMedications();
});