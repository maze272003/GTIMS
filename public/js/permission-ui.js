(function () {
    'use strict';

    if (window.__gtimsPermissionUiBound) {
        return;
    }

    window.__gtimsPermissionUiBound = true;

    function showPermissionMessage(message) {
        if (typeof gtToast !== 'undefined') {
            gtToast.error(message);
            return;
        }

        if (window.Swal) {
            window.Swal.fire({
                title: 'Insufficient Permission',
                text: message,
                icon: 'error',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
            });
            return;
        }

        window.alert(message);
    }

    document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-permission-disabled="true"]');

        if (!target) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        showPermissionMessage(
            target.getAttribute('data-permission-message')
            || 'This action cannot be accessed with your account. Please contact the superadmin for assistance.'
        );
    }, true);
})();
