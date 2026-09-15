@props(['routeName', 'activePattern'])

<a href="{{ route($routeName) }}"
   title="Tips"
   aria-label="Tips"
   @if(request()->routeIs($activePattern)) aria-current="page" @endif
   class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs($activePattern) ? 'bg-gray-800 border-l-4 border-indigo-500' : 'hover:bg-gray-800' }}">
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 18h6m-5 3h4M8 14a6 6 0 1 1 8 0c-1 1-1 2-1 2H9s0-1-1-2ZM12 1V0M3 9H1m22 0h-2M5 3 3.5 1.5M19 3l1.5-1.5" />
    </svg>
    <span x-show="open">Tips</span>
</a>
