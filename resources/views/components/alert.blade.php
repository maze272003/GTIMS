@props([
    'variant' => 'info',
    'dismissible' => false,
    'icon' => null,
])

@php
    $variants = [
        'success' => [
            'container' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300',
            'icon' => $icon ?? 'fa-solid fa-circle-check',
        ],
        'error' => [
            'container' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300',
            'icon' => $icon ?? 'fa-solid fa-circle-xmark',
        ],
        'warning' => [
            'container' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-300',
            'icon' => $icon ?? 'fa-solid fa-triangle-exclamation',
        ],
        'info' => [
            'container' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300',
            'icon' => $icon ?? 'fa-solid fa-circle-info',
        ],
    ];

    $config = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border p-4 text-sm ' . $config['container']]) }}
    @if($dismissible) x-data="{ show: true }" x-show="show" x-transition @endif
    role="alert"
>
    <div class="flex items-start gap-3">
        <i class="{{ $config['icon'] }} text-base mt-0.5 shrink-0"></i>
        <div class="flex-1">
            {{ $slot }}
        </div>
        @if($dismissible)
            <button @click="show = false" class="shrink-0 -mt-1 -mr-1 p-1 rounded hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        @endif
    </div>
</div>
