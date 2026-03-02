<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / <span class="text-red-700 dark:text-red-300 font-medium">Automation Builder</span>
                </p>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-5">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Automation Builder</h2>
                    <button onclick="document.getElementById('create-modal').classList.remove('hidden')"
                        class="mt-3 sm:mt-0 inline-flex items-center px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        <i class="fa-solid fa-plus mr-2"></i> New Workflow
                    </button>
                </div>
            </div>

            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <form method="GET" action="{{ route('admin.workflows.index') }}" class="flex flex-col sm:flex-row gap-4 items-end flex-wrap">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search workflows..."
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                    </div>
                    <div class="min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                            <option value="">All</option>
                            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="disabled" {{ request('status') === 'disabled' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition">
                        <i class="fa-solid fa-filter mr-1"></i> Filter
                    </button>
                </form>
            </div>

            {{-- Workflows Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                @if($workflows->isEmpty())
                    <div class="p-12 text-center">
                        <i class="fa-regular fa-diagram-project text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No workflows yet</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Create your first automation workflow to get started.</p>
                        <button onclick="document.getElementById('create-modal').classList.remove('hidden')"
                            class="inline-flex items-center px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                            <i class="fa-solid fa-plus mr-2"></i> Create Workflow
                        </button>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Name</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Version</th>
                                    <th class="px-6 py-3">Runs</th>
                                    <th class="px-6 py-3">Created By</th>
                                    <th class="px-6 py-3">Updated</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($workflows as $wf)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $wf->name }}</div>
                                        @if($wf->description)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Str::limit($wf->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $wf->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                            {{ $wf->status === 'draft' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                            {{ $wf->status === 'disabled' ? 'bg-gray-100 text-gray-600 dark:bg-gray-600 dark:text-gray-300' : '' }}">
                                            {{ ucfirst($wf->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">v{{ $wf->current_version }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $wf->runs_count }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">{{ $wf->creator->name ?? 'System' }}</td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">{{ $wf->updated_at->diffForHumans() }}</td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="{{ route('admin.workflows.editor', $wf) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                        </a>
                                        <a href="{{ route('admin.workflows.runs', $wf) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                                            <i class="fa-solid fa-play mr-1"></i> Runs
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $workflows->links() }}
                    </div>
                @endif
            </div>
        </main>
    </div>

    {{-- Create Workflow Modal --}}
    <div id="create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50" onclick="if(event.target===this) this.classList.add('hidden')">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Create New Workflow</h3>
                <button onclick="document.getElementById('create-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.workflows.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workflow Name *</label>
                    <input type="text" name="name" required maxlength="255" placeholder="e.g., Low Stock Alert Workflow"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" rows="3" maxlength="2000" placeholder="Describe what this workflow does..."
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="document.getElementById('create-modal').classList.add('hidden')"
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm transition">
                        Create Workflow
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
