@props(['routeName', 'activePattern', 'pendingCount' => 0])

@php
    $pendingCount = (int) $pendingCount;
    $pendingLabel = $pendingCount > 99 ? '99+' : (string) $pendingCount;
@endphp

<a href="{{ route($routeName) }}"
   title="Tips"
   aria-label="{{ $pendingCount > 0 ? 'Tips, '.$pendingCount.' pendientes de aprobación' : 'Tips' }}"
   @if(request()->routeIs($activePattern)) aria-current="page" @endif
   class="flex items-center justify-between gap-3 px-4 py-3 {{ request()->routeIs($activePattern) ? 'bg-gray-800 border-l-4 border-indigo-500' : 'hover:bg-gray-800' }}">
    <span class="flex min-w-0 items-center gap-3">
        <span class="relative shrink-0">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 18h6m-5 3h4M8 14a6 6 0 1 1 8 0c-1 1-1 2-1 2H9s0-1-1-2ZM12 1V0M3 9H1m22 0h-2M5 3 3.5 1.5M19 3l1.5-1.5" />
            </svg>
            @if($pendingCount > 0)
                <span class="absolute -right-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-gray-900" x-show="! open">
                    {{ $pendingLabel }}
                </span>
            @endif
        </span>
        <span x-show="open">Tips</span>
    </span>
    @if($pendingCount > 0)
        <span x-show="open" class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold leading-5 text-white">
            {{ $pendingLabel }}
        </span>
    @endif
</a>
