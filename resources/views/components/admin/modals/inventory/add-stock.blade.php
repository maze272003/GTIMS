<div id="addstockmodal" class="fixed w-full h-screen top-0 left-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
  <div class="modal bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg p-5">
    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
      <p id="add-stock-title" class="text-xl font-medium text-gray-600 dark:text-gray-300">Add Stock</p>
      <button id="closeaddstockmodal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
        <i class="fa-regular fa-xmark text-gray-600 dark:text-gray-400"></i>
      </button>
    </div>

    <form action="{{ route('admin.inventory.addstock') }}" method="POST" id="addstockform">
      @csrf
      @method('POST')
      <input type="hidden" id="selected-product-id" name="product_id" value="{{ old('product_id') }}">
      <input type="hidden" id="selected-branch-id" name="branch_id" value="1">

      <div class="flex gap-2 mt-2">
        <div class="w-1/2">
          <label for="batchnumber" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Batch Number:</label>
          <input type="text" name="batchnumber" id="batchnumber" placeholder="Enter Batch Number" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ old('batchnumber') }}">
          @error('batchnumber', 'addstock')
            <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
          @enderror
        </div>
        <div class="w-1/2">
          <label for="quantity" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Quantity:</label>
          <input type="number" name="quantity" id="quantity" placeholder="Enter Quantity" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400" value="{{ old('quantity') }}">
          @error('quantity', 'addstock')
            <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
          @enderror
        </div>
      </div>

      <div class="w-full mt-2">
        <label for="expiry" class="text-sm font-semibold text-gray-600 dark:text-gray-300">Expiry Date:</label>
        <input type="date" name="expiry" id="expiry" class="mt-1 p-2 w-full border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" value="{{ old('expiry') }}">
        @error('expiry', 'addstock')
          <p class="text-red-600 dark:text-red-400 text-sm mt-1 error-message">{{ $message }}</p>
        @enderror
      </div>

      <button type="button" id="addstockbtn" class="bg-blue-500 dark:bg-blue-600 text-white p-2 rounded-lg mt-5 hover:-translate-y-1 hover:shadow-md transition-all duration-200 w-fit">
        <i class="fa-regular fa-check"></i>
        <span>Submit</span>
      </button>
    </form>
  </div>
</div>

<script>
  document.getElementById('addstockbtn').addEventListener('click', function() {
    const form = document.getElementById('addstockform');
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
