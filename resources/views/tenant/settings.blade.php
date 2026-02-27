<x-app-layout>
<body class="bg-gray-50 dark:bg-gray-900 antialiased">
    <x-admin.sidebar/>

    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20 min-h-screen flex flex-col">
        <x-admin.header/>

        <main class="flex-1 pt-24 px-4 lg:px-8 pb-8">
            <div class="max-w-3xl">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tenant Settings</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Configure email branding and feature flags for the current tenant scope.
                </p>

                @if(session('success'))
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ tenant_route('tenant.settings.update') }}" class="mt-6 space-y-6 rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">From Name</label>
                            <input type="text" name="from_name" value="{{ old('from_name', $email['from_name'] ?? '') }}" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">From Address</label>
                            <input type="email" name="from_address" value="{{ old('from_address', $email['from_address'] ?? '') }}" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Feature Flags</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($features as $feature => $enabled)
                                <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                                    <span class="text-gray-700 dark:text-gray-200">{{ str_replace('_', ' ', ucfirst($feature)) }}</span>
                                    <input type="hidden" name="features[{{ $feature }}]" value="0">
                                    <input type="checkbox" name="features[{{ $feature }}]" value="1" {{ $enabled ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</x-app-layout>

