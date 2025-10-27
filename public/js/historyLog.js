document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('viewMoreModal');
    const modalDesc = document.getElementById('modalDescription');
    const closeBtn = document.getElementById('closeModalBtn');
    const searchInput = document.getElementById('searchInput');
    const tableContainer = document.getElementById('history-table');
    const loader = document.getElementById('table-loader');
    const toggleFilterBtn = document.getElementById('toggleFilterBtn');
    const filterPanel = document.getElementById('filterPanel');
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    const filterForm = document.getElementById('filterForm');
    let typingTimer;
    const delay = 500; // delay before triggering live search

    // === Modal Logic ===
    function attachModalEvents() {
        document.querySelectorAll('.view-more-btn').forEach(button => {
            button.addEventListener('click', () => {
                modalDesc.textContent = button.dataset.full;
                modal.classList.remove('hidden');
            });
        });
    }

    closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    attachModalEvents();

    // === Toggle Filter Panel ===
    toggleFilterBtn.addEventListener('click', () => {
        const isOpen = filterPanel.classList.contains('max-h-[1000px]');
        
        if (isOpen) {
            filterPanel.classList.remove('max-h-[1000px]', 'p-4');
            filterPanel.classList.add('max-h-0');
            toggleFilterBtn.innerHTML = '<i class="fas fa-filter text-xs"></i> Show Filters';
        } else {
            filterPanel.classList.remove('max-h-0');
            filterPanel.classList.add('max-h-[1000px]', 'p-4'); // allow space for expansion
            toggleFilterBtn.innerHTML = '<i class="fas fa-xmark text-xs"></i> Hide Filters';
        }
    });

    // === Live Search ===
    searchInput.addEventListener('input', () => {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => performSearch(), delay);
    });

    // === Apply Filters ===
    applyFilterBtn.addEventListener('click', () => {
        performSearch();
    });

    // === Reset Filters ===
    resetFilterBtn.addEventListener('click', () => {
        filterForm.reset();
        performSearch();
    });

    // === Fetch and Update Table ===
    function performSearch(url = null) {
        const targetUrl = url ? new URL(url, window.location.origin) : new URL(window.location.href);
        const params = new URLSearchParams(targetUrl.search); // preserve existing params like ?page=2

        const query = searchInput.value;
        const action = document.getElementById('filterAction').value;
        const user = document.getElementById('filterUser').value;
        const from = document.getElementById('filterFrom').value;
        const to = document.getElementById('filterTo').value;

        // Merge or remove parameters dynamically
        query ? params.set('search', query) : params.delete('search');
        action ? params.set('action', action) : params.delete('action');
        user ? params.set('user', user) : params.delete('user');
        from ? params.set('from', from) : params.delete('from');
        to ? params.set('to', to) : params.delete('to');

        targetUrl.search = params.toString();

        showLoader();

        fetch(targetUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            tableContainer.innerHTML = html;
            attachModalEvents();
            attachPaginationEvents();
            window.history.pushState({}, '', targetUrl); // Update browser URL
        })
        .catch(err => console.error('AJAX error:', err))
        .finally(() => setTimeout(() => hideLoader(), 200));
    }

    // === Pagination Handling ===
    function attachPaginationEvents() {
        document.querySelectorAll('#history-table .pagination a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const pageUrl = link.href;
                performSearch(pageUrl);
            });
        });
    }

    // === Loader Functions ===
    function showLoader() {
        if (loader) loader.classList.remove('hidden');
    }

    function hideLoader() {
        if (loader) loader.classList.add('hidden');
    }

    // Initialize pagination listeners on page load
    attachPaginationEvents();
});
