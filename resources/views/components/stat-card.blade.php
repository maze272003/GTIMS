@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'iconBg' => 'bg-gray-100 dark:bg-gray-700',
    'iconColor' => 'text-gray-600 dark:text-gray-400',
    'trend' => null,
    'trendUp' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5']) }}>
    <div class="flex items-start gap-3">
        @if($icon)
            <div class="shrink-0 w-10 h-10 rounded-lg {{ $iconBg }} flex items-center justify-center">
                <i class="{{ $icon }} text-lg {{ $iconColor }}"></i>
            </div>
        @endif
        <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</p>
            <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 mt-0.5">{{ $value }}</p>
            @if($trend)
                <p class="text-xs mt-1 {{ $trendUp ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    <i class="fa-solid fa-arrow-{{ $trendUp ? 'up' : 'down' }} mr-0.5"></i>
                    {{ $trend }}
                </p>
            @endif
        </div>
    </div>
    @isset($footer)
        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            {{ $footer }}
        </div>
    @endisset
</div>
