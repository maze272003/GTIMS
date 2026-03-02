<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <a href="{{ route('admin.workflows.index') }}" class="hover:text-red-700 dark:hover:text-red-300">Automation</a> /
                    <a href="{{ route('admin.workflows.editor', $workflow) }}" class="hover:text-red-700 dark:hover:text-red-300">{{ $workflow->name }}</a> /
                    <span class="text-red-700 dark:text-red-300 font-medium">Run Logs</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-5">Run History — {{ $workflow->name }}</h2>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                @if($runs->isEmpty())
                    <div class="p-12 text-center">
                        <i class="fa-regular fa-clock-rotate-left text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No runs yet</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">This workflow hasn't been executed yet.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Run ID</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Trigger</th>
                                    <th class="px-6 py-3">Dry Run</th>
                                    <th class="px-6 py-3">Steps</th>
                                    <th class="px-6 py-3">Triggered By</th>
                                    <th class="px-6 py-3">Started</th>
                                    <th class="px-6 py-3">Duration</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($runs as $run)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-400">#{{ $run->id }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $run->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                            {{ $run->status === 'running' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                            {{ $run->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                            {{ $run->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                            {{ $run->status === 'cancelled' ? 'bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300' : '' }}">
                                            {{ ucfirst($run->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400 text-xs">{{ $run->trigger_type ?? 'manual' }}</td>
                                    <td class="px-6 py-4">
                                        @if($run->is_dry_run)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">Yes</span>
                                        @else
                                            <span class="text-gray-400 text-xs">No</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $run->steps->count() }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $run->triggeredBy?->name ?? 'System' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">{{ $run->started_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                        @if($run->started_at && $run->completed_at)
                                            {{ $run->started_at->diffForHumans($run->completed_at, true) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('admin.workflows.runs.show', [$workflow, $run]) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition">
                                            <i class="fa-solid fa-eye mr-1"></i> Details
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $runs->links() }}
                    </div>
                @endif
            </div>
        </main>
    </div>
</x-app-layout>
