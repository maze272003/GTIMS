<x-app-layout>
<body class="bg-gray-50 dark:bg-gray-900 antialiased">
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20 min-h-screen flex flex-col">
        <x-admin.header/>

        <main class="flex-1 pt-24 px-4 lg:px-8 pb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Moderator Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Platform-wide status, onboarding, and incident visibility.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-5">
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-800">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Provinces</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $widgets['provinces'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-800">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Barangays</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $widgets['barangays'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-800">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Active Users</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $widgets['active_users'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-800">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Open Incidents</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">{{ $widgets['open_incidents'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-800">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Onboarding Pending</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ $widgets['onboarding_pending'] }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Health Summary</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Healthy checks: <strong>{{ $healthSummary['healthy'] }}</strong>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Degraded checks: <strong>{{ $healthSummary['degraded'] }}</strong>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        Critical checks: <strong>{{ $healthSummary['critical'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Failed Jobs by Tenant</h2>
                @if(empty($failedJobsByTenant))
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">No failed jobs detected in recent samples.</p>
                @else
                    <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Scope</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Failed Jobs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($failedJobsByTenant as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $row['scope'] }}</td>
                                        <td class="px-3 py-2 text-sm font-semibold text-red-600">{{ $row['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </main>
    </div>
</body>
</x-app-layout>
