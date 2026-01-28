<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full">
    <div class="min-h-full">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900">API Documentation</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ config('app.name') }} v{{ config('app.version', '1.0') }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="/health" class="text-sm font-medium text-gray-700 hover:text-gray-900">Health Check</a>
                        <a href="/api" class="text-sm font-medium text-gray-700 hover:text-gray-900">API Status</a>
                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                            {{ $totalRoutes }} endpoints
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                <!-- Sidebar -->
                <aside class="hidden lg:block lg:col-span-3">
                    <x-framework::api-sidebar :groupedRoutes="$groupedRoutes" />
                </aside>

                <!-- Main Content -->
                <main class="lg:col-span-9">
                    @if (empty($routes))
                        <div class="rounded-lg bg-yellow-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">No API routes found</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Create API routes in your controllers using the #[HttpGet], #[HttpPost], etc. attributes.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="space-y-8">
                            @foreach ($groupedRoutes as $resource => $resourceRoutes)
                                <section id="{{ $resource }}" class="scroll-mt-8">
                                    <h2 class="text-2xl font-bold text-gray-900 capitalize mb-4 border-b pb-2">{{ $resource }}</h2>
                                    <div class="space-y-4">
                                        @foreach ($resourceRoutes as $route)
                                            <x-framework::api-route-card :route="$route" />
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @endif
                </main>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button 
        onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
        class="fixed bottom-8 right-8 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 transition-all duration-200 hidden"
        id="backToTop"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>

    <script>
        // Show/hide back to top button
        window.addEventListener('scroll', function() {
            const button = document.getElementById('backToTop');
            if (window.pageYOffset > 300) {
                button.classList.remove('hidden');
            } else {
                button.classList.add('hidden');
            }
        });
    </script>
</body>
</html>