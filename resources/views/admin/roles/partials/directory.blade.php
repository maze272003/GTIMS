<aside class="self-start overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:sticky xl:top-28 xl:flex xl:max-h-[calc(100vh-8.5rem)] xl:flex-col">
    <div class="border-b border-gray-200 p-5 dark:border-gray-800">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-300">User Directory</p>
                <h2 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Choose a person</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Search by name, email, role, or branch.</p>
            </div>
            <span class="inline-flex min-w-12 items-center justify-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-200">
                {{ $users->count() }}
            </span>
        </div>

        <div class="mt-4">
            <label for="userSearchInput" class="sr-only">Search users</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                    id="userSearchInput"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Search doctor, nurse, admin..."
                    class="block w-full rounded-2xl border border-gray-200 bg-gray-50 py-3 pl-11 pr-4 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:bg-gray-900 dark:focus:ring-red-500/20"
                >
            </div>
        </div>

        <div class="mt-4 xl:hidden">
            <label for="mobileUserSelect" class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Quick Select</label>
            <select
                id="mobileUserSelect"
                class="block w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-500/20"
            >
                @foreach($users as $user)
                    <option
                        value="{{ route('admin.roles.index', ['user' => $user->id, 'search' => $search]) }}"
                        @selected($selectedUser?->id === $user->id)
                    >
                        {{ $user->name }} - {{ ucfirst($user->level->name ?? 'No role') }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div id="userDirectoryList" class="space-y-3 p-3 xl:min-h-0 xl:flex-1 xl:overflow-y-auto">
        @forelse($users as $user)
            @php
                $isSelected = $selectedUser?->id === $user->id;
                $effectivePermissionCount = $user->getEffectivePermissions()->count();
            @endphp
            <a
                href="{{ route('admin.roles.index', ['user' => $user->id, 'search' => $search]) }}"
                data-user-card
                data-user-id="{{ $user->id }}"
                data-search="{{ strtolower($user->name.' '.$user->email.' '.($user->level->name ?? '').' '.($user->branch->name ?? '')) }}"
                class="block rounded-2xl border p-4 transition {{ $isSelected ? 'border-red-300 bg-red-50 shadow-sm dark:border-red-500/40 dark:bg-red-500/10' : 'border-gray-200 bg-white hover:border-red-200 hover:bg-red-50/40 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-red-500/30 dark:hover:bg-gray-800/80' }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    </div>

                    @if($isSelected)
                        <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-red-700 shadow-sm dark:bg-gray-900 dark:text-red-200">
                            Selected
                        </span>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-id-badge mr-1.5 text-[10px]"></i>
                        {{ ucfirst($user->level->name ?? 'No role') }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-building mr-1.5 text-[10px]"></i>
                        {{ $user->branch->name ?? 'Unassigned branch' }}
                    </span>
                </div>

                <div class="mt-4 flex items-center justify-between text-xs">
                    <span class="font-medium text-gray-600 dark:text-gray-300">{{ $effectivePermissionCount }} permissions</span>
                    <span class="text-gray-400 dark:text-gray-500">
                        {{ $user->uses_custom_permissions ? 'Custom access' : 'Role defaults' }}
                    </span>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800/70">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm dark:bg-gray-900 dark:text-gray-500">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">No users yet</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Use the user management module to create accounts, then return here to configure individual access.</p>
                @if(auth()->user()?->hasPermission('users.manage'))
                    <a
                        href="{{ route('admin.manageaccount') }}"
                        class="mt-4 inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        Open User Management
                    </a>
                @endif
            </div>
        @endforelse

        <div id="emptyUserSearchState" class="hidden rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800/70">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm dark:bg-gray-900 dark:text-gray-500">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">No matching users</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Try a different search term or check the user record in user management.</p>
        </div>
    </div>
</aside>
