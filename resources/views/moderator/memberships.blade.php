<x-app-layout>
<body class="bg-gray-50 dark:bg-gray-900 antialiased">
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20 min-h-screen flex flex-col">
        <x-admin.header/>

        <main class="flex-1 pt-24 px-4 lg:px-8 pb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Memberships and Role Assignments</h1>

            @if(session('success'))
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('moderator.memberships.store') }}" class="mt-6 grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow md:grid-cols-4 lg:grid-cols-5 dark:bg-gray-800">
                @csrf
                <select name="user_id" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" required>
                    <option value="">User</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <select name="scope_type" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" required>
                    <option value="platform">Platform</option>
                    <option value="province">Province</option>
                    <option value="barangay">Barangay</option>
                </select>
                <input type="number" name="scope_id" placeholder="Scope ID (blank for platform)" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                <select name="role_id" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" required>
                    <option value="">Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }} ({{ $role->scope_type }})</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
            </form>

            <div class="mt-4 overflow-hidden rounded-xl bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Scope</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Scope ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($memberships as $membership)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $membership->user?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $membership->scope_type }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $membership->scope_id ?? 'platform' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $membership->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $memberships->links() }}</div>
        </main>
    </div>
</body>
</x-app-layout>

