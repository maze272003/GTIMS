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