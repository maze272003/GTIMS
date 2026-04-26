<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>

        <main id="main-content" class="min-h-screen p-4 pt-24 lg:p-8 lg:pt-24">
            <div class="mb-6 mt-16 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="font-medium text-red-700 dark:text-red-300">User Permissions</span>
                    </p>
                    <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">User Access Management</h1>
                    <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                        Select one user at a time, review only their access, then assign or revoke permissions with grouped mobile-friendly controls.
                    </p>
                </div>

                <div id="user-permissions-header-actions">
                    @include('admin.roles.partials.header-actions')
                </div>
            </div>

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-950/30 dark:text-red-200">
                    Please review the highlighted permission changes and try again.
                </div>
            @endif

            <div
                id="user-permissions-app"
                data-index-url="{{ route('admin.roles.index') }}"
                data-update-url="{{ route('admin.roles.update') }}"
                data-search="{{ $search ?? '' }}"
                data-selected-user-id="{{ $selectedUser?->id }}"
                data-fetching="false"
            >
                <div class="grid items-start gap-6 xl:grid-cols-[320px,minmax(0,1fr)]">
                    <div id="user-permissions-directory">
                        @include('admin.roles.partials.directory')
                    </div>

                    <div id="user-permissions-workspace">
                        @include('admin.roles.partials.workspace')
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
