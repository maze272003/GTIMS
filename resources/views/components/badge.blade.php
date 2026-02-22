@props([
    'variant' => 'default',
    'size' => 'sm',
])

@php
    $variants = [
        'default'  => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 ring-gray-500/10',
        'primary'  => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 ring-red-600/10',
        'success'  => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300 ring-green-600/10',
        'warning'  => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 ring-yellow-600/10',
        'danger'   => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 ring-red-600/10',
        'info'     => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 ring-blue-600/10',
    ];

    $sizes = [
        'xs' => 'px-1.5 py-0.5 text-xs',
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-sm',
    ];

    $classes = 'inline-flex items-center font-medium rounded-full ring-1 ring-inset '
        . ($variants[$variant] ?? $variants['default']) . ' '
        . ($sizes[$size] ?? $sizes['sm']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
