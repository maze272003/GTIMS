<x-app-layout>
    <x-admin.sidebar/>
    <div id="content-wrapper" class="transition-all duration-300 lg:ml-64 md:ml-20">
        <x-admin.header/>
        <main id="main-content" class="pt-20 p-4 lg:p-8 min-h-screen">

            <div class="mb-6 pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-20">
                <div class="flex flex-col gap-5">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Home / <span class="text-red-700 dark:text-red-300 font-medium">Notifications</span>
                    </p>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-3">
                        Notifications
                        @if(isset($unreadCount) && $unreadCount > 0)
                            <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 text-sm font-medium px-2.5 py-0.5 rounded-full">{{ $unreadCount }} unread</span>
                        @endif
                    </h2>
                </div>
                @if(isset($unreadCount) && $unreadCount > 0)
                    <form action="{{ route('admin.notifications.mark-all-read') }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200">
                            <i class="fa-solid fa-check-double mr-2"></i> Mark All as Read
                        </button>
                    </form>
                @endif
            </div>

            @if (session('success'))
                <div id="successAlert" class="fixed top-24 right-5 border-l-4 border-green-500 bg-white text-green-700 py-3 px-6 rounded-lg shadow-lg z-50 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-2xl"></i>
                    <div><p class="font-bold">Success!</p><p class="text-black">{{ session('success') }}</p></div>
                </div>
                <script>setTimeout(() => { const a = document.getElementById('successAlert'); if (a) a.remove(); }, 4000);</script>
            @endif

            <div class="space-y-3">
                @forelse($notifications ?? [] as $notification)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-start gap-4 transition hover:shadow-md {{ !$notification->read_at ? 'border-l-4 border-l-red-500' : '' }}">
                        <div class="flex-shrink-0 mt-1">
                            @php $type = $notification->data['type'] ?? $notification->type ?? 'info'; @endphp
                            @if(str_contains($type, 'low_stock'))
                                <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-triangle-exclamation text-orange-600 dark:text-orange-400"></i>
                                </div>
                            @elseif(str_contains($type, 'approval'))
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-clipboard-check text-blue-600 dark:text-blue-400"></i>
                                </div>
                            @elseif(str_contains($type, 'hold') || str_contains($type, 'expir'))
                                <div class="w-10 h-10 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-clock text-red-600 dark:text-red-400"></i>
                                </div>
                            @else
                                <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-bell text-gray-600 dark:text-gray-400"></i>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white {{ !$notification->read_at ? '' : 'font-normal' }}">
                                {{ $notification->data['title'] ?? $notification->data['message'] ?? 'Notification' }}
                            </p>
                            @if(isset($notification->data['body']))
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $notification->data['body'] }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if(!$notification->read_at)
                                <form action="{{ route('admin.notifications.mark-read', $notification->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm transition" title="Mark as read">
                                        <i class="fa-solid fa-envelope-open"></i>
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-300 dark:text-gray-600 text-sm"><i class="fa-solid fa-check"></i></span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-10 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fa-regular fa-bell-slash text-4xl mb-3 text-gray-300"></i>
                            <p class="text-gray-500 dark:text-gray-400">No notifications.</p>
                        </div>
                    </div>
                @endforelse
            </div>

            @isset($notifications)
                @if(method_exists($notifications, 'links'))
                    <div class="mt-6">
                        {{ $notifications->links() }}
                    </div>
                @endif
            @endisset

        </main>
    </div>
</x-app-layout>
