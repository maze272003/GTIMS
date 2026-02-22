@props([
    'paginator' => null,
])

@if($paginator && $paginator->hasPages())
<nav {{ $attributes->merge(['class' => 'flex items-center justify-between']) }} aria-label="Pagination">
    <div class="hidden sm:block">
        <p class="text-sm text-gray-700 dark:text-gray-400">
            Showing <span class="font-medium">{{ $paginator->firstItem() }}</span>
            to <span class="font-medium">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium">{{ $paginator->total() }}</span> results
        </p>
    </div>
    <div class="flex items-center gap-1">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
            <span class="px-3 py-1.5 text-sm text-gray-400 dark:text-gray-600 cursor-not-allowed rounded-lg">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>
        @endif

        {{-- Page Numbers --}}
        @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if($page == $paginator->currentPage())
                <span class="px-3 py-1.5 text-sm font-medium bg-red-600 text-white rounded-lg">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">{{ $page }}</a>
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </a>
        @else
            <span class="px-3 py-1.5 text-sm text-gray-400 dark:text-gray-600 cursor-not-allowed rounded-lg">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </span>
        @endif
    </div>
</nav>
@endif
