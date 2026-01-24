<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Entrenamientos</h1>
                <p class="text-sm text-gray-600">Gestiona tus entrenamientos por fecha y secciones.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('coach.trainings.index', array_merge(request()->query(), ['view' => 'list'])) }}"
                   class="px-3 py-2 rounded-lg border text-sm {{ $view === 'list' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700' }}">
                    Lista
                </a>

                <a href="{{ route('coach.trainings.index', array_merge(request()->query(), ['view' => 'calendar'])) }}"
                   class="px-3 py-2 rounded-lg border text-sm {{ $view === 'calendar' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700' }}">
                    Calendario
                </a>

                @if($view === 'calendar')
                    <a href="{{ route('coach.trainings.create', ['date' => $currentMonth->toDateString()]) }}"
                       class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">
                        + Nuevo
                    </a>
                @else
                    <a href="{{ route('coach.trainings.create') }}"
                       class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium">
                        + Nuevo
                    </a>
                @endif
            </div>
        </div>

        {{-- Filtros: solo para LISTA --}}
        @if($view !== 'calendar')
            <div class="mt-6 bg-white border rounded-xl p-4">
                <form method="GET" action="{{ route('coach.trainings.index') }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="view" value="{{ $view }}"/>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Fecha (opcional)</label>
                        <input type="date" name="date" value="{{ $date }}"
                               class="h-10 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
                    </div>

                    <button class="h-10 px-4 rounded-lg bg-gray-900 text-white text-sm">Filtrar</button>

                    <a href="{{ route('coach.trainings.index', ['view' => $view]) }}"
                       class="h-10 px-4 rounded-lg border text-sm text-gray-700 inline-flex items-center">
                        Limpiar
                    </a>
                </form>
            </div>
        @endif

        @if($view === 'calendar')
            @php
                $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
                $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

                $cursor = $start->copy();
                $days = [];
                while ($cursor->lte($end)) {
                    $days[] = $cursor->copy();
                    $cursor->addDay();
                }

                // Semana inicia en Lunes (consistente con startOfWeek(Carbon::MONDAY))
                $weekdays = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

                // Fallback por si Tailwind no trae aspect-square
                // (si tu tailwind sí lo trae, igual funciona)
                $useAspectSquare = true;
            @endphp

            <div class="mt-6 bg-white border rounded-xl overflow-hidden">
                {{-- Barra superior (estilo imagen 2) --}}
                <div class="px-4 py-4 bg-indigo-600 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('coach.trainings.index', ['view' => 'calendar', 'month' => $prevMonth]) }}"
                           class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20">
                            ‹
                        </a>

                        <div class="text-lg font-semibold">
                            {{ $currentMonth->translatedFormat('F Y') }}
                        </div>

                        <a href="{{ route('coach.trainings.index', ['view' => 'calendar', 'month' => $nextMonth]) }}"
                           class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20">
                            ›
                        </a>
                    </div>

                    <a href="{{ route('coach.trainings.create', ['date' => $currentMonth->toDateString()]) }}"
                       class="px-4 py-2 rounded-lg bg-white text-indigo-700 text-sm font-semibold">
                        + Nuevo
                    </a>
                </div>

                {{-- Header de weekdays (barra azul) --}}
                <div class="grid grid-cols-7 bg-indigo-600 text-white border-t border-indigo-500">
                    @foreach($weekdays as $w)
                        <div class="py-2 text-center text-sm font-semibold border-r border-indigo-500 last:border-r-0">
                            {{ $w }}
                        </div>
                    @endforeach
                </div>

                {{-- Grid cuadrado --}}
                <div class="grid grid-cols-7">
                    @foreach($days as $d)
                        @php
                            $isOutside = $d->month !== $currentMonth->month;
                            $key = $d->format('Y-m-d');
                            $items = $byDate[$key] ?? collect();
                        @endphp

                        @if($useAspectSquare)
                            <div class="aspect-square border-r border-b last:border-r-0 p-2 {{ $isOutside ? 'bg-gray-50 text-gray-400' : 'bg-white' }}">
                                <div class="flex items-start justify-between">
                                    <div class="text-sm font-semibold {{ $isOutside ? 'text-gray-400' : 'text-gray-900' }}">
                                        {{ $d->day }}
                                    </div>

                                    <a href="{{ route('coach.trainings.create', ['date' => $key]) }}"
                                       class="w-7 h-7 inline-flex items-center justify-center rounded-md border text-gray-700 hover:bg-gray-100">
                                        +
                                    </a>
                                </div>

                                <div class="mt-2 space-y-1">
                                    @foreach($items->take(2) as $t)
                                        @php
                                            $hasColor = filled($t->tag_color);
                                        @endphp

                                        <a href="{{ route('coach.trainings.edit', $t) }}"
                                        class="block text-xs rounded-md px-2 py-1 border hover:opacity-90"
                                        style="
                                            background-color: {{ $hasColor ? $t->tag_color : 'transparent' }};
                                            border-color: {{ $hasColor ? $t->tag_color : '#e5e7eb' }};
                                        "
                                        >
                                            <div class="font-medium truncate {{ $hasColor ? 'text-white' : ($isOutside ? 'text-gray-500' : 'text-gray-900') }}">
                                                {{ $t->title }}
                                            </div>
                                        </a>
                                    @endforeach


                                    @if($items->count() > 2)
                                            <a href="{{ route('coach.trainings.index', ['view' => 'list', 'date' => $key]) }}"
                                                class="text-xs text-indigo-700 px-1 underline hover:no-underline">
                                                +{{ $items->count() - 2 }} más
                                            </a>
                                    @endif

                                </div>
                            </div>
                        @else
                            {{-- Fallback cuadrado sin aspect-ratio --}}
                            <div class="relative border-r border-b last:border-r-0 {{ $isOutside ? 'bg-gray-50 text-gray-400' : 'bg-white' }}" style="padding-top: 100%;">
                                <div class="absolute inset-0 p-2">
                                    <div class="flex items-start justify-between">
                                        <div class="text-sm font-semibold {{ $isOutside ? 'text-gray-400' : 'text-gray-900' }}">
                                            {{ $d->day }}
                                        </div>

                                        <a href="{{ route('coach.trainings.create', ['date' => $key]) }}"
                                           class="w-7 h-7 inline-flex items-center justify-center rounded-md border text-gray-700 hover:bg-gray-100">
                                            +
                                        </a>
                                    </div>

                                    <div class="mt-2 space-y-1">
                                     @foreach($items->take(2) as $t)
                                            @php
                                                $hasColor = filled($t->tag_color);
                                            @endphp

                                            <a href="{{ route('coach.trainings.edit', $t) }}"
                                            class="block text-xs rounded-md px-2 py-1 border hover:opacity-90"
                                            style="
                                                background-color: {{ $hasColor ? $t->tag_color : 'transparent' }};
                                                border-color: {{ $hasColor ? $t->tag_color : '#e5e7eb' }};
                                            "
                                            >
                                                <div class="font-medium truncate {{ $hasColor ? 'text-white' : ($isOutside ? 'text-gray-500' : 'text-gray-900') }}">
                                                    {{ $t->title }}
                                                </div>
                                            </a>
                                        @endforeach


                                       @if($items->count() > 2)
                                            <button type="button"
                                                    class="text-xs text-indigo-700 px-1 underline hover:no-underline js-more"
                                                    data-date="{{ $key }}">
                                                +{{ $items->count() - 2 }} más
                                            </button>
                                            @endif

                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @else
        
            {{-- LIST VIEW --}}
            <div class="mt-6 bg-white border rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Mostrando {{ $trainings->firstItem() ?? 0 }}–{{ $trainings->lastItem() ?? 0 }} de {{ $trainings->total() }}
                    </div>
                </div>

                <div class="divide-y">
                    @forelse($trainings as $t)
                        <a href="{{ route('coach.trainings.edit', $t) }}"
                           class="block px-4 py-4 hover:bg-gray-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    {{-- <div class="font-semibold text-gray-900">{{ $t->title }}</div> --}}
                                    @php
                                        $hasColor = filled($t->tag_color);
                                    @endphp

                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-1 text-sm font-semibold border"
                                            style="
                                                background-color: {{ $hasColor ? $t->tag_color : 'transparent' }};
                                                border-color: {{ $hasColor ? $t->tag_color : '#e5e7eb' }};
                                                color: {{ $hasColor ? '#ffffff' : '#111827' }};
                                            "
                                        >
                                            {{ $t->title }}
                                        </span>
                                    </div>

                                    <div class="text-sm text-gray-600">
                                        Fecha: {{ optional($t->scheduled_at)->format('Y-m-d') }}
                                        · Secciones: {{ $t->sections_count }}
                                        · Tipo: {{ $t->type }}
                                        · Nivel: {{ $t->level }}
                                    </div>
                                </div>

                            <div class="flex items-center gap-2">
                                        {{-- Estado --}}
                                        <span class="text-xs px-2 py-1 rounded-full border
                                            {{ $t->visibility === 'free'
                                                ? 'bg-green-50 border-green-200 text-green-700'
                                                : 'bg-blue-50 border-blue-200 text-blue-700' }}">
                                            {{ $t->visibility === 'free' ? 'Libre' : 'Asignado' }}
                                        </span>

                                        {{-- Eliminar --}}
                                        <form action="{{ route('coach.trainings.destroy', $t) }}"
                                            method="POST"
                                            onsubmit="return confirm('¿Eliminar este entrenamiento? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                                        border border-red-200 text-red-600
                                                        hover:bg-red-50 transition"
                                                    title="Eliminar">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M9 3a1 1 0 00-1 1v1H5a1 1 0 100 2h14a1 1 0 100-2h-3V4a1 1 0 00-1-1H9z"/>
                                                    <path d="M7 9a1 1 0 011 1v9a1 1 0 11-2 0v-9a1 1 0 011-1zm5 0a1 1 0 011 1v9a1 1 0 11-2 0v-9a1 1 0 011-1zm6 1a1 1 0 10-2 0v9a1 1 0 102 0v-9z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>

                                
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-10 text-center text-gray-600">
                            No hay entrenamientos{{ $date ? ' para esa fecha' : '' }}.
                        </div>
                    @endforelse
                </div>

                <div class="px-4 py-4 border-t">
                    {{ $trainings->links() }}
                </div>
            </div>
        @endif
    </div>
    
</x-app-layout>
