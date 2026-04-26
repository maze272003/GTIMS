<section class="min-w-0 space-y-6">
    @if($selectedUser)
        <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-y-auto p-5 sm:p-6 lg:max-h-[calc(100vh-11rem)]">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-red-100 text-lg font-bold text-red-700 dark:bg-red-500/10 dark:text-red-200">
                        {{ $selectedUserInitials }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-300">Currently Editing</p>
                        <h2 class="mt-2 break-words text-2xl font-bold text-gray-900 dark:text-white">{{ $selectedUser->name }}</h2>
                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <span class="break-all">{{ $selectedUser->email }}</span>
                            <span class="hidden sm:inline">&bull;</span>
                            <span>{{ ucfirst($selectedUser->level->name ?? 'No role') }}</span>
                            <span class="hidden sm:inline">&bull;</span>
                            <span>{{ $selectedUser->branch->name ?? 'Unassigned branch' }}</span>
                        </div>
                        <p class="mt-3 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                            Changes here affect only this user. Role names help with identification, but access is saved individually.
                        </p>
                    </div>
                </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-200">
                            {{ $selectedUser->uses_custom_permissions ? 'Individual access profile' : 'Using role defaults until saved here' }}
                        </span>
                        @if(auth()->user()?->hasPermission('users.manage'))
                            <a
                                href="{{ route('admin.manageaccount', ['search' => $selectedUser->email]) }}"
                                class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-red-200 hover:text-red-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-red-500/40 dark:hover:text-red-300"
                            >
                                <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>
                                Manage in User Management
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-800/70">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Assigned</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white" data-assigned-count>{{ count($selectedPermissionIds) }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Permissions currently enabled.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-800/70">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Available</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $permissions->count() }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Total access options in the system.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-800/70">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Role</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ ucfirst($selectedUser->level->name ?? 'None') }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Used for identity and team context.</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-gray-800/70">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Access Mode</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white" data-access-mode>{{ $selectedUser->uses_custom_permissions ? 'Custom' : 'Template' }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Saving here switches this user to individual access.</p>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/60">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Current Access</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">A quick glance at the permissions currently enabled for this user.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm dark:bg-gray-900 dark:text-gray-200">
                            <span data-current-access-count>{{ count($selectedPermissionIds) }}</span>&nbsp;enabled
                        </span>
                    </div>

                    @if($assignedPermissions->isNotEmpty())
                        <div class="mt-4 flex max-h-48 flex-wrap gap-2 overflow-y-auto pr-2" data-current-access-list>
                            @foreach($assignedPermissions as $permission)
                                <span class="inline-flex items-center rounded-full border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 dark:border-red-500/20 dark:bg-gray-900 dark:text-gray-200">
                                    {{ ucwords(str_replace(['.', '_'], ' ', $permission->name)) }}
                                </span>
                            @endforeach
                        </div>
                        <div class="mt-4 hidden rounded-2xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400" data-current-access-empty>
                            No permissions are currently assigned. Toggle access below and save changes to grant access.
                        </div>
                    @else
                        <div class="mt-4 hidden flex max-h-48 flex-wrap gap-2 overflow-y-auto pr-2" data-current-access-list></div>
                        <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400" data-current-access-empty>
                            No permissions are currently assigned. Toggle access below and save changes to grant access.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <form
            id="permissionsForm"
            action="{{ route('admin.roles.update') }}"
            method="POST"
            class="space-y-6 pb-28"
            data-initial-selected='@json(array_values($selectedPermissionIds))'
            data-initial-custom="{{ $selectedUser->uses_custom_permissions ? 'true' : 'false' }}"
        >
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">
            <input type="hidden" name="search" value="{{ $search }}">

            @forelse($permissionSections as $section)
                <details class="group overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" data-permission-section data-section-key="{{ $section['key'] }}" @if($loop->first || $section['assigned_count'] > 0) open @endif>
                    <summary class="cursor-pointer list-none p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-200">
                                    <i class="{{ $section['icon'] }}"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $section['label'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $section['description'] }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <span data-section-assigned-count>{{ $section['assigned_count'] }}</span>&nbsp;/ {{ $section['total_count'] }} enabled
                                </span>
                                <span class="text-gray-400 transition group-open:rotate-180 dark:text-gray-500">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>
                    </summary>

                    <div class="border-t border-gray-200 p-4 dark:border-gray-800 sm:p-6">
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach($section['permissions'] as $permission)
                                <label class="flex items-start gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 transition hover:border-red-200 hover:bg-white dark:border-gray-800 dark:bg-gray-800/70 dark:hover:border-red-500/30 dark:hover:bg-gray-900" data-permission-item data-permission-id="{{ $permission['id'] }}" data-permission-label="{{ $permission['label'] }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $permission['label'] }}</span>
                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-300">
                                                {{ $permission['group'] }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                            {{ $permission['description'] ?: 'No extra description is available for this permission.' }}
                                        </p>
                                    </div>

                                    <span class="relative mt-1 inline-flex h-7 w-12 shrink-0 items-center">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission['id'] }}"
                                            class="peer sr-only"
                                            @checked($permission['assigned'])
                                        >
                                        <span class="absolute inset-0 rounded-full bg-gray-300 transition peer-checked:bg-red-500 dark:bg-gray-600"></span>
                                        <span class="absolute left-1 h-5 w-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>
            @empty
                <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No permissions configured</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Add permission records before assigning individual access.</p>
                </div>
            @endforelse

            @if($permissions->isNotEmpty())
                <div class="sticky bottom-4 z-20 pt-2 sm:bottom-6">
                    <div class="rounded-3xl border border-gray-200 bg-white/95 p-4 shadow-lg shadow-gray-200/40 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 dark:shadow-black/20 sm:p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Save {{ $selectedUser->name }}'s access</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" data-permissions-dirty-state>Validation runs before saving and only this user's permissions are updated.</p>
                            </div>
                            <button
                                id="savePermissionsButton"
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 md:w-auto md:shrink-0"
                            >
                                <i class="fa-solid fa-floppy-disk mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </form>
    @else
        <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-200">
                <i class="fa-solid fa-user-check text-xl"></i>
            </div>
            <h2 class="mt-5 text-xl font-semibold text-gray-900 dark:text-white">Select a user to review access</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                Pick a doctor, nurse, admin, or any other individual from the directory to inspect and manage their specific permissions.
            </p>
            @if(auth()->user()?->hasPermission('users.manage'))
                <a
                    href="{{ route('admin.manageaccount') }}"
                    class="mt-6 inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700"
                >
                    <i class="fa-solid fa-users-gear mr-2"></i>
                    Open User Management
                </a>
            @endif
        </div>
    @endif
</section>
