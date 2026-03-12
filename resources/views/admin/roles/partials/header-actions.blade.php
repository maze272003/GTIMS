<div class="flex flex-wrap items-center gap-3">
    @if(auth()->user()?->hasPermission('users.manage'))
        <a
            href="{{ route('admin.manageaccount', array_filter(['search' => $selectedUser?->email])) }}"
            class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-red-200 hover:text-red-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-red-500/40 dark:hover:text-red-300"
        >
            <i class="fa-solid fa-users-gear mr-2"></i>
            Open User Management
        </a>
    @endif
</div>
