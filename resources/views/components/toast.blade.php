{{--
    Auto-render session flash messages as SweetAlert2 toasts.
    Include this component in the layout to automatically display toasts.

    Supports the following session flash keys:
        - session('success')
        - session('error')
        - session('warning')
        - session('info')
        - $errors (validation errors)
--}}

@if(session('success') || session('error') || session('warning') || session('info') || $errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
            if (typeof gtToast !== 'undefined') gtToast.success(@json(session('success')));
        @endif

        @if(session('error'))
            if (typeof gtToast !== 'undefined') gtToast.error(@json(session('error')));
        @endif

        @if(session('warning'))
            if (typeof gtToast !== 'undefined') gtToast.warning(@json(session('warning')));
        @endif

        @if(session('info'))
            if (typeof gtToast !== 'undefined') gtToast.info(@json(session('info')));
        @endif

        @if($errors->any())
            if (typeof gtToast !== 'undefined') gtToast.error(@json('Please fix the validation errors and try again.'));
        @endif
    });
</script>
@endif
