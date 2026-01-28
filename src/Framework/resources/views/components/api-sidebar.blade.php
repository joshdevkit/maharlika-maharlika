<nav class="sticky top-4 space-y-1">
    <h3 class="px-3 text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Resources</h3>
    @foreach ($groupedRoutes as $resource => $resourceRoutes)
        <a 
            href="#{{ $resource }}" 
            class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-md text-gray-700 hover:text-gray-900 hover:bg-gray-100 transition-colors"
        >
            <span class="capitalize">{{ $resource }}</span>
            <span class="ml-3 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 group-hover:bg-gray-200">
                {{ count($resourceRoutes) }}
            </span>
        </a>
    @endforeach
</nav>