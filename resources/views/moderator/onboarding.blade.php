<x-app-layout>
<body class="bg-gray-50 dark:bg-gray-900 antialiased">
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20 min-h-screen flex flex-col">
        <x-admin.header/>

        <main class="flex-1 pt-24 px-4 lg:px-8 pb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tenant Onboarding</h1>

            @if(session('success'))
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 overflow-hidden rounded-xl bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Province</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Current Step</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Completed</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Advance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($records as $record)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $record->province?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $record->status }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $record->current_step ?? 'complete' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ implode(', ', (array) $record->completed_steps) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <form method="POST" action="{{ route('moderator.onboarding.advance', $record) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="step" class="rounded border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                            @foreach(\App\Services\TenantOnboardingService::STEPS as $step)
                                                <option value="{{ $step }}">{{ $step }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Complete Step</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $records->links() }}</div>
        </main>
    </div>
</body>
</x-app-layout>

