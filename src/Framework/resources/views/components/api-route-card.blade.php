<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow duration-200">
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-3">
                <x-framework::api-method-badge :method="$route['method']" />
                <code class="text-sm font-mono text-gray-900 bg-gray-50 px-3 py-1 rounded">{{ $route['uri'] }}</code>
            </div>
            
            @if (isset($route['name']) && $route['name'])
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    <span class="font-medium">{{ $route['name'] }}</span>
                </div>
            @endif

            @if (is_array($route['action']))
                <div class="text-sm text-gray-500">
                   <span class="font-mono">{{ $route['action'][0] . '@' . $route['action'][1] }}</span>
                </div>
            @endif
        </div>

        <button 
            onclick="copyToClipboard('{{ $route['uri'] }}')" 
            class="ml-4 text-gray-400 hover:text-gray-600 transition-colors"
            title="Copy endpoint URL"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
        </button>
    </div>
</div>

<script>
function copyToClipboard(text) {
    const fullUrl = window.location.origin + text;
    navigator.clipboard.writeText(fullUrl).then(() => {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg transition-opacity duration-300';
        toast.textContent = 'Copied to clipboard!';
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => document.body.removeChild(toast), 300);
        }, 2000);
    });
}
</script>