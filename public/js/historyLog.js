document.addEventListener('DOMContentLoaded', function () {
    const tableContainer = document.getElementById('history-table');
    const modal          = document.getElementById('viewMoreModal');
    const modalDesc      = document.getElementById('modalDescription');
    const closeBtn       = document.getElementById('closeModalBtn');
    const searchInput    = document.getElementById('searchInput');
    const toggleFilter   = document.getElementById('toggleFilterBtn');
    const filterPanel    = document.getElementById('filterPanel');
    const applyFilter    = document.getElementById('applyFilterBtn');
    const resetFilter    = document.getElementById('resetFilterBtn');
    const filterForm     = document.getElementById('filterForm');
    const loader         = document.getElementById('table-loader');

    let debounceTimer;
    const DEBOUNCE_DELAY = 300;

    /* -------------------------------------------------
       Helper: Debounce
       ------------------------------------------------- */
    function debounce(func, delay) {
        return function (...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func.apply(this, args), delay);
        };
    }

    /* -------------------------------------------------
       Re-attach “View More” buttons after every AJAX load
       ------------------------------------------------- */
    function attachViewMore() {
        document.querySelectorAll('.view-more-btn').forEach(btn => {
            btn.onclick = () => {
                modalDesc.textContent = btn.dataset.full;
                modal.classList.remove('hidden');
            };
        });
    }

    /* -------------------------------------------------
       Modal close
       ------------------------------------------------- */
    closeBtn.onclick = () => modal.classList.add('hidden');
    modal.onclick = e => { if (e.target === modal) modal.classList.add('hidden'); };

    /* -------------------------------------------------
       Filter panel toggle (smooth)
       ------------------------------------------------- */
    if (toggleFilter && filterPanel) {
        toggleFilter.onclick = () => {
            const hidden = filterPanel.classList.contains('max-h-0');
            filterPanel.classList.toggle('max-h-0', !hidden);
            filterPanel.classList.toggle('max-h-[1000px]', hidden);
            filterPanel.classList.toggle('opacity-0', !hidden);
            filterPanel.classList.toggle('opacity-100', hidden);
            filterPanel.classList.toggle('p-0', !hidden);
            filterPanel.classList.toggle('p-4', hidden);
            toggleFilter.innerHTML = hidden
                ? '<i class="fas fa-xmark text-xs"></i> Hide Filters'
                : '<i class="fas fa-filter text-xs"></i> Show Filters';
        };
    }

    /* -------------------------------------------------
       Core AJAX fetch
       ------------------------------------------------- */
    function fetchHistory(url) {
        showLoader();
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                tableContainer.innerHTML = html;
                window.history.pushState({ path: url }, '', url);
                attachViewMore();
                attachPagination();
                attachSort();
            })
            .catch(err => console.error('AJAX error:', err))
            .finally(() => setTimeout(hideLoader, 200));
    }

    /* -------------------------------------------------
       Pagination (delegated)
       ------------------------------------------------- */
    function attachPagination() {
        tableContainer.addEventListener('click', e => {
            const a = e.target.closest('a');
            if (a && a.closest('.pagination-links')) {
                e.preventDefault();
                fetchHistory(a.href);
            }
        });
    }

    /* -------------------------------------------------
       Date sort button
       ------------------------------------------------- */
    function attachSort() {
        const btn = document.getElementById('sortDateBtn');
        if (!btn) return;
        btn.onclick = () => {
            const u = new URL(location.href);
            const p = u.searchParams;
            const cur = p.get('sort') || 'desc';
            p.set('sort', cur === 'desc' ? 'asc' : 'desc');
            fetchHistory(u.toString());
        };
    }

    /* -------------------------------------------------
       Search (debounced)
       ------------------------------------------------- */
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(() => {
            const base = location.origin + location.pathname;
            const u    = new URL(base);
            const q    = searchInput.value.trim();
            if (q) u.searchParams.set('search', q);
            else u.searchParams.delete('search');
            fetchHistory(u.href);
        }, DEBOUNCE_DELAY));
    }

    /* -------------------------------------------------
       Filters (apply / reset)
       ------------------------------------------------- */
    if (applyFilter) {
        applyFilter.onclick = () => performFilters();
    }
    if (resetFilter) {
        resetFilter.onclick = () => {
            filterForm.reset();
            performFilters();
        };
    }

    function performFilters() {
        const base = location.origin + location.pathname;
        const u    = new URL(base);
        const p    = u.searchParams;

        const vals = {
            search: searchInput?.value.trim(),
            action: document.getElementById('filterAction')?.value,
            user  : document.getElementById('filterUser')?.value,
            from  : document.getElementById('filterFrom')?.value,
            to    : document.getElementById('filterTo')?.value,
        };

        Object.entries(vals).forEach(([k, v]) => v ? p.set(k, v) : p.delete(k));
        fetchHistory(u.href);
    }

    /* -------------------------------------------------
       Loader helpers
       ------------------------------------------------- */
    function showLoader() { if (loader) loader.classList.remove('hidden'); }
    function hideLoader() { if (loader) loader.classList.add('hidden'); }

    /* -------------------------------------------------
       Browser back/forward
       ------------------------------------------------- */
    window.addEventListener('popstate', () => fetchHistory(location.href));

    /* -------------------------------------------------
       Initialise everything on first load
       ------------------------------------------------- */
    attachViewMore();
    attachPagination();
    attachSort();
});