<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">
            <div class="mb-6 pt-16">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / Settings / <span class="text-red-700 dark:text-red-300 font-medium">Branch Management</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-4">Branch Lifecycle Management</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Create branches, configure the designated main branch, and archive branches with transactional data migration.
                </p>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">Create New Branch</h3>
                    </div>
                    <form method="POST" action="{{ route('admin.branches.store') }}" class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch Name</label>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500"
                                placeholder="e.g. RHU 3"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code (optional)</label>
                            <input
                                type="text"
                                name="code"
                                value="{{ old('code') }}"
                                class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500"
                                placeholder="e.g. rhu-3"
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_main" id="is_main" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500" {{ old('is_main') ? 'checked' : '' }}>
                            <label for="is_main" class="text-sm text-gray-700 dark:text-gray-300">Set as main branch</label>
                        </div>
                        <div class="md:col-span-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm shadow">
                                <i class="fa-solid fa-plus mr-2"></i>Create Branch
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Main Branch</h3>
                    @if($mainBranch)
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $mainBranch->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Code: {{ $mainBranch->code }}</p>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            All archival migrations consolidate active inventory, pending transactions, and operational records into this branch.
                        </p>
                    @else
                        <p class="text-sm text-red-600">No active main branch configured.</p>
                    @endif
                </div>
            </div>

            @php
                $latestRunBySource = $runs->groupBy('source_branch_id')->map(fn($collection) => $collection->first());
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Branch Status</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">Branch</th>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Data Counts</th>
                                <th class="px-4 py-3">Migration Progress</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($branches as $branch)
                                @php
                                    $run = $latestRunBySource->get($branch->id);
                                    $progress = (int) ($run?->progress_percent ?? 0);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $branch->name }}</p>
                                        @if($branch->is_main)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mt-1">Main</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $branch->code }}</td>
                                    <td class="px-4 py-3">
                                        @if($branch->is_archived)
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-200 text-gray-700">Archived</span>
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ optional($branch->archived_at)->format('Y-m-d H:i') ?? 'N/A' }}
                                            </p>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                        Users: {{ $branch->users_count }}<br>
                                        Inventory: {{ $branch->inventories_count }}<br>
                                        Orders: {{ $branch->orders_count }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($run)
                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div class="h-2 rounded-full {{ $run->status === 'failed' ? 'bg-red-500' : ($run->status === 'completed' ? 'bg-green-500' : 'bg-blue-500') }}" style="width: {{ $progress }}%"></div>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                                {{ ucfirst(str_replace('_', ' ', $run->status)) }} ({{ $progress }}%)
                                            </p>
                                        @else
                                            <span class="text-xs text-gray-400">No migration run</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @if(!$branch->is_archived && !$branch->is_main)
                                                <form method="POST" action="{{ route('admin.branches.set-main', $branch->id) }}">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1 text-xs rounded border border-blue-300 text-blue-700 hover:bg-blue-50">
                                                        Set Main
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.branches.archive', $branch->id) }}" onsubmit="return confirm('Archive {{ $branch->name }} and migrate all data to {{ $mainBranch?->name ?? 'main branch' }}?');">
                                                    @csrf
                                                    <input type="hidden" name="target_main_branch_id" value="{{ $mainBranch?->id }}">
                                                    <input type="hidden" name="reason" value="Super Admin archival action">
                                                    <button type="submit" class="px-3 py-1 text-xs rounded border border-red-300 text-red-700 hover:bg-red-50 {{ $mainBranch ? '' : 'opacity-50 cursor-not-allowed' }}" {{ $mainBranch ? '' : 'disabled' }}>
                                                        Archive
                                                    </button>
                                                </form>
                                            @endif

                                            @if($run && $run->status === 'failed')
                                                <form method="POST" action="{{ route('admin.branches.rollback', $run->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Manual rollback marker from branch management interface">
                                                    <button type="submit" class="px-3 py-1 text-xs rounded border border-amber-300 text-amber-700 hover:bg-amber-50">
                                                        Mark Rolled Back
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No branches available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Recent Archival Runs</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">Run ID</th>
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Checksums</th>
                                <th class="px-4 py-3">Started</th>
                                <th class="px-4 py-3">Completed</th>
                                <th class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($runs as $run)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs">#{{ $run->id }}</td>
                                    <td class="px-4 py-3">{{ $run->sourceBranch?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">{{ $run->targetBranch?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $run->status === 'completed' ? 'bg-green-100 text-green-800' : ($run->status === 'failed' ? 'bg-red-100 text-red-800' : ($run->status === 'rolled_back' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800')) }}">
                                            {{ ucfirst(str_replace('_', ' ', $run->status)) }}
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1">{{ $run->progress_percent }}%</p>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        <div>Before: {{ $run->before_checksum ? substr($run->before_checksum, 0, 10).'...' : 'N/A' }}</div>
                                        <div>After: {{ $run->after_checksum ? substr($run->after_checksum, 0, 10).'...' : 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ optional($run->started_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-xs">{{ optional($run->completed_at)->format('Y-m-d H:i:s') ?? optional($run->failed_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">
                                        @if($run->status === 'failed')
                                            <form method="POST" action="{{ route('admin.branches.rollback', $run->id) }}">
                                                @csrf
                                                <input type="hidden" name="reason" value="Rollback initiated from archival run table">
                                                <button type="submit" class="px-3 py-1 text-xs rounded border border-amber-300 text-amber-700 hover:bg-amber-50">
                                                    Rollback
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">No archival runs yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</x-app-layout>

