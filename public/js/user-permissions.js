(function () {
    'use strict';

    if (window.__gtimsUserPermissionsBound) {
        return;
    }

    window.__gtimsUserPermissionsBound = true;

    let pendingSearchTimer = null;
    let pendingGetRequest = null;

    function getPermissionsForm() {
        return document.getElementById('permissionsForm');
    }

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

    function currentPermissionInputs() {
        const form = getPermissionsForm();

        if (!form) {
            return [];
        }

        return Array.from(form.querySelectorAll('input[name="permissions[]"]'));
    }

    function currentCheckedPermissionInputs() {
        return currentPermissionInputs().filter(function (input) {
            return input.checked;
        });
    }

    function parseInitialSelection(form) {
        if (!form) {
            return [];
        }

        try {
            const payload = JSON.parse(form.dataset.initialSelected || '[]');

            return Array.isArray(payload)
                ? payload.map(function (value) { return String(value); }).sort()
                : [];
        } catch (error) {
            return [];
        }
    }

    function currentSelectedPermissionIds() {
        return currentCheckedPermissionInputs()
            .map(function (input) { return String(input.value); })
            .sort();
    }

    function selectionsMatch(left, right) {
        if (left.length !== right.length) {
            return false;
        }

        return left.every(function (value, index) {
            return value === right[index];
        });
    }

    function sectionCheckedCount(section) {
        if (!section) {
            return 0;
        }

        return section.querySelectorAll('input[name="permissions[]"]:checked').length;
    }

    function updateCurrentAccessPreview(checkedInputs) {
        const list = document.querySelector('[data-current-access-list]');
        const emptyState = document.querySelector('[data-current-access-empty]');

        if (!list || !emptyState) {
            return;
        }

        if (checkedInputs.length === 0) {
            list.innerHTML = '';
            list.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }

        const items = checkedInputs
            .map(function (input) {
                return {
                    id: String(input.value),
                    label: input.closest('[data-permission-item]')?.dataset.permissionLabel || input.value,
                };
            })
            .sort(function (left, right) {
                return left.label.localeCompare(right.label);
            });

        list.innerHTML = items.map(function (item) {
            return '<span class="inline-flex items-center rounded-full border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-red-500/20 dark:bg-gray-900 dark:text-gray-200">'
                + item.label
                + '</span>';
        }).join('');

        list.classList.remove('hidden');
        emptyState.classList.add('hidden');
    }

    function syncWorkspaceState() {
        const form = getPermissionsForm();

        if (!form) {
            return;
        }

        const checkedInputs = currentCheckedPermissionInputs();
        const selectedCount = checkedInputs.length;
        const initialSelection = parseInitialSelection(form);
        const isDirty = !selectionsMatch(
            currentSelectedPermissionIds(),
            initialSelection
        );

        form.dataset.dirty = isDirty ? 'true' : 'false';

        document.querySelectorAll('[data-assigned-count]').forEach(function (element) {
            element.textContent = String(selectedCount);
        });

        document.querySelectorAll('[data-current-access-count]').forEach(function (element) {
            element.textContent = String(selectedCount);
        });

        document.querySelectorAll('[data-permission-section]').forEach(function (section) {
            const assignedCount = sectionCheckedCount(section);

            section.querySelectorAll('[data-section-assigned-count]').forEach(function (element) {
                element.textContent = String(assignedCount);
            });
        });

        updateCurrentAccessPreview(checkedInputs);

        const dirtyState = document.querySelector('[data-permissions-dirty-state]');
        if (dirtyState) {
            dirtyState.textContent = isDirty
                ? 'Unsaved changes ready to save.'
                : 'Validation runs before saving and only this user\'s permissions are updated.';
        }

        const accessMode = document.querySelector('[data-access-mode]');
        if (accessMode) {
            const isInitiallyCustom = form.dataset.initialCustom === 'true';

            accessMode.textContent = (!isInitiallyCustom && isDirty)
                ? 'Custom after save'
                : (isInitiallyCustom ? 'Custom' : 'Template');
        }
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

        initializeUserPermissionsPage();
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

        if (!app) {
            return null;
        }

        const requestOptions = Object.assign({
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }, options || {});

        const requestMethod = String(requestOptions.method || 'GET').toUpperCase();

        if (app.dataset.fetching === 'true') {
            if (requestMethod === 'GET') {
                pendingGetRequest = {
                    url: url,
                    options: requestOptions,
                };
            }

            return null;
        }

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
            const hasPendingNewerGet = requestMethod === 'GET'
                && pendingGetRequest
                && pendingGetRequest.url !== url;

            if (!hasPendingNewerGet) {
                renderFragments(payload, requestMethod === 'GET');
            }

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

            if (pendingGetRequest) {
                const nextRequest = pendingGetRequest;
                pendingGetRequest = null;

                if (requestMethod !== 'GET' || nextRequest.url !== url) {
                    fetchFragments(nextRequest.url, nextRequest.options);
                }
            }
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
        syncWorkspaceState();
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
            return;
        }

        if (event.target.matches('input[name="permissions[]"]')) {
            syncWorkspaceState();
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
