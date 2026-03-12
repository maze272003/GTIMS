(function () {
    'use strict';

    if (window.__gtimsUserPermissionsBound) {
        return;
    }

    window.__gtimsUserPermissionsBound = true;

    let pendingSearchTimer = null;

    function getApp() {
        return document.getElementById('user-permissions-app');
    }

    function isUserPermissionsPage() {
        return !!getApp();
    }

    function currentSearch() {
        const input = document.getElementById('userSearchInput');
        return input ? input.value.trim() : (getApp()?.dataset.search || '');
    }

    function currentSelectedUserId() {
        return getApp()?.dataset.selectedUserId || '';
    }

    function buildRolesUrl(userId, search) {
        const app = getApp();

        if (!app) {
            return '';
        }

        const url = new URL(app.dataset.indexUrl, window.location.origin);

        if (userId) {
            url.searchParams.set('user', userId);
        }

        if (search) {
            url.searchParams.set('search', search);
        }

        return url.toString();
    }

    function setLoadingState(isLoading) {
        const app = getApp();

        if (!app) {
            return;
        }

        app.dataset.fetching = isLoading ? 'true' : 'false';

        ['user-permissions-directory', 'user-permissions-workspace'].forEach(function (id) {
            const element = document.getElementById(id);

            if (!element) {
                return;
            }

            element.classList.toggle('pointer-events-none', isLoading);
            element.classList.toggle('opacity-70', isLoading);
        });

        const saveButton = document.getElementById('savePermissionsButton');
        if (saveButton) {
            saveButton.disabled = isLoading;
        }
    }

    function renderFragments(payload, shouldPushState) {
        const app = getApp();

        if (!app || !payload) {
            return;
        }

        const directory = document.getElementById('user-permissions-directory');
        const headerActions = document.getElementById('user-permissions-header-actions');
        const workspace = document.getElementById('user-permissions-workspace');

        if (headerActions && typeof payload.header_actions_html === 'string') {
            headerActions.innerHTML = payload.header_actions_html;
        }

        if (directory && typeof payload.directory_html === 'string') {
            directory.innerHTML = payload.directory_html;
        }

        if (workspace && typeof payload.workspace_html === 'string') {
            workspace.innerHTML = payload.workspace_html;
        }

        app.dataset.search = payload.search || '';
        app.dataset.selectedUserId = payload.selected_user_id || '';

        if (shouldPushState && payload.url) {
            window.history.pushState({ userPermissions: true }, '', payload.url);
        }

        filterUsers();
    }

    function handleFetchFailure(response, fallbackUrl) {
        if (!response) {
            if (typeof gtToast !== 'undefined') {
                gtToast.error('Unable to update permissions right now.');
            }
            return;
        }

        if (response.status === 403 || response.status === 401) {
            window.location.href = fallbackUrl || window.location.href;
            return;
        }

        if (typeof gtHandleError !== 'undefined') {
            gtHandleError(response, 'Unable to update the user permissions page.');
        } else if (typeof gtToast !== 'undefined') {
            gtToast.error('Unable to update the user permissions page.');
        }
    }

    async function fetchFragments(url, options) {
        const app = getApp();

        if (!app || app.dataset.fetching === 'true') {
            return null;
        }

        const requestOptions = Object.assign({
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }, options || {});

        setLoadingState(true);

        try {
            const response = await fetch(url, requestOptions);

            if (response.status === 422) {
                const payload = await response.json();
                const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;

                if (typeof gtToast !== 'undefined') {
                    gtToast.error(firstError || 'Please check your input and try again.');
                }

                return null;
            }

            if (!response.ok) {
                handleFetchFailure(response, url);
                return null;
            }

            const payload = await response.json();
            renderFragments(payload, requestOptions.method === 'GET');

            if (payload.message && typeof gtToast !== 'undefined') {
                gtToast.success(payload.message);
            }

            return payload;
        } catch (error) {
            if (typeof gtToast !== 'undefined') {
                gtToast.error('Unable to update the user permissions page.');
            }

            return null;
        } finally {
            setLoadingState(false);
        }
    }

    function filterUsers() {
        if (!isUserPermissionsPage()) {
            return;
        }

        const term = currentSearch().toLowerCase();
        const cards = Array.from(document.querySelectorAll('[data-user-card]'));
        const emptyState = document.getElementById('emptyUserSearchState');
        let visibleCount = 0;

        cards.forEach(function (card) {
            const haystack = (card.dataset.search || '').toLowerCase();
            const matches = term === '' || haystack.includes(term);

            card.classList.toggle('hidden', !matches);

            if (matches) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle('hidden', visibleCount !== 0 || cards.length === 0);
        }

        const url = new URL(window.location.href);

        if (term) {
            url.searchParams.set('search', term);
        } else {
            url.searchParams.delete('search');
        }

        if (currentSelectedUserId()) {
            url.searchParams.set('user', currentSelectedUserId());
        }

        window.history.replaceState({ userPermissions: true }, '', url.toString());
    }

    async function loadUserFromUrl(url) {
        await fetchFragments(url, { method: 'GET' });
    }

    async function savePermissions(form) {
        const app = getApp();

        if (!app || !form) {
            return;
        }

        const formData = new FormData(form);

        if (!formData.get('search')) {
            formData.set('search', currentSearch());
        }

        const saveButton = document.getElementById('savePermissionsButton');

        if (saveButton) {
            saveButton.innerHTML = '<i class="fa-solid fa-spinner-third mr-2 animate-spin"></i>Saving...';
        }

        const payload = await fetchFragments(app.dataset.updateUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        if (saveButton) {
            saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk mr-2"></i>Save Changes';
        }

        return payload;
    }

    function initializeUserPermissionsPage() {
        if (!isUserPermissionsPage()) {
            return;
        }

        filterUsers();
    }

    document.addEventListener('DOMContentLoaded', initializeUserPermissionsPage);
    window.initializeUserPermissionsPage = initializeUserPermissionsPage;

    document.addEventListener('click', function (event) {
        const app = getApp();

        if (!app) {
            return;
        }

        const userCard = event.target.closest('[data-user-card]');
        if (userCard) {
            event.preventDefault();
            loadUserFromUrl(userCard.href);
            return;
        }
    });

    document.addEventListener('change', function (event) {
        const app = getApp();

        if (!app) {
            return;
        }

        if (event.target.id === 'mobileUserSelect' && event.target.value) {
            loadUserFromUrl(event.target.value);
        }
    });

    document.addEventListener('input', function (event) {
        const app = getApp();

        if (!app || event.target.id !== 'userSearchInput') {
            return;
        }

        filterUsers();

        if (pendingSearchTimer) {
            window.clearTimeout(pendingSearchTimer);
        }

        pendingSearchTimer = window.setTimeout(function () {
            const visibleCard = document.querySelector('[data-user-card]:not(.hidden)');

            if (visibleCard && currentSelectedUserId() !== visibleCard.dataset.userId) {
                loadUserFromUrl(buildRolesUrl(visibleCard.dataset.userId, currentSearch()));
            }
        }, 250);
    });

    document.addEventListener('submit', function (event) {
        const app = getApp();

        if (!app) {
            return;
        }

        if (event.target.id === 'permissionsForm') {
            event.preventDefault();
            savePermissions(event.target);
        }
    });

    window.addEventListener('popstate', function () {
        if (!isUserPermissionsPage()) {
            return;
        }

        loadUserFromUrl(window.location.href);
    });
})();
