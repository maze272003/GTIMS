@props([
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm']) }}>
    @isset($header)
        <div class="px-4 py-3 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            {{ $header }}
        </div>
    @endisset

    <div @class([
        'px-4 py-4 sm:px-6' => $padding,
    ])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-4 py-3 sm:px-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl">
            {{ $footer }}
        </div>
    @endisset
</div>
