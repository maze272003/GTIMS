<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <a href="{{ route('admin.workflows.index') }}" class="hover:text-red-700 dark:hover:text-red-300">Automation</a> /
                    <a href="{{ route('admin.workflows.editor', $workflow) }}" class="hover:text-red-700 dark:hover:text-red-300">{{ $workflow->name }}</a> /
                    <a href="{{ route('admin.workflows.runs', $workflow) }}" class="hover:text-red-700 dark:hover:text-red-300">Runs</a> /
                    <span class="text-red-700 dark:text-red-300 font-medium">Run #{{ $run->id }}</span>
                </p>
                <div class="flex items-center gap-3 mt-5">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Run #{{ $run->id }}</h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        {{ $run->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                        {{ $run->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                        {{ $run->status === 'running' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                        {{ $run->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}">
                        {{ ucfirst($run->status) }}
                    </span>
                    @if($run->is_dry_run)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                            Dry Run
                        </span>
                    @endif
                </div>
            </div>

            {{-- Run Summary --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Trigger</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-1">{{ $run->trigger_type ?? 'Manual' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Triggered By</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-1">{{ $run->triggeredBy?->name ?? 'System' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Started</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-1">{{ $run->started_at?->format('M d, H:i:s') ?? '-' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Duration</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-1">
                        @if($run->started_at && $run->completed_at)
                            {{ $run->started_at->diffForHumans($run->completed_at, true) }}
                        @else
                            -
                        @endif
                    </p>
                </div>
            </div>

            {{-- Error Message --}}
            @if($run->error_message)
            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
                <p class="text-sm font-semibold text-red-700 dark:text-red-400">Error:</p>
                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $run->error_message }}</p>
            </div>
            @endif

            @php
                $completion = data_get($run->context, '_completion', []);
                $debugTrace = data_get($run->context, '_debug_trace', []);
            @endphp

            {{-- Completion Criteria --}}
            @if(!empty($completion))
                <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Completion Criteria</h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium {{ data_get($completion, 'all_criteria_met') ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                            {{ data_get($completion, 'all_criteria_met') ? 'Passed' : 'Failed' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ data_get($completion, 'summary') }}</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 text-xs">
                        <div class="bg-gray-50 dark:bg-gray-700/30 rounded p-2">
                            <p class="text-gray-500 dark:text-gray-400">Finalized Nodes</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ data_get($completion, 'finalized_nodes', 0) }} / {{ data_get($completion, 'node_count', 0) }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/30 rounded p-2">
                            <p class="text-gray-500 dark:text-gray-400">Parallel Stages</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ data_get($completion, 'parallel_stages', 0) }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/30 rounded p-2">
                            <p class="text-gray-500 dark:text-gray-400">Notifications</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ data_get($completion, 'notifications_sent') ? 'Dispatched' : 'Not Sent' }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/30 rounded p-2">
                            <p class="text-gray-500 dark:text-gray-400">Error Resolution</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ data_get($completion, 'error_states_resolved') ? 'Resolved' : 'Unresolved' }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Debug Trace --}}
            @if(is_array($debugTrace) && count($debugTrace) > 0)
                <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Debug Trace</h3>
                    <div class="max-h-72 overflow-y-auto space-y-2">
                        @foreach(array_slice($debugTrace, -80) as $entry)
                            <div class="text-xs bg-gray-50 dark:bg-gray-700/30 rounded p-2 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ strtoupper((string) data_get($entry, 'status', 'event')) }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ data_get($entry, 'timestamp') }}</span>
                                </div>
                                <p class="text-gray-600 dark:text-gray-300 mt-1">{{ data_get($entry, 'message', '') }}</p>
                                @if(data_get($entry, 'node_id'))
                                    <p class="text-gray-500 dark:text-gray-400 mt-1">Node: {{ data_get($entry, 'node_id') }} ({{ data_get($entry, 'action_type') }})</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Step Timeline --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Step Timeline</h3>
                </div>

                @if($run->steps->isEmpty())
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <p>No steps recorded for this run.</p>
                    </div>
                @else
                    <div class="p-6">
                        <div class="space-y-4">
                            @foreach($run->steps->sortBy('created_at') as $step)
                            <div class="flex items-start gap-4">
                                {{-- Status Icon --}}
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center
                                    {{ $step->status === 'completed' ? 'bg-green-100 dark:bg-green-900/30' : '' }}
                                    {{ $step->status === 'failed' ? 'bg-red-100 dark:bg-red-900/30' : '' }}
                                    {{ $step->status === 'running' ? 'bg-blue-100 dark:bg-blue-900/30' : '' }}
                                    {{ $step->status === 'skipped' ? 'bg-gray-100 dark:bg-gray-700' : '' }}
                                    {{ $step->status === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900/30' : '' }}">
                                    @if($step->status === 'completed')
                                        <i class="fa-solid fa-check text-green-600 dark:text-green-400 text-xs"></i>
                                    @elseif($step->status === 'failed')
                                        <i class="fa-solid fa-xmark text-red-600 dark:text-red-400 text-xs"></i>
                                    @elseif($step->status === 'running')
                                        <i class="fa-solid fa-spinner fa-spin text-blue-600 dark:text-blue-400 text-xs"></i>
                                    @else
                                        <i class="fa-solid fa-circle text-gray-400 text-xs"></i>
                                    @endif
                                </div>

                                {{-- Step Details --}}
                                <div class="flex-1 bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $step->action_type }}</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">({{ $step->node_id }})</span>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $step->status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                            {{ $step->status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                            {{ $step->status === 'skipped' ? 'bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300' : '' }}">
                                            {{ ucfirst($step->status) }}
                                        </span>
                                    </div>

                                    @if($step->error_message)
                                        <p class="text-sm text-red-600 dark:text-red-400 mb-2">{{ $step->error_message }}</p>
                                    @endif

                                    @if($step->output_snapshot)
                                        <details class="mt-2">
                                            <summary class="text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">Output</summary>
                                            <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto text-gray-600 dark:text-gray-400">{{ json_encode($step->output_snapshot, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endif

                                    <div class="flex gap-4 mt-2 text-xs text-gray-400 dark:text-gray-500">
                                        @if($step->started_at)
                                            <span>Started: {{ $step->started_at->format('H:i:s') }}</span>
                                        @endif
                                        @if($step->completed_at)
                                            <span>Completed: {{ $step->completed_at->format('H:i:s') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>
</x-app-layout>
