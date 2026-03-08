<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen" x-data="workflowRuns()">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <a href="{{ route('admin.workflows.index') }}" class="hover:text-red-700 dark:hover:text-red-300">Automation</a> /
                    <a href="{{ route('admin.workflows.editor', $workflow) }}" class="hover:text-red-700 dark:hover:text-red-300">{{ $workflow->name }}</a> /
                    <span class="text-red-700 dark:text-red-300 font-medium">Run Logs</span>
                </p>
                <div class="flex items-center justify-between mt-5">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Run History — {{ $workflow->name }}</h2>
                    <a href="{{ route('admin.workflows.dead-letter', $workflow) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition"
                       @click.prevent="showDeadLetter = true; loadDeadLetterRuns()">
                        <i class="fa-solid fa-skull-crossbones"></i>
                        Dead-Letter Queue
                        <span x-show="deadLetterCount > 0" x-text="deadLetterCount"
                              class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-600 rounded-full"></span>
                    </a>
                </div>
            </div>

            {{-- Dead-Letter Queue Drawer --}}
            <div x-show="showDeadLetter" x-cloak x-transition
                 class="mb-6 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-200 dark:border-red-800 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-red-200 dark:border-red-800">
                    <h3 class="text-lg font-semibold text-red-800 dark:text-red-300">
                        <i class="fa-solid fa-skull-crossbones mr-2"></i> Dead-Letter Queue
                    </h3>
                    <button @click="showDeadLetter = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <template x-if="deadLetterRuns.length === 0 && !loadingDeadLetter">
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <i class="fa-regular fa-circle-check text-3xl text-green-500 mb-3"></i>
                        <p>No dead-lettered runs. All clear!</p>
                    </div>
                </template>
                <template x-if="loadingDeadLetter">
                    <div class="p-8 text-center text-gray-500">
                        <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
                    </div>
                </template>
                <template x-if="deadLetterRuns.length > 0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-red-100/50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Run ID</th>
                                    <th class="px-6 py-3">Trigger</th>
                                    <th class="px-6 py-3">Error</th>
                                    <th class="px-6 py-3">Retries</th>
                                    <th class="px-6 py-3">Failed At</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-red-200 dark:divide-red-800">
                                <template x-for="dlRun in deadLetterRuns" :key="dlRun.id">
                                    <tr class="hover:bg-red-100/50 dark:hover:bg-red-900/20">
                                        <td class="px-6 py-3 font-mono text-xs" x-text="'#' + dlRun.id"></td>
                                        <td class="px-6 py-3 text-xs" x-text="dlRun.trigger_type || 'manual'"></td>
                                        <td class="px-6 py-3 text-xs max-w-xs truncate" x-text="dlRun.error_message || '-'"></td>
                                        <td class="px-6 py-3 text-xs" x-text="dlRun.retry_attempt + '/' + dlRun.max_retries"></td>
                                        <td class="px-6 py-3 text-xs" x-text="dlRun.completed_at ? new Date(dlRun.completed_at).toLocaleString() : '-'"></td>
                                        <td class="px-6 py-3 text-right">
                                            <button @click="rerunRun(dlRun.id)"
                                                    :disabled="rerunning === dlRun.id"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/40 transition disabled:opacity-50">
                                                <i class="fa-solid fa-rotate-right mr-1" :class="rerunning === dlRun.id ? 'fa-spin' : ''"></i>
                                                Rerun
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
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
                                    <td class="px-6 py-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        #{{ $run->id }}
                                        @if($run->is_dead_letter)
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400" title="Dead-lettered">DL</span>
                                        @endif
                                        @if($run->parent_run_id)
                                            <span class="ml-1 text-gray-400 text-[10px]" title="Rerun of #{{ $run->parent_run_id }}">&#8634;#{{ $run->parent_run_id }}</span>
                                        @endif
                                    </td>
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
                                    <td class="px-6 py-4 text-right space-x-1">
                                        @if(in_array($run->status, ['failed', 'cancelled']))
                                            <button @click="rerunRun({{ $run->id }})"
                                                    :disabled="rerunning === {{ $run->id }}"
                                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/40 transition disabled:opacity-50">
                                                <i class="fa-solid fa-rotate-right mr-1" :class="rerunning === {{ $run->id }} ? 'fa-spin' : ''"></i> Rerun
                                            </button>
                                        @endif
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

    @push('scripts')
    <script>
    function workflowRuns() {
        return {
            showDeadLetter: false,
            deadLetterRuns: [],
            deadLetterCount: 0,
            loadingDeadLetter: false,
            rerunning: null,

            init() {
                // Preload dead-letter count
                fetch('{{ route("admin.workflows.dead-letter", $workflow) }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => { this.deadLetterCount = data.runs?.total || data.runs?.data?.length || 0; })
                .catch(() => {});
            },

            async loadDeadLetterRuns() {
                this.loadingDeadLetter = true;
                try {
                    const res = await fetch('{{ route("admin.workflows.dead-letter", $workflow) }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    this.deadLetterRuns = data.runs?.data || [];
                    this.deadLetterCount = data.runs?.total || this.deadLetterRuns.length;
                } catch (e) {
                    console.error('Failed to load dead-letter runs', e);
                } finally {
                    this.loadingDeadLetter = false;
                }
            },

            async rerunRun(runId) {
                if (this.rerunning) return;
                this.rerunning = runId;

                try {
                    const res = await fetch(`{{ url('admin/workflows/' . $workflow->id . '/runs') }}/${runId}/rerun`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Rerun Started', text: 'New run #' + data.run.id + ' created.', timer: 2500 });
                        }
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Rerun Failed', text: data.error || 'Unknown error' });
                        }
                    }
                } catch (e) {
                    console.error('Rerun failed', e);
                } finally {
                    this.rerunning = null;
                }
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
