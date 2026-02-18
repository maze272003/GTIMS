<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <a href="{{ route('admin.audit.index') }}" class="hover:underline">Audit Log</a> / <span class="text-red-700 dark:text-red-300 font-medium">#{{ $audit->id }}</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Audit Entry #{{ $audit->id }}</h2>
                </div>
                <a href="{{ route('admin.audit.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg text-sm transition hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
            </div>

            {{-- Audit Info --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Action</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($audit->action === 'create') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                            @elseif($audit->action === 'update') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                            @elseif($audit->action === 'delete') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                            {{ ucfirst($audit->action) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Entity</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ class_basename($audit->entity_type ?? $audit->auditable_type ?? '-') }} #{{ $audit->entity_id ?? $audit->auditable_id ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">User</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $audit->user->name ?? '-' }}</p>
                        @if($audit->user)
                            <p class="text-xs text-gray-400">{{ $audit->user->email ?? '' }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $audit->created_at->format('M d, Y H:i:s') }}</p>
                    </div>
                    @if($audit->reason)
                        <div class="sm:col-span-2 lg:col-span-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Reason</p>
                            <p class="text-gray-700 dark:text-gray-300">{{ $audit->reason }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Before / After Diff --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
                        <h3 class="font-semibold text-red-800 dark:text-red-300 flex items-center gap-2">
                            <i class="fa-solid fa-minus-circle"></i> Before
                        </h3>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        @php $before = $audit->old_values ?? $audit->before ?? null; @endphp
                        @if($before)
                            <pre class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 rounded-lg p-4 whitespace-pre-wrap break-words font-mono">{{ is_array($before) ? json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $before }}</pre>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No previous data (new record).</p>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/20">
                        <h3 class="font-semibold text-green-800 dark:text-green-300 flex items-center gap-2">
                            <i class="fa-solid fa-plus-circle"></i> After
                        </h3>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        @php $after = $audit->new_values ?? $audit->after ?? null; @endphp
                        @if($after)
                            <pre class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 rounded-lg p-4 whitespace-pre-wrap break-words font-mono">{{ is_array($after) ? json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $after }}</pre>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No new data (deleted record).</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Metadata --}}
            @if($audit->metadata ?? $audit->ip_address ?? null)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-white mb-4">Metadata</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @if($audit->ip_address ?? null)
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">IP Address</p>
                                <p class="font-mono text-sm text-gray-900 dark:text-white">{{ $audit->ip_address }}</p>
                            </div>
                        @endif
                        @if($audit->user_agent ?? null)
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">User Agent</p>
                                <p class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ $audit->user_agent }}</p>
                            </div>
                        @endif
                        @if($audit->metadata)
                            <div class="sm:col-span-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Extra Data</p>
                                <pre class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 rounded-lg p-4 whitespace-pre-wrap break-words font-mono">{{ is_array($audit->metadata) ? json_encode($audit->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $audit->metadata }}</pre>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </main>
    </div>
</x-app-layout>
