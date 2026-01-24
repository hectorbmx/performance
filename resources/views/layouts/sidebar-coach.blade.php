@php
    $is = fn(string $pattern) => request()->routeIs($pattern);

    $linkClass = fn(bool $active) =>
        $active ? 'bg-gray-800 border-l-4 border-indigo-500' : 'hover:bg-gray-800';
@endphp

<aside
    x-data="{ open: true }"
    class="bg-gray-900 text-white min-h-screen transition-all duration-300"
    :class="open ? 'w-64' : 'w-20'"
>
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-700">
        <span class="font-bold text-lg" x-show="open">Coach</span>

        <button @click="open = !open" class="text-gray-400 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <nav class="mt-4 space-y-1">

        <a href="{{ route('coach.dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('coach.dashboard')) }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l9-9 9 9v9a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V12H9v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9z" />
            </svg>
            <span x-show="open">Dashboard</span>
        </a>
        {{-- Después del Dashboard y antes de Clientes --}}

            <a href="{{ route('coach.membresias.index') }}"
            class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('coach.membresias.*')) }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4" />
                </svg>
                <span x-show="open">Membresías</span>
            </a>

        {{-- placeholders futuros --}}
        <a href="{{ route('coach.clients.index') }}"
           class="flex items-center gap-3 px-4 py-3  {{ $linkClass($is('coach.clients.*')) }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" stroke-width="2" />
            </svg>
            <span x-show="open">Clientes</span>
        </a>

        <a href="{{ route('coach.trainings.index') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('coach.trainings.*')) }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z" />
            </svg>
            <span x-show="open">Entrenamientos</span>
        </a>

        <a href="{{ route('coach.groups.index') }}"
           class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('coach.groups.*')) }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 7h10M7 12h10M7 17h10" />
            </svg>
            <span x-show="open">Grupos</span>
        </a>
       <a href="{{ route('coach.config.index') }}"
   class="flex items-center gap-3 px-4 py-3 {{ $linkClass($is('coach.config.*')) }}">
    {{-- Icono (elige uno de los 3) --}}
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11.983 2.5l.478 1.91a7.93 7.93 0 0 1 1.78.74l1.78-1.02 1.6 1.6-1.02 1.78c.32.56.57 1.16.74 1.78l1.91.48v2.264l-1.91.478a7.93 7.93 0 0 1-.74 1.78l1.02 1.78-1.6 1.6-1.78-1.02a7.93 7.93 0 0 1-1.78.74l-.478 1.91h-2.264l-.48-1.91a7.93 7.93 0 0 1-1.78-.74l-1.78 1.02-1.6-1.6 1.02-1.78a7.93 7.93 0 0 1-.74-1.78L2.5 13.017v-2.264l1.91-.48c.17-.62.42-1.22.74-1.78L4.13 6.714l1.6-1.6 1.78 1.02c.56-.32 1.16-.57 1.78-.74l.48-1.91h2.213Z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Z" />
    </svg>

    <span x-show="open">Config</span>
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
