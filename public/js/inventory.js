// clear validation if close the modal
function clearValidation(modal) {
    const errorMessages = modal.querySelectorAll('.error-message');
    errorMessages.forEach(error => error.remove());
}

// Add New Product Modal

function showAddNewProductModal() {
    const modal = document.getElementById('addnewproductmodal');
    const btn = document.getElementById('addnewproductbtn');
    const closeBtn = document.getElementById('closeaddnewproductmodal');

    btn.addEventListener('click', () => modal.classList.remove('hidden'));
    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden')
        clearValidation(modal);
    });
    modal.addEventListener('click', (e) => { 
        if (e.target === modal) modal.classList.add('hidden'); 
        clearValidation(modal);
    });
}

// View All Products Modal
function showViewAllProductsModal() {
    const modal = document.getElementById('viewallproductsmodal');
    const btn = document.getElementById('viewallproductsbtn');
    const closeBtn = document.getElementById('closeviewallproductsmodal');

    btn.addEventListener('click', () => modal.classList.remove('hidden'));
    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        clearValidation(modal);
    });
    modal.addEventListener('click', (e) => { 
        if (e.target === modal) modal.classList.add('hidden');
        clearValidation(modal);
    });
}

// Add Stock Modal
function showAddStockModal() {
    const modal = document.getElementById('addstockmodal');
    const closeBtn = document.getElementById('closeaddstockmodal');
    const title = document.getElementById('add-stock-title');
    const productIdInput = document.getElementById('selected-product-id');

document.querySelectorAll('.add-stock-btn').forEach(button => {
    button.addEventListener('click', function () {
    const row = this.closest('tr');
    const productName = `${row.dataset.product} ${row.dataset.strength} ${row.dataset.form}`;
    const productId = row.dataset.productId;

    title.textContent = `Add Stock - ${productName}`;
    productIdInput.value = productId;

    modal.classList.remove('hidden');
    });
});

closeBtn.addEventListener('click', () => {
    modal.classList.add('hidden');
    clearValidation(modal);
});
modal.addEventListener('click', (e) => { 
    if (e.target === modal) modal.classList.add('hidden'); 
    clearValidation(modal);
});
}

// Edit Product Modal
function showEditProductModal() {
    const modal = document.getElementById('editproductmodal');
    const closeBtn = document.getElementById('closeeditproductmodal');
    const brandInput = document.getElementById('edit-brand');
    const productInput = document.getElementById('edit-product');
    const formInput = document.getElementById('edit-form');
    const strengthInput = document.getElementById('edit-strength');
    const productIdInput = document.getElementById('edit-product-id');

    document.querySelectorAll('.edit-product-btn').forEach(button => {
        button.addEventListener('click', function () {
            const row = this.closest('tr');
            const productId = row.dataset.productId;

            productIdInput.value = productIdInput.value || productId || '';
            brandInput.value = brandInput.value || row.dataset.brand || '';
            productInput.value = productInput.value || row.dataset.product || '';
            formInput.value = formInput.value || row.dataset.form || '';
            strengthInput.value = strengthInput.value || row.dataset.strength || '';

            modal.classList.remove('hidden');
        });
    });

    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        clearValidation(modal);
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
        clearValidation(modal);
    });
}

// Ensure the function is called when the DOM is loaded
document.addEventListener('DOMContentLoaded', showEditProductModal);

// Edit Stock Modal
function showEditStockModal() {
    const modal = document.getElementById('editstockmodal');
    const closeBtn = document.getElementById('closeeditstockmodal');
    const title = document.getElementById('edit-stock-title');
    const productDisplay = document.getElementById('edit-stock-product');
    const stockIdInput = document.getElementById('edit-stock-id');
    const batchInput = document.getElementById('edit-batchnumber');
    const quantityInput = document.getElementById('edit-quantity');
    const expiryInput = document.getElementById('edit-expiry');

document.querySelectorAll('.edit-stock-btn').forEach(button => {
    button.addEventListener('click', function () {
        const row = this.closest('tr');
        const productName = `${row.dataset.product} ${row.dataset.strength} ${row.dataset.form} (${row.dataset.brand})`;
        const batch = row.dataset.batch;
        const quantity = row.dataset.quantity;
        const expiry = row.dataset.expiry;
        const stockId = row.dataset.stockId;

        if (!stockId) {
            console.error('Inventory ID is undefined');
            return;
        }

        title.textContent = `Edit Stock - ${batch}`;
        productDisplay.textContent = productName;
        stockIdInput.value = stockId;
        batchInput.value = batch;
        quantityInput.value = quantity;
        expiryInput.value = expiry;

        modal.classList.remove('hidden');
    });
});

closeBtn.addEventListener('click', () => {
    modal.classList.add('hidden');
    clearValidation(modal);
});
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.add('hidden');
        clearValidation(modal);
    }
});
}

function showarchivemodal(){
    const modal = document.getElementById('viewarchiveproductsmodal');
    const btn = document.getElementById('viewarchiveproductsbtn');
    const closeBtn = document.getElementById('closeviewarchiveproductsmodal');
    btn.addEventListener('click', () => {
        modal.classList.remove('hidden');
    });
    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });
}

function showArchiveStockmodal(){
    const modal = document.getElementById('viewarchivedstocksmodal');
    const closeBtn = document.getElementById('closeviewarchivedstocksmodal');
    const productNameSpan = document.getElementById('archived-product-name');
    const stocksTbody = document.getElementById('archived-stocks-tbody');
    document.querySelectorAll('.view-archivestock-btn').forEach(button => {
        button.addEventListener('click', function () {
            const row = this.closest('tr');
            const productId = row.dataset.productId;
            const productName = `${row.dataset.brand} ${row.dataset.product} ${row.dataset.strength} ${row.dataset.form}`;
            productNameSpan.textContent = productName;
            const rows = stocksTbody.querySelectorAll('tr');
            let hasVisibleRows = false;
            rows.forEach(row => {
                if (row.dataset.productId === productId) {
                    row.style.display = '';
                    hasVisibleRows = true;
                } else {
                    row.style.display = 'none';
                }
            });
            if (!hasVisibleRows && rows.length > 0 && rows[0].querySelector('td').textContent === 'No Archived Stocks Available') {
                rows[0].style.display = '';
            }
            modal.classList.remove('hidden');
        });
    });
    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });
}

function sweetalertforallfunction(){
    const addproductform = document.getElementById('add-product-form');
    const addproductbtn = document.getElementById('add-product-btn');

    addproductbtn.addEventListener('click', () => {
        showSweetAlert(addproductform);
    });
    function showSweetAlert(form){
        const inputs = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]), select');
        let formIsMissingData = false;

        inputs.forEach((input) => {
            if (input.value.trim() === '') {
                formIsMissingData = true;
            }
        });

        if (formIsMissingData) {
            Swal.fire({
                title: 'Missing Fields',
                text: 'Please fill out all required inputs before submitting.',
                icon: 'warning',
                confirmButtonText: 'OK',
                customClass: {
                    popup: 'swal-popup',
                    title: 'swal-title',
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
    }
}
// Initialize everything
document.addEventListener('DOMContentLoaded', () => {
    showAddNewProductModal();
    showViewAllProductsModal();
    showAddStockModal();
    showEditProductModal();
    showEditStockModal();
    sweetalertforallfunction();
    showarchivemodal();
    showArchiveStockmodal();
});

// sweetalert for add new product

