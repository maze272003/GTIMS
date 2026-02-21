<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Holds</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Hold Management</h2>
                </div>
                <a href="{{ route('admin.holds.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-red-700 hover:bg-red-800 text-white rounded-lg shadow-md transition-all duration-200">
                    <i class="fa-solid fa-plus mr-2"></i> Create Hold
                </a>
            </div>

            @if (session('success'))
                <script>document.addEventListener('DOMContentLoaded', function() { gtToast.success(@json(session('success'))); });</script>
            @endif

            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <form method="GET" action="{{ route('admin.holds.index') }}" class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Statuses</option>
                            @foreach(['pending', 'approved', 'released', 'expired', 'cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                        <select name="type" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Types</option>
                            @foreach(['reservation', 'quarantine', 'recall'] as $t)
                                <option value="{{ $t }}" {{ request('type') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch</label>
                        <select name="branch" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All Branches</option>
                            @isset($branches)
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            @endisset
                        </select>
                    </div>
                    <button type="submit" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2.5 rounded-lg text-sm transition">
                        <i class="fa-solid fa-filter mr-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.holds.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2.5 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600">
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
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Type</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Status</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Reason Code</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Created By</th>
                                <th class="p-3 text-gray-700 dark:text-gray-300 uppercase text-sm tracking-wide">Created At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($holds ?? [] as $hold)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150 cursor-pointer" onclick="window.location='{{ route('admin.holds.show', $hold->id) }}'">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">#{{ $hold->id }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $hold->branch->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($hold->type === 'reservation') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                            @elseif($hold->type === 'quarantine') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 @endif">
                                            {{ ucfirst($hold->type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($hold->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                            @elseif($hold->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300
                                            @elseif($hold->status === 'released') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                            @elseif($hold->status === 'expired') bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300
                                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 @endif">
                                            {{ ucfirst($hold->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $hold->reason_code ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $hold->creator->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $hold->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                            <p>No holds found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @isset($holds)
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $holds->links() }}
                    </div>
                @endisset
            </div>
        </main>
    </div>
</x-app-layout>
