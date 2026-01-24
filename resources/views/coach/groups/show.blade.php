<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $group->name }}</h1>
                <p class="text-sm text-gray-600">Administra clientes y entrenamientos asignados a este grupo.</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('coach.groups.index') }}"
                   class="px-4 py-2 rounded-lg border text-sm text-gray-700 hover:bg-gray-50 transition">
                    Volver
                </a>

                <a href="{{ route('coach.groups.edit', $group) }}"
                   class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition">
                    Editar grupo
                </a>
            </div>
        </div>

        {{-- Resumen --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs uppercase text-gray-500">Estatus</p>
                    @if($group->is_active)
                        <span class="inline-flex mt-1 px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">
                            Activo
                        </span>
                    @else
                        <span class="inline-flex mt-1 px-2 py-1 text-xs rounded-full bg-gray-200 text-gray-600">
                            Inactivo
                        </span>
                    @endif
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">Clientes</p>
                    <button onclick="openClientModal()" 
                            class="mt-1 text-lg font-semibold text-indigo-600 hover:text-indigo-800 transition cursor-pointer">
                        {{ $group->clients->count() }}
                    </button>
                    <p class="text-xs text-gray-500 mt-1">Gestiona clientes del grupo desde aquí.</p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">Entrenamientos asignados</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $assignments->count() }}</p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">Descripción</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $group->description ?: '—' }}</p>
                </div>
            </div>
        </div>

        {{-- =========================
             CALENDARIO DE ENTRENAMIENTOS
             ========================= --}}
        @php
            // Preparar datos del calendario
            $month = request('month'); // formato Y-m opcional
            $currentMonth = \Carbon\Carbon::parse($month ? ($month.'-01') : now()->startOfMonth())->startOfMonth();

            $start = $currentMonth->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
            $end   = $currentMonth->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);

            $byDate = collect($assignments)->groupBy(function($a){
                return optional($a->scheduled_for)->format('Y-m-d');
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
        @endphp

        <div class="bg-white border rounded-xl overflow-hidden">
            {{-- Barra superior del calendario --}}
            <div class="px-4 py-4 bg-indigo-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('coach.groups.show', ['group' => $group->id, 'month' => $prevMonth]) }}"
                       class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 transition">
                        ‹
                    </a>

                    <div class="text-lg font-semibold">
                        {{ $currentMonth->translatedFormat('F Y') }}
                    </div>

                    <a href="{{ route('coach.groups.show', ['group' => $group->id, 'month' => $nextMonth]) }}"
                       class="w-9 h-9 inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 transition">
                        ›
                    </a>
                </div>

                <div class="text-sm">
                    <span class="font-medium">{{ $assignments->count() }}</span> entrenamientos asignados
                </div>
            </div>

            {{-- Header días de la semana --}}
            <div class="grid grid-cols-7 bg-indigo-600 text-white border-t border-indigo-500">
                @foreach($weekdays as $w)
                    <div class="py-2 text-center text-sm font-semibold border-r border-indigo-500 last:border-r-0">
                        {{ $w }}
                    </div>
                @endforeach
            </div>

            {{-- Grid del calendario --}}
            <div class="grid grid-cols-7">
                @foreach($days as $d)
                    @php
                        $isOutside = $d->month !== $currentMonth->month;
                        $key = $d->format('Y-m-d');
                        $items = $byDate[$key] ?? collect();
                    @endphp

                    <div class="aspect-square border-r border-b last:border-r-0 p-2 {{ $isOutside ? 'bg-gray-50 text-gray-400' : 'bg-white' }} hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between">
                            <div class="text-sm font-semibold {{ $isOutside ? 'text-gray-400' : 'text-gray-900' }}">
                                {{ $d->day }}
                            </div>

                            {{-- Botón para agregar entrenamiento en este día --}}
                            @if(!$isOutside)
                                <button onclick="openAddTrainingModal('{{ $key }}')"
                                        class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition text-lg font-bold"
                                        title="Agregar entrenamiento">
                                    +
                                </button>
                            @endif
                        </div>

                        <div class="mt-2 space-y-1">
                            @foreach($items->take(2) as $a)
                                <div class="block text-xs rounded-md px-2 py-1 bg-indigo-600 text-white cursor-pointer hover:bg-indigo-700 transition"
                                     onclick="showTrainingDetails({{ $a->id }}, '{{ $a->trainingSession->title ?? $a->trainingSession->name ?? 'Entrenamiento' }}', '{{ $key }}')">
                                    <div class="font-medium truncate">
                                        {{ $a->trainingSession->title ?? $a->trainingSession->name ?? 'Entrenamiento #' . $a->training_session_id }}
                                    </div>
                                </div>
                            @endforeach

                            @if($items->count() > 2)
                                <button onclick="showDayDetails('{{ $key }}')"
                                        class="text-xs text-indigo-700 px-1 underline hover:no-underline">
                                    +{{ $items->count() - 2 }} más
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- =========================
         MODAL PARA AGREGAR ENTRENAMIENTO
         ========================= --}}
    <div id="addTrainingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Agregar entrenamiento</h2>
                    <p class="text-sm text-gray-600" id="addTrainingDate"></p>
                </div>
                <button onclick="closeAddTrainingModal()" 
                        class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('coach.groups.trainings.store', $group) }}" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="scheduled_for" id="hiddenScheduledFor">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selecciona un entrenamiento</label>
                    <select name="training_session_id" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2
                                   focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                        <option value="">-- Selecciona un entrenamiento --</option>
                        @foreach($trainings as $t)
                            <option value="{{ $t->id }}">
                                {{ $t->title ?? $t->name ?? ('Entrenamiento #' . $t->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="closeAddTrainingModal()"
                            class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">
                        Agregar entrenamiento
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- =========================
         MODAL PARA VER DETALLES DEL ENTRENAMIENTO
         ========================= --}}
    <div id="trainingDetailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900" id="trainingDetailsTitle"></h2>
                    <p class="text-sm text-gray-600" id="trainingDetailsDate"></p>
                </div>
                <button onclick="closeTrainingDetailsModal()" 
                        class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">¿Deseas eliminar este entrenamiento del grupo?</p>

                <form method="POST" id="deleteTrainingForm" onsubmit="return confirm('¿Seguro que deseas eliminar este entrenamiento?')">
                    @csrf
                    @method('DELETE')
                    
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeTrainingDetailsModal()"
                                class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                            Eliminar entrenamiento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- =========================
         MODAL DE CLIENTES
         ========================= --}}
    <div id="clientModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            {{-- Header del modal --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Clientes del grupo</h2>
                    <p class="text-sm text-gray-600">Agrega o quita clientes de este grupo.</p>
                </div>
                <button onclick="closeClientModal()" 
                        class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Contenido del modal --}}
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                {{-- Buscador para agregar clientes --}}
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Agregar nuevos clientes</label>
                    
                    <div class="flex gap-2 mb-3">
                        <input type="text" 
                               id="clientSearch"
                               placeholder="Buscar por nombre o email..."
                               oninput="filterClients()"
                               class="flex-1 rounded-lg border border-gray-300 px-4 py-2
                                      focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                    </div>

                    {{-- Lista de clientes disponibles (filtrable) --}}
                    <div id="availableClientsList" class="space-y-2 max-h-48 overflow-y-auto">
                        @forelse($availableClients as $c)
                            <div class="client-item flex items-center justify-between p-3 bg-white border rounded-lg hover:shadow-sm transition"
                                 data-name="{{ strtolower($c->first_name . ' ' . $c->last_name) }}"
                                 data-email="{{ strtolower($c->email ?? '') }}">
                                <div>
                                    <div class="font-medium text-gray-900">
                                        {{ $c->first_name }} {{ $c->last_name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        @if($c->email) {{ $c->email }} @endif
                                    </div>
                                </div>
                                
                                <form method="POST" action="{{ route('coach.groups.clients.store', $group) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="client_ids[]" value="{{ $c->id }}">
                                    <button type="submit"
                                            class="px-3 py-1 text-xs rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">
                                        Agregar
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500 text-center py-4">
                                No hay clientes disponibles para asignar.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Lista de clientes asignados --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Clientes asignados ({{ $group->clients->count() }})</h3>
                    
                    <div class="space-y-2">
                        @forelse($group->clients as $client)
                            <div class="flex items-center justify-between gap-3 p-4 border border-gray-200 rounded-lg hover:shadow-sm transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <span class="text-sm font-semibold text-indigo-600">
                                            {{ substr($client->first_name, 0, 1) }}{{ substr($client->last_name, 0, 1) }}
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            {{ $client->first_name }} {{ $client->last_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            @if($client->email) {{ $client->email }} @endif
                                            @if($client->phone) <span class="ml-2">• {{ $client->phone }}</span> @endif
                                        </div>
                                    </div>
                                </div>

                                <form method="POST"
                                      action="{{ route('coach.groups.clients.destroy', [$group, $client]) }}"
                                      onsubmit="return confirm('¿Quitar a {{ $client->first_name }} del grupo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            title="Quitar del grupo"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                                   bg-red-600 text-white hover:bg-red-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                  d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 100 2h12a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500 text-center py-8 bg-gray-50 rounded-lg">
                                No hay clientes asignados todavía.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Footer del modal --}}
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                <button onclick="closeClientModal()" 
                        class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <script>
        // Modal de clientes
        function openClientModal() {
            document.getElementById('clientModal').classList.remove('hidden');
        }

        function closeClientModal() {
            document.getElementById('clientModal').classList.add('hidden');
        }

        // Filtrar clientes en el buscador
        function filterClients() {
            const searchTerm = document.getElementById('clientSearch').value.toLowerCase();
            const items = document.querySelectorAll('.client-item');
            
            items.forEach(item => {
                const name = item.dataset.name;
                const email = item.dataset.email;
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Modal para agregar entrenamiento
        function openAddTrainingModal(date) {
            const modal = document.getElementById('addTrainingModal');
            const dateElement = document.getElementById('addTrainingDate');
            const hiddenInput = document.getElementById('hiddenScheduledFor');
            
            // Formatear fecha
            const dateObj = new Date(date + 'T00:00:00');
            const formatted = dateObj.toLocaleDateString('es-ES', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            dateElement.textContent = formatted;
            hiddenInput.value = date;
            modal.classList.remove('hidden');
        }

        function closeAddTrainingModal() {
            document.getElementById('addTrainingModal').classList.add('hidden');
        }

        // Modal para ver detalles del entrenamiento
        function showTrainingDetails(assignmentId, title, date) {
            const modal = document.getElementById('trainingDetailsModal');
            const titleElement = document.getElementById('trainingDetailsTitle');
            const dateElement = document.getElementById('trainingDetailsDate');
            const form = document.getElementById('deleteTrainingForm');
            
            // Formatear fecha
            const dateObj = new Date(date + 'T00:00:00');
            const formatted = dateObj.toLocaleDateString('es-ES', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            titleElement.textContent = title;
            dateElement.textContent = formatted;
            form.action = `/coach/groups/{{ $group->id }}/trainings/${assignmentId}`;
            
            modal.classList.remove('hidden');
        }

        function closeTrainingDetailsModal() {
            document.getElementById('trainingDetailsModal').classList.add('hidden');
        }

        // Modal para ver todos los entrenamientos de un día
        function showDayDetails(date) {
            // Por ahora mostramos alert, luego podemos hacer un modal más elaborado
            alert('Ver todos los entrenamientos del ' + date);
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeClientModal();
                closeAddTrainingModal();
                closeTrainingDetailsModal();
            }
        });
    </script>
</x-app-layout>