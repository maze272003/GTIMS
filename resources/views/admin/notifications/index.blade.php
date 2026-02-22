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
                            <x-badge variant="danger" size="md">{{ $unreadCount }} unread</x-badge>
                        @endif
                    </h2>
                </div>

                @if(isset($unreadCount) && $unreadCount > 0)
                    <form action="{{ route('admin.notifications.read-all') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center justify-center px-5 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200">
                            <i class="fa-solid fa-check-double mr-2"></i> Mark All as Read
                        </button>
                    </form>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($notifications ?? [] as $notification)
                    @php
                        $data = $notification->data ?? [];

                        // category/action_type are from your DB payload
                        $category = $data['type'] ?? $data['category'] ?? 'info';
                        $action   = $data['action_type'] ?? null;

                        // Human title
                        $title = $data['title']
                            ?? $data['message']
                            ?? (\Illuminate\Support\Str::headline($category) . ($action ? ' - ' . \Illuminate\Support\Str::headline($action) : ''));

                        // Pick "details": prefer details/body, else show remaining keys
                        $rawDetails = $data['details'] ?? ($data['body'] ?? null);

                        // If details/body is a JSON string, decode it
                        if (is_string($rawDetails)) {
                            $trim = trim($rawDetails);
                            if (($trim !== '') && (($trim[0] ?? '') === '{' || ($trim[0] ?? '') === '[')) {
                                $decoded = json_decode($rawDetails, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $rawDetails = $decoded;
                                }
                            }
                        }

                        // If still no details provided, build details from data excluding meta keys
                        if (is_null($rawDetails)) {
                            $rawDetails = $data;
                        }

                        // Remove meta keys from details display
                        if (is_array($rawDetails)) {
                            foreach (['type','title','message','body','category','action_type'] as $k) {
                                unset($rawDetails[$k]);
                            }
                        }

                        // Format helpers
                        $labelKey = function ($key) {
                            return \Illuminate\Support\Str::headline(str_replace(['-', '.'], '_', (string)$key));
                        };

                        $formatValue = function ($value) {
                            if (is_bool($value)) return $value ? 'Yes' : 'No';
                            if (is_null($value)) return '—';
                            if (is_array($value)) return json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                            return (string) $value;
                        };
                    @endphp

                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex items-start gap-4 transition hover:shadow-md {{ !$notification->read_at ? 'border-l-4 border-l-red-500' : '' }}">

                        <div class="flex-shrink-0 mt-1">
                            @if(\Illuminate\Support\Str::contains($category, 'low_stock'))
                                <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-triangle-exclamation text-orange-600 dark:text-orange-400"></i>
                                </div>
                            @elseif(\Illuminate\Support\Str::contains($category, 'approval'))
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <i class="fa-solid fa-clipboard-check text-blue-600 dark:text-blue-400"></i>
                                </div>
                            @elseif(\Illuminate\Support\Str::contains($category, 'hold') || \Illuminate\Support\Str::contains($category, 'expir'))
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
                                {{ $title }}
                            </p>

                            {{-- Pretty details (key/value grid) --}}
                            @if(is_array($rawDetails) && count($rawDetails))
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 text-xs">
                                    @foreach($rawDetails as $k => $v)
                                        <div class="flex gap-2 min-w-0">
                                            <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                {{ $labelKey($k) }}:
                                            </span>
                                            <span class="text-gray-700 dark:text-gray-200 break-words">
                                                {{ $formatValue($v) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif(!is_null($rawDetails) && $rawDetails !== '')
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $formatValue($rawDetails) }}
                                </p>
                            @endif

                            <p class="text-xs text-gray-400 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if(!$notification->read_at)
                                <form action="{{ route('admin.notifications.read', $notification->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm transition" title="Mark as read">
                                        <i class="fa-solid fa-envelope-open"></i>
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-300 dark:text-gray-600 text-sm" title="Read">
                                    <i class="fa-solid fa-check"></i>
                                </span>
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
