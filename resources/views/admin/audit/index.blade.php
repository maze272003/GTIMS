<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / <span class="text-red-700 dark:text-red-300 font-medium">Audit Log</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-5">Audit Log</h2>
            </div>

            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <form method="GET" action="{{ route('admin.audit.index') }}" class="flex flex-col sm:flex-row gap-4 items-end flex-wrap">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Action</label>
                        <select name="action" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Actions</option>
                            @foreach(['create', 'update', 'delete', 'approve', 'reject', 'transfer', 'login', 'logout'] as $a)
                                <option value="{{ $a }}" {{ request('action') == $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entity Type</label>
                        <select name="entity_type" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Types</option>
                            @foreach($entityTypes ?? [] as $type)
                                <option value="{{ $type }}" {{ request('entity_type') == $type ? 'selected' : '' }}>{{ class_basename($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                    </div>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg text-sm transition">
                        <i class="fa-solid fa-filter mr-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.audit.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2.5 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600">
                        Clear
                    </a>
                </form>
            </div>

            {{-- Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-gray-200 dark:bg-gray-700">
                            <tr>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">ID</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Action</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Entity Type</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Entity ID</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">User</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Created At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($audits ?? [] as $audit)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150 cursor-pointer" onclick="window.location='{{ route('admin.audit.show', $audit->id) }}'">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">#{{ $audit->id }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($audit->action === 'create') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                            @elseif($audit->action === 'update') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                            @elseif($audit->action === 'delete') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                            {{ ucfirst($audit->action) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ class_basename($audit->entity_type ?? $audit->auditable_type ?? '-') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $audit->entity_id ?? $audit->auditable_id ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $audit->user->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $audit->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                            <p>No audit records found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @isset($audits)
                    @if(method_exists($audits, 'links'))
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            {{ $audits->links() }}
                        </div>
                    @endif
                @endisset
            </div>
        </main>
    </div>
</x-app-layout>
