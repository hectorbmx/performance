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
                $month = request('month', $month ?? now()->format('Y-m')); // formato Y-m opcional
                $currentMonth = ($currentMonth ?? \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01'))->copy()->startOfMonth();

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
                                <div class="w-7 h-7">
                                    <a href="{{ route('coach.trainings.create', ['date' => $key, 'client_id' => $client->id]) }}"
                                       class="w-7 h-7 inline-flex items-center justify-center rounded-md border text-gray-700 hover:bg-gray-100">
                                        +
                                    </a>
                                </div>
                            </div>

                            <div class="mt-2 space-y-1">
                                @foreach($items->take(2) as $t)
                                    @php $hasColor = filled($t->tag_color); @endphp

                                    <div class="group/training rounded-md border px-2 py-1 text-xs hover:opacity-90"
                                         style="
                                            background-color: {{ $hasColor ? $t->tag_color : 'transparent' }};
                                            border-color: {{ $hasColor ? $t->tag_color : '#e5e7eb' }};
                                         ">
                                        <div class="flex items-start gap-1">
                                            <a href="{{ route('coach.trainings.edit', $t) }}" class="min-w-0 flex-1">
                                                <span class="block truncate font-medium {{ $hasColor ? 'text-white' : ($isOutside ? 'text-gray-500' : 'text-gray-900') }}">
                                                    {{ $t->title }}
                                                </span>
                                            </a>

                                            <div class="flex shrink-0 items-center gap-1 opacity-100 xl:opacity-0 xl:transition xl:group-hover/training:opacity-100">
                                                <button type="button"
                                                        class="inline-flex h-5 w-5 items-center justify-center rounded {{ $hasColor ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}"
                                                        title="Copiar entrenamiento"
                                                        data-copy-training
                                                        data-copy-id="{{ $t->id }}"
                                                        data-copy-action="{{ route('coach.trainings.copy', $t) }}"
                                                        data-copy-title="{{ e($t->title) }}"
                                                        data-copy-color="{{ $t->tag_color ?: '#2563eb' }}"
                                                        data-copy-visibility="{{ $t->visibility }}"
                                                        data-copy-date="{{ optional($t->scheduled_at)->toDateString() }}">
                                                    <i class="fa-regular fa-copy text-[10px]"></i>
                                                </button>

                                                <form action="{{ route('coach.trainings.destroy', $t) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('¿Eliminar este entrenamiento? Esta acción no se puede deshacer.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="return_client_id" value="{{ $client->id }}">
                                                    <input type="hidden" name="return_view" value="calendar">
                                                    <input type="hidden" name="return_month" value="{{ $currentMonth->format('Y-m') }}">
                                                    <button type="submit"
                                                            class="inline-flex h-5 w-5 items-center justify-center rounded {{ $hasColor ? 'bg-white/15 text-white hover:bg-red-500/80' : 'bg-red-50 text-red-600 hover:bg-red-100' }}"
                                                            title="Eliminar entrenamiento">
                                                        <i class="fa-regular fa-trash-can text-[10px]"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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

    @if($viewMode === 'calendar')
        @php
            $copyPayload = collect($byDate ?? [])
                ->flatten(1)
                ->mapWithKeys(function ($training) use ($groupAssignments) {
                    $clients = $training->assignments
                        ->filter(fn ($assignment) => filled($assignment->client_id) && $assignment->client)
                        ->map(fn ($assignment) => [
                            'id' => $assignment->client->id,
                            'name' => $assignment->client->full_name,
                            'email' => $assignment->client->email,
                        ])
                        ->values();

                    $groups = ($groupAssignments[$training->id] ?? collect())
                        ->filter(fn ($assignment) => $assignment->group)
                        ->map(fn ($assignment) => [
                            'id' => $assignment->group->id,
                            'name' => $assignment->group->name,
                        ])
                        ->values();

                    return [$training->id => [
                        'clients' => $clients,
                        'groups' => $groups,
                    ]];
                });

            $copyClientOptions = ($copyClients ?? collect())->map(fn ($copyClient) => [
                'id' => $copyClient->id,
                'name' => $copyClient->full_name,
                'email' => $copyClient->email,
            ])->values();

            $copyGroupOptions = ($copyGroups ?? collect())->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
            ])->values();
        @endphp

        <div id="copyTrainingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-950/50 px-4 py-6">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-950">Copiar entrenamiento</h2>
                        <p id="copyTrainingTitle" class="mt-1 text-sm text-gray-600"></p>
                    </div>
                    <button type="button" id="closeCopyTrainingModal" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-slate-100" aria-label="Cerrar modal">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form id="copyTrainingForm" method="POST" class="max-h-[calc(90vh-74px)] overflow-y-auto px-5 py-5">
                    @csrf
                    <input type="hidden" name="return_client_id" value="{{ $client->id }}">

                    <div class="grid gap-4 md:grid-cols-[1fr_180px]">
                        <div>
                            <label for="copyTrainingName" class="mb-2 block text-sm font-semibold text-gray-900">Nombre del entrenamiento</label>
                            <input type="text" id="copyTrainingName" name="title" required
                                   class="h-12 w-full rounded-lg border-slate-300 text-base focus:border-blue-600 focus:ring-blue-600"/>
                        </div>

                        <div>
                            <label for="copyTrainingColor" class="mb-2 block text-sm font-semibold text-gray-900">Color etiqueta</label>
                            <input type="color" id="copyTrainingColor" name="tag_color"
                                   class="h-12 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1"/>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="copyTrainingDate" class="mb-2 block text-sm font-semibold text-gray-900">Fecha destino</label>
                        <input type="date" id="copyTrainingDate" name="scheduled_at" required
                               class="h-12 w-full rounded-lg border-slate-300 text-base focus:border-blue-600 focus:ring-blue-600"/>
                    </div>

                    <div id="copyAssignmentEditor" class="mt-5 hidden rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-950">Asignados</h3>
                            <span class="text-xs font-semibold text-gray-500">Editable antes de copiar</span>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="copyClientPicker" class="mb-1 block text-xs font-semibold text-gray-600">Agregar atleta</label>
                                <select id="copyClientPicker" class="h-10 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                    <option value="">Seleccionar atleta</option>
                                </select>
                            </div>
                            <div>
                                <label for="copyGroupPicker" class="mb-1 block text-xs font-semibold text-gray-600">Agregar grupo</label>
                                <select id="copyGroupPicker" class="h-10 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                    <option value="">Seleccionar grupo</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Atletas</p>
                            <div id="copyAssignedClients" class="flex min-h-10 flex-wrap gap-2"></div>
                        </div>

                        <div class="mt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Grupos</p>
                            <div id="copyAssignedGroups" class="flex min-h-10 flex-wrap gap-2"></div>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-gray-600">
                        Se copiara el entrenamiento completo con secciones y asignaciones a la fecha seleccionada.
                    </p>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" id="cancelCopyTrainingModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                            Copiar entrenamiento
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('copyTrainingModal');
                const form = document.getElementById('copyTrainingForm');
                const title = document.getElementById('copyTrainingTitle');
                const nameInput = document.getElementById('copyTrainingName');
                const colorInput = document.getElementById('copyTrainingColor');
                const dateInput = document.getElementById('copyTrainingDate');
                const assignmentEditor = document.getElementById('copyAssignmentEditor');
                const clientPicker = document.getElementById('copyClientPicker');
                const groupPicker = document.getElementById('copyGroupPicker');
                const assignedClientsContainer = document.getElementById('copyAssignedClients');
                const assignedGroupsContainer = document.getElementById('copyAssignedGroups');
                const trainingAssignments = @js($copyPayload);
                const clientOptions = @js($copyClientOptions);
                const groupOptions = @js($copyGroupOptions);
                let selectedClients = [];
                let selectedGroups = [];
                const closeButtons = [
                    document.getElementById('closeCopyTrainingModal'),
                    document.getElementById('cancelCopyTrainingModal'),
                ];

                if (!modal || !form || !title || !nameInput || !colorInput || !dateInput) return;

                const fillPicker = (picker, options, selected, emptyLabel) => {
                    picker.innerHTML = `<option value="">${emptyLabel}</option>`;
                    options
                        .filter((option) => !selected.some((item) => Number(item.id) === Number(option.id)))
                        .forEach((option) => {
                            const element = document.createElement('option');
                            element.value = option.id;
                            element.textContent = option.email ? `${option.name} (${option.email})` : option.name;
                            picker.appendChild(element);
                        });
                };

                const appendHiddenInput = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    input.dataset.copyAssignmentInput = 'true';
                    form.appendChild(input);
                };

                const renderAssigned = () => {
                    form.querySelectorAll('[data-copy-assignment-input]').forEach((input) => input.remove());
                    selectedClients.forEach((selectedClient) => appendHiddenInput('assigned_clients[]', selectedClient.id));
                    selectedGroups.forEach((group) => appendHiddenInput('assigned_groups[]', group.id));

                    const renderChip = (item, type) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold ' + (type === 'client' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-blue-200 bg-blue-50 text-blue-700');
                        button.title = 'Quitar';
                        const label = document.createElement('span');
                        label.textContent = item.name;
                        const icon = document.createElement('i');
                        icon.className = 'fa-solid fa-minus text-xs text-red-600';
                        button.append(label, icon);
                        button.addEventListener('click', () => {
                            if (type === 'client') {
                                selectedClients = selectedClients.filter((selectedClient) => Number(selectedClient.id) !== Number(item.id));
                            } else {
                                selectedGroups = selectedGroups.filter((group) => Number(group.id) !== Number(item.id));
                            }
                            renderAssigned();
                        });
                        return button;
                    };

                    assignedClientsContainer.innerHTML = '';
                    assignedGroupsContainer.innerHTML = '';

                    if (selectedClients.length === 0) {
                        const empty = document.createElement('span');
                        empty.className = 'text-sm text-gray-500';
                        empty.textContent = 'Sin atletas asignados.';
                        assignedClientsContainer.appendChild(empty);
                    } else {
                        selectedClients.forEach((selectedClient) => assignedClientsContainer.appendChild(renderChip(selectedClient, 'client')));
                    }

                    if (selectedGroups.length === 0) {
                        const empty = document.createElement('span');
                        empty.className = 'text-sm text-gray-500';
                        empty.textContent = 'Sin grupos asignados.';
                        assignedGroupsContainer.appendChild(empty);
                    } else {
                        selectedGroups.forEach((group) => assignedGroupsContainer.appendChild(renderChip(group, 'group')));
                    }

                    fillPicker(clientPicker, clientOptions, selectedClients, 'Seleccionar atleta');
                    fillPicker(groupPicker, groupOptions, selectedGroups, 'Seleccionar grupo');
                };

                const openModal = (button) => {
                    const trainingId = button.dataset.copyId;
                    const assignments = trainingAssignments[trainingId] || { clients: [], groups: [] };
                    form.action = button.dataset.copyAction || '';
                    title.textContent = button.dataset.copyTitle || 'Entrenamiento seleccionado';
                    nameInput.value = button.dataset.copyTitle || '';
                    colorInput.value = button.dataset.copyColor || '#2563eb';
                    dateInput.value = button.dataset.copyDate || '';
                    selectedClients = [...(assignments.clients || [])];
                    selectedGroups = [...(assignments.groups || [])];
                    assignmentEditor.classList.toggle('hidden', button.dataset.copyVisibility !== 'assigned');
                    renderAssigned();
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    nameInput.focus();
                    nameInput.select();
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    form.action = '';
                    title.textContent = '';
                    nameInput.value = '';
                    colorInput.value = '#2563eb';
                    dateInput.value = '';
                    selectedClients = [];
                    selectedGroups = [];
                    assignmentEditor.classList.add('hidden');
                    renderAssigned();
                };

                clientPicker?.addEventListener('change', () => {
                    const selected = clientOptions.find((selectedClient) => Number(selectedClient.id) === Number(clientPicker.value));
                    if (selected) selectedClients.push(selected);
                    clientPicker.value = '';
                    renderAssigned();
                });

                groupPicker?.addEventListener('change', () => {
                    const selected = groupOptions.find((group) => Number(group.id) === Number(groupPicker.value));
                    if (selected) selectedGroups.push(selected);
                    groupPicker.value = '';
                    renderAssigned();
                });

                document.querySelectorAll('[data-copy-training]').forEach((button) => {
                    button.addEventListener('click', () => openModal(button));
                });

                closeButtons.forEach((button) => {
                    button?.addEventListener('click', closeModal);
                });

                modal.addEventListener('click', (event) => {
                    if (event.target === modal) closeModal();
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            });
        </script>
    @endif
</x-app-layout>
