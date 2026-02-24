/**
 * GTIMS Centralized Notification & Confirmation Utilities
 * Uses SweetAlert2 for toast notifications and confirmation modals.
 */
(function () {
    'use strict';

    // ── Toast Notifications ──────────────────────────────────────────
    function getToast() {
        if (typeof Swal === 'undefined') return null;

        return Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: function (toast) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    }

    window.gtToast = {
        success: function (msg) {
            var toast = getToast();
            if (toast) toast.fire({ icon: 'success', title: msg || 'Done!' });
        },
        error: function (msg) {
            var toast = getToast();
            if (toast) toast.fire({ icon: 'error', title: msg || 'Something went wrong.' });
        },
        warning: function (msg) {
            var toast = getToast();
            if (toast) toast.fire({ icon: 'warning', title: msg || 'Warning' });
        },
        info: function (msg) {
            var toast = getToast();
            if (toast) toast.fire({ icon: 'info', title: msg || 'Info' });
        }
    };

    // ── Confirmation Modal ───────────────────────────────────────────
    /**
     * Show a confirmation modal before a critical action.
     * @param {Object}   opts
     * @param {string}   opts.title        – Modal title
     * @param {string}   opts.text         – Description of the consequence
     * @param {string}  [opts.icon]        – 'warning' | 'info' | 'error' (default 'warning')
     * @param {string}  [opts.confirmText] – Confirm button label (default 'Yes, proceed')
     * @param {string}  [opts.cancelText]  – Cancel button label (default 'Cancel')
     * @param {Function} opts.onConfirm    – Called when the user confirms
     */
    window.gtConfirm = function (opts) {
        if (typeof Swal === 'undefined') {
            if (confirm(opts.text || opts.title)) {
                if (typeof opts.onConfirm === 'function') opts.onConfirm();
            }
            return;
        }

        Swal.fire({
            title: opts.title || 'Are you sure?',
            text: opts.text || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Yes, proceed',
            cancelButtonText: opts.cancelText || 'Cancel',
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
        }).then(function (result) {
            if (result.isConfirmed && typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            }
        });
    };

    // ── API Error Normalizer ─────────────────────────────────────────
    /**
     * Normalizes an error response / exception and shows a user-friendly toast.
     * @param {*} error – Fetch Response, Error object, or anything
     * @param {string} [fallback] – Fallback message
     */
    window.gtHandleError = function (error, fallback) {
        var msg = fallback || 'Something went wrong. Please try again.';

        if (error && error.status) {
            if (error.status === 401 || error.status === 403) {
                msg = 'You are not authorized to perform this action.';
            } else if (error.status === 422) {
                msg = 'Please check your input and try again.';
            } else if (error.status >= 500) {
                msg = 'A server error occurred. Please try again later.';
            } else if (error.status === 0 || !navigator.onLine) {
                msg = 'You appear to be offline. Check your connection.';
            }
        } else if (!navigator.onLine) {
            msg = 'You appear to be offline. Check your connection.';
        }

        window.gtToast.error(msg);
    };

    // ── Offline / Online Banner ──────────────────────────────────────
    window.addEventListener('offline', function () {
        window.gtToast.warning('You are offline. Some features may not work.');
    });
    window.addEventListener('online', function () {
        window.gtToast.success('You are back online.');
    });

})();
