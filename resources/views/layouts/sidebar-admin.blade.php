@php
    $is = function(string $pattern) {
        return request()->routeIs($pattern);
    };

    $linkClass = function(bool $active) {
        return $active
            ? 'bg-gray-800 border-l-4 border-indigo-500'
            : 'hover:bg-gray-800';
    };
@endphp

<aside
    x-data="{ open: true }"
    class="bg-gray-900 text-white min-h-screen transition-all duration-300"
    :class="open ? 'w-64' : 'w-20'"
>

    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-700">
        <span class="font-bold text-lg" x-show="open">CoachSaaS</span>

        <button @click="open = !open" class="text-gray-400 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Menu -->
    <nav class="mt-4 space-y-1">

        {{-- Dashboard --}}
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('admin.dashboard')) }}">

            <i class="fas fa-home text-lg"></i>
            <span x-show="open">Dashboard</span>
        </a>

        {{-- Coaches --}}
        <a href="{{ route('admin.coaches.index') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('admin.coaches.index')) }}">
           <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
  <circle cx="9" cy="7" r="4" stroke-width="2" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M22 21v-2a4 4 0 0 0-3-3.87" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M16 3.13a4 4 0 0 1 0 7.75" />
</svg>

            <span x-show="open">Coaches</span>
        </a>

        {{-- Planes --}}
        <a href="{{ route('admin.plans.index') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('admin.plans.index')) }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M9 12h6M9 16h6M9 8h6" />
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M7 4h10a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2z" />
</svg>

            <span x-show="open">Planes</span>
        </a>

        {{-- Suscripciones --}}
            <a href="{{ route('admin.subscriptions.index') }}"
            class="flex items-center justify-between px-4 py-3 {{ $linkClass($is('admin.subscriptions.*')) }}">

                <div class="flex items-center gap-3">
                    {{-- Icon --}}
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z" />
                    </svg>

                    <span x-show="open">Suscripciones</span>
                </div>

                {{-- Badges --}}
                <div x-show="open" class="flex gap-1">
                    @if(($sidebarUnpaidCount ?? 0) > 0)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-red-600 text-white">
                            {{ $sidebarUnpaidCount }}
                        </span>
                    @endif

                    @if(($sidebarGraceCount ?? 0) > 0)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-400 text-black">
                            {{ $sidebarGraceCount }}
                        </span>
                    @endif
                </div>
            </a>


        {{-- Pagos --}}
        <a href="{{ route('admin.payments.index') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('admin.payments.index')) }}">
       <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
  <rect x="2" y="5" width="20" height="14" rx="2" ry="2" stroke-width="2"></rect>
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M2 10h20" />
</svg>


            <span x-show="open">Pagos</span>
        </a>
<form method="POST" action="{{ route('logout') }}" class="mt-6 px-4">
    @csrf

    <button type="submit"
        class="w-full flex items-center gap-3 px-4 py-3 text-left text-red-400 hover:bg-gray-800 hover:text-red-300 rounded-md">

        {{-- Icon --}}
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1" />
        </svg>

        <span x-show="open">Cerrar sesión</span>
    </button>
</form>

    </nav>
</aside>
