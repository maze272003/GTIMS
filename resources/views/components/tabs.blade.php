@props([
    'active' => null,
])

<div {{ $attributes }}>
    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-0 overflow-x-auto -mb-px" aria-label="Tabs">
            {{ $triggers ?? '' }}
        </nav>
    </div>

    {{-- Tab Content --}}
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
