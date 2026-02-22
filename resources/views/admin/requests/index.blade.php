<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Requests</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Request Management</h2>
                </div>
                <a href="{{ route('admin.requests.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md transition-all duration-200">
                    <i class="fa-solid fa-plus mr-2"></i> Create Request
                </a>
            </div>

            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <form method="GET" action="{{ route('admin.requests.index') }}" class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Statuses</option>
                            @foreach(['draft', 'submitted', 'under_review', 'approved', 'denied', 'fulfilled', 'closed'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                        <select name="priority" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Priorities</option>
                            @foreach(['low', 'normal', 'high', 'urgent'] as $p)
                                <option value="{{ $p }}" {{ request('priority') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch</label>
                        <select name="branch" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Branches</option>
                            @foreach($branches ?? [] as $branch)
                                <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg text-sm transition">
                        <i class="fa-solid fa-filter mr-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.requests.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2.5 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600">
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
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Branch</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Department</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Priority</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Status</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Requester</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Created At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($requests ?? [] as $request)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150 cursor-pointer" onclick="window.location='{{ route('admin.requests.show', $request->id) }}'">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">#{{ $request->id }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $request->branch->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $request->department ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($request->priority === 'urgent')
                                            <x-badge variant="danger">{{ ucfirst($request->priority) }}</x-badge>
                                        @elseif($request->priority === 'high')
                                            <x-badge variant="warning">{{ ucfirst($request->priority) }}</x-badge>
                                        @elseif($request->priority === 'normal')
                                            <x-badge variant="info">{{ ucfirst($request->priority) }}</x-badge>
                                        @else
                                            <x-badge variant="default">{{ ucfirst($request->priority) }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($request->status === 'approved')
                                            <x-badge variant="success">{{ ucwords(str_replace('_', ' ', $request->status)) }}</x-badge>
                                        @elseif($request->status === 'submitted')
                                            <x-badge variant="warning">{{ ucwords(str_replace('_', ' ', $request->status)) }}</x-badge>
                                        @elseif($request->status === 'under_review')
                                            <x-badge variant="info">{{ ucwords(str_replace('_', ' ', $request->status)) }}</x-badge>
                                        @elseif($request->status === 'denied')
                                            <x-badge variant="danger">{{ ucwords(str_replace('_', ' ', $request->status)) }}</x-badge>
                                        @elseif($request->status === 'fulfilled')
                                            <x-badge variant="primary">{{ ucwords(str_replace('_', ' ', $request->status)) }}</x-badge>
                                        @else
                                            <x-badge variant="default">{{ ucwords(str_replace('_', ' ', $request->status)) }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $request->requester->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $request->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                            <p>No requests found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @isset($requests)
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $requests->links() }}
                    </div>
                @endisset
            </div>
        </main>
    </div>
</x-app-layout>
