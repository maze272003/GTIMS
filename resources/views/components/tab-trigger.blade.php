@props([
    'active' => false,
    'tab' => '',
])

@php
    $classes = $active
        ? 'border-red-500 text-red-600 dark:text-red-400'
        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600';
@endphp

<button
    {{ $attributes->merge([
        'class' => 'whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ' . $classes,
        'type' => 'button',
    ]) }}
>
    {{ $slot }}
</button>
