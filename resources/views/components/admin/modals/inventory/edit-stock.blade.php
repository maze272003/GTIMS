<div id="editstockmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
            <p id="edit-stock-title" class="text-xl font-medium text-gray-600 dark:text-gray-300">Edit Stock</p>
            <button id="closeeditstockmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400"></i>
            </button>
        </div>
        <form action="{{ route('admin.inventory.editstock') }}" method="POST" id="editstockform">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit-stock-id" name="inventory_id" value="{{ old('inventory_id') }}">
            <div class="mb-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span id="edit-stock-product"></span>
                </p>
            </div>
            <div class="flex gap-2 mt-2">
                <div class="w-1/2">
                    <label for="edit-batchnumber" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Batch Number:</label>
                    <input type="text" name="batchnumber" id="edit-batchnumber" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('batchnumber') }}" placeholder="Enter Batch Number">
                    @error('batchnumber', 'editstock')
                        <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-1/2">
                    <label for="edit-quantity" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Quantity:</label>
                    <input type="number" name="quantity" id="edit-quantity" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('quantity') }}" placeholder="Enter Quantity">
                    @error('quantity', 'editstock')
                        <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="w-full mt-2">
                <label for="edit-expiry" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Expiry Date:</label>
                <input type="date" name="expiry" id="edit-expiry" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('expiry') }}">
                @error('expiry', 'editstock')
                    <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
                @enderror
            </div>
            <button type="button" id="editstockbtn" class="bg-blue-500 dark:bg-blue-600 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
                <i class="fa-regular fa-check"></i> <span>Update</span>
            </button>
        </form>
    </div>
</div>

<script>
    document.getElementById('editstockbtn').addEventListener('click', function() {
    const form = document.getElementById('editstockform');
    const inputs = form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]');
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
</script>