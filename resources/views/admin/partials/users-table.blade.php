<div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div id="table-loader" class="absolute inset-0 z-10 bg-white/50 dark:bg-gray-800/50 hidden flex-col items-center justify-center backdrop-blur-sm transition-all duration-300">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
        <span class="mt-2 text-sm font-medium text-blue-600 dark:text-blue-400">Loading data...</span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User Profile</th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role Access</th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Branch</th>
                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined Date</th>
                    <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($users as $user)
                    @php
                        $role = strtolower($user->level->name ?? '');
                        $badgeClass = match(true) {
                            str_contains($role, 'admin') => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
                            str_contains($role, 'doctor') => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
                            str_contains($role, 'encoder') => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 border border-green-200 dark:border-green-800',
                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200',
                        };
                    @endphp

                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200 group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 relative">
                                    <img class="h-10 w-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-800 shadow-sm" 
                                         src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random&color=fff&bold=true&size=128" 
                                         alt="{{ $user->name }}">
                                    <span class="absolute bottom-0 right-0 block h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900 bg-green-400"></span>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 group-hover:text-blue-600 transition-colors">
                                        {{ $user->name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                        {{ $user->email }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badgeClass }}">
                                {{ ucfirst($user->level->name ?? 'N/A') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                                <i class="fa-solid fa-building text-gray-400 mr-2 text-xs"></i>
                                {{ $user->branch->name ?? 'Head Office' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $user->created_at->format('M d, Y') }}
                            <span class="block text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <button onclick='openUserModal("edit", @json($user))' class="group relative p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 rounded-full hover:bg-blue-50 dark:hover:bg-blue-900/20">
                                <i class="fa-solid fa-pencil fa-lg"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-full mb-3">
                                    <i class="fa-solid fa-users-slash text-4xl text-gray-400 dark:text-gray-500"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">No users found</h3>
                                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Try adjusting your search terms or add a new user.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            {{ $users->links() }}
        </div>
    @endif
</div>