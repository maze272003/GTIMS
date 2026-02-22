@props([
    'headers' => [],
    'empty' => null,
    'emptyMessage' => 'No records found.',
    'emptyIcon' => 'fa-regular fa-inbox',
    'loading' => false,
    'striped' => false,
    'hoverable' => true,
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden']) }}>
    @isset($toolbar)
        <div class="px-4 py-3 sm:px-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            @if(count($headers) > 0)
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        @foreach($headers as $header)
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            @isset($head)
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    {{ $head }}
                </thead>
            @endisset

            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @if($loading)
                    <tr>
                        <td colspan="{{ count($headers) ?: 99 }}" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="animate-spin h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Loading…</span>
                            </div>
                        </td>
                    </tr>
                @elseif($empty)
                    <tr>
                        <td colspan="{{ count($headers) ?: 99 }}" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <i class="{{ $emptyIcon }} text-3xl text-gray-300 dark:text-gray-600"></i>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</span>
                            </div>
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @isset($pagination)
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            {{ $pagination }}
        </div>
    @endisset
</div>
