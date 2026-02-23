document.addEventListener('DOMContentLoaded', function () {

    // --- HELPER FUNCTIONS ---

    function clearValidation(modal) {
        const errorMessages = modal.querySelectorAll('.error-message');
        errorMessages.forEach(error => error.remove());
    }

    function toggleModal(modalId, show = true) {
        const modal = document.getElementById(modalId);
        if (modal) {
            if (show) {
                modal.classList.remove('hidden');
            } else {
                modal.classList.add('hidden');
                clearValidation(modal);
            }
        }
    }

    const swalClasses = {
        container: 'swal-container',
        popup: 'swal-popup',
        title: 'swal-title',
        htmlContainer: 'swal-content',
        confirmButton: 'swal-confirm-button',
        cancelButton: 'swal-cancel-button',
        icon: 'swal-icon'
    };

    function inventorySwal(options = {}) {
        return Swal.fire({
            allowOutsideClick: false,
            ...options,
            customClass: {
                ...swalClasses,
                ...(options.customClass || {})
            }
        });
    }

    function getRequiredFields(form) {
        return Array.from(form.querySelectorAll('input[required], select[required], textarea[required]'))
            .filter((field) => !field.disabled);
    }

    function hasMissingRequiredFields(form) {
        return getRequiredFields(form).some((field) => String(field.value ?? '').trim() === '');
    }

    function showIncompleteFormAlert() {
        return inventorySwal({
            title: 'Incomplete Form',
            text: 'Please fill in all required fields before submitting.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
    }

    function showProcessingAlert() {
        return inventorySwal({
            title: 'Processing...',
            text: 'Please wait, your request is being processed.',
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function bindValidatedModalSubmit({
        buttonId,
        formId,
        confirmTitle = 'Are you sure?',
        confirmText = 'Please confirm if you want to proceed.',
        confirmIcon = 'info',
        confirmButtonText = 'Confirm',
        cancelButtonText = 'Cancel',
        validate = null,
    }) {
        const button = document.getElementById(buttonId);
        const form = document.getElementById(formId);

        if (!button || !form) return;

        button.addEventListener('click', async function () {
            if (hasMissingRequiredFields(form)) {
                await showIncompleteFormAlert();
                return;
            }

            if (typeof validate === 'function') {
                const validationResult = validate({ form, button });

                if (validationResult !== true) {
                    const errorState = validationResult || {};
                    await inventorySwal({
                        title: errorState.title || 'Validation Error',
                        text: errorState.text || 'Please check your input values.',
                        icon: errorState.icon || 'error',
                        confirmButtonText: errorState.confirmButtonText || 'OK',
                    });
                    return;
                }
            }

            const result = await inventorySwal({
                title: confirmTitle,
                text: confirmText,
                icon: confirmIcon,
                showCancelButton: true,
                cancelButtonText,
                confirmButtonText,
            });

            if (!result.isConfirmed) return;

            showProcessingAlert();
            form.submit();
        });
    }

    window.inventoryModalValidation = {
        bindValidatedModalSubmit,
        inventorySwal,
        showProcessingAlert,
        showIncompleteFormAlert,
    };

    // --- EVENT DELEGATION (Ito ang solusyon sa Bugs) ---
    // Lahat ng click events sa loob ng table ay dito dadaan.
    // Kahit mag-AJAX ka, gagana pa rin ito.

    document.addEventListener('click', function (e) {
        const target = e.target;

        // 1. EDIT STOCK BUTTON
        const editStockBtn = target.closest('.edit-stock-btn');
        if (editStockBtn) {
            const row = editStockBtn.closest('tr');
            if (!row) return;

            const modal = document.getElementById('editstockmodal');
            const title = document.getElementById('edit-stock-title');
            const productDisplay = document.getElementById('edit-stock-product');
            const stockIdInput = document.getElementById('edit-stock-id');
            const batchInput = document.getElementById('edit-batchnumber');
            const quantityInput = document.getElementById('edit-quantity');
            const expiryInput = document.getElementById('edit-expiry');

            // Data gathering
            const productName = `${row.dataset.product} ${row.dataset.strength} ${row.dataset.form} (${row.dataset.brand})`;
            const batch = row.dataset.batch;
            const quantity = row.dataset.quantity;
            const expiry = row.dataset.expiry;
            const stockId = row.dataset.stockId;

            if (stockId) {
                title.textContent = `Edit Stock - ${batch}`;
                productDisplay.textContent = productName;
                stockIdInput.value = stockId;
                batchInput.value = batch;
                quantityInput.value = quantity;
                expiryInput.value = expiry;
                toggleModal('editstockmodal', true);
            }
            return;
        }

        // 2. TRANSFER STOCK BUTTON
        const transferBtn = target.closest('.transfer-stock-btn');
        if (transferBtn) {
            const row = transferBtn.closest('tr');
            const modal = document.getElementById('transferstockmodal');
            const qtyInput = document.getElementById('transfer_qty');
            const errorMsg = document.getElementById('transfer-error');
            const confirmBtn = document.getElementById('confirm-transfer-btn');
            
            // Data from data-attributes
            const stockId = transferBtn.dataset.stockId;
            const product = transferBtn.dataset.product;
            const strength = transferBtn.dataset.strength;
            const form = transferBtn.dataset.form;
            const batch = transferBtn.dataset.batch;
            const branch = transferBtn.dataset.branch;
            const quantity = parseInt(transferBtn.dataset.quantity);
            const branchId = transferBtn.dataset.branchId;

            // Populate Modal
            document.getElementById('transfer-inventory-id').value = stockId;
            document.getElementById('transfer-product-name').textContent = `${product} ${strength} ${form}`;
            document.getElementById('transfer-batch').textContent = batch;
            document.getElementById('transfer-current-branch').textContent = branch;
            document.getElementById('transfer-available-qty').textContent = quantity;

            // Reset Input
            qtyInput.max = quantity;
            qtyInput.value = '';
            if(errorMsg) errorMsg.classList.add('hidden');
            if(confirmBtn) confirmBtn.disabled = false;

            // Auto-select destination (Assuming 1=RHU1, 2=RHU2)
            const destSelect = document.getElementById('destination_branch');
            if(destSelect) {
                destSelect.value = (branchId == 1) ? 2 : 1;
            }

            // Real-time validation for transfer input
            qtyInput.oninput = () => {
                const val = parseInt(qtyInput.value);
                if (val > quantity || val <= 0 || isNaN(val)) {
                    errorMsg.classList.remove('hidden');
                    confirmBtn.disabled = true;
                } else {
                    errorMsg.classList.add('hidden');
                    confirmBtn.disabled = false;
                }
            };

            toggleModal('transferstockmodal', true);
            return;
        }

        // 3. ADD STOCK BUTTON (Icon sa table row)
        const addStockBtn = target.closest('.add-stock-btn');
        if (addStockBtn) {
            const row = addStockBtn.closest('tr');
            const modal = document.getElementById('addstockmodal');
            const title = document.getElementById('add-stock-title');
            const productIdInput = document.getElementById('selected-product-id');

            const productName = `${row.dataset.product} ${row.dataset.strength} ${row.dataset.form}`;
            const productId = row.dataset.productId;

            title.textContent = `Add Stock - ${productName}`;
            productIdInput.value = productId;
            toggleModal('addstockmodal', true);
            return;
        }

        // 4. EDIT PRODUCT BUTTON
        const editProductBtn = target.closest('.edit-product-btn');
        if (editProductBtn) {
            const row = editProductBtn.closest('tr');
            const modal = document.getElementById('editproductmodal');
            const brandInput = document.getElementById('edit-brand');
            const productInput = document.getElementById('edit-product');
            const formInput = document.getElementById('edit-form');
            const strengthInput = document.getElementById('edit-strength');
            const productIdInput = document.getElementById('edit-product-id');

            const productId = row.dataset.productId;

            productIdInput.value = productIdInput.value || productId || '';
            brandInput.value = brandInput.value || row.dataset.brand || '';
            productInput.value = productInput.value || row.dataset.product || '';
            formInput.value = formInput.value || row.dataset.form || '';
            strengthInput.value = strengthInput.value || row.dataset.strength || '';

            toggleModal('editproductmodal', true);
            return;
        }

        // 5. VIEW ARCHIVED STOCKS BUTTON
        const viewArchiveStockBtn = target.closest('.view-archivestock-btn');
        if (viewArchiveStockBtn) {
            const row = viewArchiveStockBtn.closest('tr');
            const modal = document.getElementById('viewarchivedstocksmodal');
            const productNameSpan = document.getElementById('archived-product-name');
            const stocksTbody = document.getElementById('archived-stocks-tbody');
            const productId = row.dataset.productId;
            const productName = `${row.dataset.brand} ${row.dataset.product} ${row.dataset.strength} ${row.dataset.form}`;

            if(productNameSpan) productNameSpan.textContent = productName;
            
            // Reset and show modal
            if(stocksTbody) stocksTbody.innerHTML = '';
            toggleModal('viewarchivedstocksmodal', true);

            // Fetch Data
            loadMoreArchivedStocks(productId, 1, stocksTbody);
            return;
        }

        // --- CLOSING MODALS (Clicking outside or X button) ---
        
        // Close Button Class
        if (target.closest('.close-modal') || target.closest('[id^="close"]')) { // Matches IDs starting with 'close'
             // Find parent modal
             const modal = target.closest('.modal')?.parentElement || target.closest('[id$="modal"]');
             if(modal) {
                 modal.classList.add('hidden');
                 clearValidation(modal);
             }
        }

        // Clicking Outside Modal (Background)
        if (target.classList.contains('fixed') && target.classList.contains('z-50')) {
             // Assuming the modal wrapper has these classes (Tailwind modal background)
             target.classList.add('hidden');
             clearValidation(target);
        }
    });

    // --- STATIC BUTTON LISTENERS (Buttons na hindi nawawala) ---

    // Add New Product (Top Button)
    const addNewProductBtn = document.getElementById('addnewproductbtn');
    if (addNewProductBtn) {
        addNewProductBtn.addEventListener('click', () => toggleModal('addnewproductmodal', true));
    }

    // View All Products (Top Button)
    const viewAllProductsBtn = document.getElementById('viewallproductsbtn');
    if (viewAllProductsBtn) {
        viewAllProductsBtn.addEventListener('click', () => toggleModal('viewallproductsmodal', true));
    }

    // View Archived Products (Top Button)
    const viewArchiveProductsBtn = document.getElementById('viewarchiveproductsbtn');
    if (viewArchiveProductsBtn) {
        viewArchiveProductsBtn.addEventListener('click', () => toggleModal('viewarchiveproductsmodal', true));
    }

    // --- ARCHIVED STOCKS AJAX LOGIC ---
    async function loadMoreArchivedStocks(productId, page, container) {
        try {
            const url = `/admin/inventory/archived-stocks?product_id=${productId}&page=${page}`;
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            
            if (page === 1 && container) container.innerHTML = '';
            if (container) container.insertAdjacentHTML('beforeend', data.html);
            
            // Note: Simple implementation. Add scroll listener logic here if you want infinite scroll
        } catch (error) {
            if (typeof gtToast !== 'undefined') gtToast.error('Error loading archived stocks.');
            if(container) container.innerHTML = '<tr><td colspan="4" class="text-red-500 p-4 text-center">Error loading data</td></tr>';
        }
    }

    // --- CONSOLIDATED MODAL VALIDATION / CONFIRM SUBMIT ---
    bindValidatedModalSubmit({
        buttonId: 'add-product-btn',
        formId: 'add-product-form',
    });

    bindValidatedModalSubmit({
        buttonId: 'addstockbtn',
        formId: 'addstockform',
    });

    bindValidatedModalSubmit({
        buttonId: 'editproductbtn',
        formId: 'edit-product-form',
    });

    bindValidatedModalSubmit({
        buttonId: 'editstockbtn',
        formId: 'editstockform',
    });
});
