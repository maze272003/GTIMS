<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 mt-20">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Home / Notifications / <span class="text-red-700 dark:text-red-300 font-medium">Preferences</span>
                </p>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-5">Notification Preferences</h2>
            </div>

            @if (session('success'))
                <div id="successAlert" class="fixed top-24 right-5 border-l-4 border-green-500 bg-white text-green-700 py-3 px-6 rounded-lg shadow-lg z-50 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-2xl"></i>
                    <div><p class="font-bold">Success!</p><p class="text-black">{{ session('success') }}</p></div>
                </div>
                <script>setTimeout(() => { const a = document.getElementById('successAlert'); if (a) a.remove(); }, 4000);</script>
            @endif

            <form action="{{ route('admin.notifications.preferences.update') }}" method="POST">
                @csrf
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Email Notification Settings</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Choose which notifications you want to receive via email.</p>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @php
                            $notificationTypes = [
                                'low_stock' => ['label' => 'Low Stock Alerts', 'description' => 'Get notified when inventory items fall below the threshold.', 'icon' => 'fa-triangle-exclamation', 'color' => 'orange'],
                                'approval_needed' => ['label' => 'Approval Needed', 'description' => 'Get notified when orders or requests need your approval.', 'icon' => 'fa-clipboard-check', 'color' => 'blue'],
                                'hold_expiry' => ['label' => 'Hold Expiry', 'description' => 'Get notified when inventory holds are about to expire.', 'icon' => 'fa-clock', 'color' => 'red'],
                                'request_status' => ['label' => 'Request Status Changes', 'description' => 'Get notified when your requests change status.', 'icon' => 'fa-rotate', 'color' => 'purple'],
                            ];
                        @endphp

                        @foreach($notificationTypes as $key => $config)
                            <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-{{ $config['color'] }}-100 dark:bg-{{ $config['color'] }}-900 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid {{ $config['icon'] }} text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $config['label'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $config['description'] }}</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="preferences[{{ $key }}][email_enabled]" value="0">
                                    <input type="checkbox" name="preferences[{{ $key }}][email_enabled]" value="1"
                                        class="sr-only peer"
                                        {{ (isset($preferences) && ($preferences[$key]['email_enabled'] ?? false)) ? 'checked' : '' }}>
                                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 dark:peer-focus:ring-red-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 pb-10">
                    <a href="{{ route('admin.notifications.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</a>
                    <button type="submit" class="px-6 py-2.5 bg-red-700 hover:bg-red-800 text-white rounded-lg shadow-md transition">
                        <i class="fa-solid fa-save mr-1"></i> Save Preferences
                    </button>
                </div>
            </form>

        </main>
    </div>
</x-app-layout>
