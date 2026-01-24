<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('coach.clients.index') }}"
                       class="text-sm text-gray-600 hover:text-gray-900 underline">
                        ← Volver a clientes
                    </a>
                </div>

                <h1 class="mt-2 text-2xl font-semibold text-gray-900">
                    Entrenamientos de {{ $client->first_name }} {{ $client->last_name }}
                </h1>
                <p class="text-sm text-gray-600">
                    Vista filtrada de entrenamientos asignados a este cliente.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('coach.clients.trainings.index', [$client, 'view' => 'list'] + request()->query()) }}"
                   class="px-3 py-2 rounded-lg border text-sm {{ $viewMode === 'list' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700' }}">
                    Lista
                </a>

                <a href="{{ route('coach.clients.trainings.index', [$client, 'view' => 'calendar'] + request()->query()) }}"
                   class="px-3 py-2 rounded-lg border text-sm {{ $viewMode === 'calendar' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700' }}">
                    Calendario
                </a>
            </div>
        </div>

        {{-- NOTA: MVP read-only, no hay botón + Nuevo aquí --}}

        @if($viewMode === 'calendar')
            @php
                // Para reusar tu diseño, necesitamos construir el mismo set de variables:
                // currentMonth, start, end, byDate

                // Si tu controlador ya te manda algo distinto, ajustamos en 2 minutos.
                $month = request('month'); // formato Y-m opcional
                $currentMonth = \Carbon\Carbon::parse($month ? ($month.'-01') : now()->startOfMonth())->startOfMonth();

                $start = $currentMonth->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
                $end   = $currentMonth->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);

                $byDate = collect($trainings)->groupBy(function($t){
                    return optional($t->scheduled_at)->format('Y-m-d');
                });

                $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
                $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

                $cursor = $start->copy();
                $days = [];
                while ($cursor->lte($end)) {
                    $days[] = $cursor->copy();
                    $cursor->addDay();
                }

                $weekdays = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
                $useAspectSquare = true;
            @endphp

            <div class="mt-6 bg-white border rounded-xl overflow-hidden">
                {{-- Barra superior --}}
                <div class="px-4 py-4 bg-indigo-600 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('coach.clients.trainings.index', ['client' => $client->id, 'view' => 'calendar', 'month' => $prevMonth]) }}"
                           class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20">
                            ‹
                        </a>

                        <div class="text-lg font-semibold">
                            {{ $currentMonth->translatedFormat('F Y') }}
                        </div>

                        <a href="{{ route('coach.clients.trainings.index', ['client' => $client->id, 'view' => 'calendar', 'month' => $nextMonth]) }}"
                           class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20">
                            ›
                        </a>
                    </div>

                    {{-- MVP: read-only, sin +Nuevo --}}
                    <div class="text-sm font-medium text-white/90">
                        Solo lectura
                    </div>
                </div>

                {{-- Header weekdays --}}
                <div class="grid grid-cols-7 bg-indigo-600 text-white border-t border-indigo-500">
                    @foreach($weekdays as $w)
                        <div class="py-2 text-center text-sm font-semibold border-r border-indigo-500 last:border-r-0">
                            {{ $w }}
                        </div>
                    @endforeach
                </div>

                {{-- Grid --}}
                <div class="grid grid-cols-7">
                    @foreach($days as $d)
                        @php
                            $isOutside = $d->month !== $currentMonth->month;
                            $key = $d->format('Y-m-d');
                            $items = $byDate[$key] ?? collect();
                        @endphp

                        <div class="aspect-square border-r border-b last:border-r-0 p-2 {{ $isOutside ? 'bg-gray-50 text-gray-400' : 'bg-white' }}">
                            <div class="flex items-start justify-between">
                                <div class="text-sm font-semibold {{ $isOutside ? 'text-gray-400' : 'text-gray-900' }}">
                                    {{ $d->day }}
                                </div>

                                {{-- MVP: read-only, ocultamos el + --}}
                                <div class="w-7 h-7"></div>
                            </div>

                            <div class="mt-2 space-y-1">
                                @foreach($items->take(2) as $t)
                                    @php $hasColor = filled($t->tag_color); @endphp

                                    {{-- Si quieres que al dar clic abra edit, déjalo así.
                                         Si prefieres read-only total, cambia a <div> --}}
                                    <a href="{{ route('coach.trainings.edit', $t) }}"
                                       class="block text-xs rounded-md px-2 py-1 border hover:opacity-90"
                                       style="
                                            background-color: {{ $hasColor ? $t->tag_color : 'transparent' }};
                                            border-color: {{ $hasColor ? $t->tag_color : '#e5e7eb' }};
                                       ">
                                        <div class="font-medium truncate {{ $hasColor ? 'text-white' : ($isOutside ? 'text-gray-500' : 'text-gray-900') }}">
                                            {{ $t->title }}
                                        </div>
                                    </a>
                                @endforeach

                                @if($items->count() > 2)
                                    {{-- te manda a lista, filtrando por fecha pero manteniendo cliente --}}
                                    <a href="{{ route('coach.clients.trainings.index', ['client' => $client->id, 'view' => 'list', 'date' => $key]) }}"
                                       class="text-xs text-indigo-700 px-1 underline hover:no-underline">
                                        +{{ $items->count() - 2 }} más
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        @else
            {{-- LIST VIEW --}}
            @php
                // Para la lista, filtramos por fecha si viene date (YYYY-MM-DD)
                $date = request('date');
                $items = collect($trainings);

                if ($date) {
                    $items = $items->filter(function($t) use ($date) {
                        return optional($t->scheduled_at)->format('Y-m-d') === $date;
                    });
                }
            @endphp

            <div class="mt-6 bg-white border rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        {{ $date ? "Fecha: {$date}" : 'Todos los asignados' }} · Total: {{ $items->count() }}
                    </div>

                    {{-- Filtro por fecha (MVP) --}}
                    <form method="GET" action="{{ route('coach.clients.trainings.index', $client) }}" class="flex items-end gap-2">
                        <input type="hidden" name="view" value="list" />
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Fecha (opcional)</label>
                            <input type="date" name="date" value="{{ $date }}"
                                   class="h-10 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"/>
                        </div>

                        <button class="h-10 px-4 rounded-lg bg-gray-900 text-white text-sm">Filtrar</button>

                        <a href="{{ route('coach.clients.trainings.index', ['client' => $client->id, 'view' => 'list']) }}"
                           class="h-10 px-4 rounded-lg border text-sm text-gray-700 inline-flex items-center">
                            Limpiar
                        </a>
                    </form>
                </div>

                <div class="divide-y">
                    @forelse($items->sortByDesc('scheduled_at') as $t)
                        <a href="{{ route('coach.trainings.edit', $t) }}"
                           class="block px-4 py-4 hover:bg-gray-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    @php $hasColor = filled($t->tag_color); @endphp

                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-sm font-semibold border"
                                              style="
                                                background-color: {{ $hasColor ? $t->tag_color : 'transparent' }};
                                                border-color: {{ $hasColor ? $t->tag_color : '#e5e7eb' }};
                                                color: {{ $hasColor ? '#ffffff' : '#111827' }};
                                              ">
                                            {{ $t->title }}
                                        </span>
                                    </div>

                                    <div class="text-sm text-gray-600">
                                        Fecha: {{ optional($t->scheduled_at)->format('Y-m-d') }}
                                        · Secciones: {{ $t->sections_count ?? $t->sections()->count() }}
                                        · Tipo: {{ $t->type }}
                                        · Nivel: {{ $t->level }}
                                    </div>
                                </div>

                                <div class="text-xs px-2 py-1 rounded-full border {{ $t->visibility === 'free' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-blue-50 border-blue-200 text-blue-700' }}">
                                    {{ $t->visibility === 'free' ? 'Libre' : 'Asignado' }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-10 text-center text-gray-600">
                            No hay entrenamientos{{ $date ? ' para esa fecha' : '' }}.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
