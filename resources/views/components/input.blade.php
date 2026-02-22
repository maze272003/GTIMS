@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'disabled' => false,
    'required' => false,
])

@php
    $inputName = $name ?? $attributes->get('id');
    $hasError = $error || ($inputName && $errors->has($inputName));
    $errorMsg = $error ?? ($inputName ? $errors->first($inputName) : null);

    $inputClasses = 'block w-full rounded-lg border shadow-sm text-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-200 '
        . ($hasError
            ? 'border-red-300 dark:border-red-600 text-red-900 dark:text-red-300 focus:border-red-500 focus:ring-red-500/20'
            : 'border-gray-300 dark:border-gray-600 focus:border-red-500 focus:ring-red-500/20');
@endphp

<div>
    @if($label)
        <label @if($inputName) for="{{ $inputName }}" @endif class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        @if($inputName) name="{{ $inputName }}" id="{{ $inputName }}" @endif
        @disabled($disabled)
        {{ $attributes->merge(['class' => $inputClasses]) }}
    >

    @if($hint && !$hasError)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    @if($hasError)
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $errorMsg }}</p>
    @endif
</div>
