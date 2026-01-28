<nav class="flex items-center justify-between px-4 py-3">
    @if ($paginator->onFirstPage())
        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 cursor-not-allowed rounded-md">
            Previous
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
            Previous
        </a>
    @endif

    <div class="text-sm text-gray-700">
        Page <span class="font-medium">{{ $paginator->currentPage() }}</span> of <span class="font-medium">{{ $paginator->lastPage() }}</span>
    </div>

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
            Next
        </a>
    @else
        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 cursor-not-allowed rounded-md">
            Next
        </span>
    @endif
</nav>