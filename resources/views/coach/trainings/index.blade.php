<x-app-layout>
    @php
        $isCalendar = $view === 'calendar';
        $selectedDate = $isCalendar
            ? ($currentMonth ?? now())->toDateString()
            : ($date ?: now()->toDateString());
        $selectedCarbon = \Carbon\Carbon::parse($selectedDate);
        $weekStart = $selectedCarbon->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $weekDays = collect(range(0, 6))->map(fn ($offset) => $weekStart->copy()->addDays($offset));
        $dayLabels = ['MON','TUE','WED','THU','FRI','SAT','SUN'];
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="text-sm font-semibold text-gray-950">Dashboard &gt; Entrenamientos</div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1 rounded-2xl bg-blue-50 p-1">
                    @foreach($weekDays as $idx => $day)
                        @php
                            $isActiveDay = $day->isSameDay($selectedCarbon);
                            $dateRoute = $isCalendar
                                ? route('coach.trainings.index', ['view' => 'calendar', 'month' => $day->format('Y-m')])
                                : route('coach.trainings.index', ['view' => 'list', 'date' => $day->toDateString()]);
                        @endphp
                        <a href="{{ $dateRoute }}"
                           class="min-w-[54px] rounded-xl px-3 py-2 text-center text-sm transition {{ $isActiveDay ? 'bg-blue-700 text-white shadow-md' : 'text-gray-950 hover:bg-white' }}">
                            <span class="block text-[11px] font-bold {{ $isActiveDay ? 'text-white' : 'text-gray-500' }}">{{ $dayLabels[$idx] }}</span>
                            <span class="block text-lg font-semibold">{{ $day->format('d') }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('coach.trainings.index', array_merge(request()->except('month'), ['view' => 'list'])) }}"
                       class="px-4 py-3 rounded-lg border text-sm font-semibold {{ $view === 'list' ? 'bg-gray-950 text-white border-gray-950' : 'bg-white text-gray-700 border-slate-300' }}">
                        Lista
                    </a>

                    <a href="{{ route('coach.trainings.index', array_merge(request()->except('date'), ['view' => 'calendar'])) }}"
                       class="px-4 py-3 rounded-lg border text-sm font-semibold {{ $view === 'calendar' ? 'bg-gray-950 text-white border-gray-950' : 'bg-white text-gray-700 border-slate-300' }}">
                        Calendario
                    </a>

                    <a href="{{ route('coach.trainings.create', ['date' => $selectedDate]) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-900/15">
                        <i class="fa-solid fa-plus"></i>
                        Nuevo
                    </a>
                </div>
            </div>
        </div>

        <div class="mb-8 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-950">Entrenamientos</h1>
                <p class="mt-1 text-lg text-gray-700">Gestiona tus entrenamientos por fecha y secciones.</p>
            </div>

            @if(!$isCalendar && filled($date))
                <a href="{{ route('coach.trainings.index', ['view' => 'list']) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900">
                    <i class="fa-solid fa-xmark"></i>
                    Quitar filtro de fecha
                </a>
            @endif
        </div>

        @if($isCalendar)
            @php
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

            <div class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-300 bg-white px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('coach.trainings.index', ['view' => 'calendar', 'month' => $prevMonth]) }}"
                           class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-gray-700 hover:bg-blue-50">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>

                        <div>
                            <div class="text-2xl font-bold text-gray-950">{{ $currentMonth->translatedFormat('F Y') }}</div>
                            <div class="text-sm text-gray-600">Vista mensual de entrenamientos</div>
                        </div>

                        <a href="{{ route('coach.trainings.index', ['view' => 'calendar', 'month' => $nextMonth]) }}"
                           class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-gray-700 hover:bg-blue-50">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>

                    <a href="{{ route('coach.trainings.create', ['date' => $currentMonth->toDateString()]) }}"
                       class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white">
                        <i class="fa-solid fa-plus"></i>
                        Nuevo entrenamiento
                    </a>
                </div>

                <div class="grid grid-cols-7 bg-blue-50 text-gray-700">
                    @foreach($weekdays as $w)
                        <div class="border-r border-slate-300 py-3 text-center text-sm font-bold last:border-r-0">
                            {{ $w }}
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach($days as $d)
                        @php
                            $isOutside = $d->month !== $currentMonth->month;
                            $isToday = $d->isToday();
                            $key = $d->format('Y-m-d');
                            $items = $byDate[$key] ?? collect();
                        @endphp

                        <div class="min-h-[150px] border-r border-b border-slate-200 p-3 last:border-r-0 {{ $isOutside ? 'bg-slate-50 text-gray-400' : 'bg-white' }}">
                            <div class="mb-3 flex items-start justify-between">
                                <div class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold {{ $isToday ? 'bg-blue-700 text-white' : ($isOutside ? 'text-gray-400' : 'text-gray-950') }}">
                                    {{ $d->day }}
                                </div>

                                <a href="{{ route('coach.trainings.create', ['date' => $key]) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-gray-700 hover:bg-blue-50"
                                   title="Agregar entrenamiento">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </a>
                            </div>

                            <div class="space-y-2">
                                @foreach($items->take(3) as $t)
                                    @php $hasColor = filled($t->tag_color); @endphp
                                    <div class="group/training rounded-lg border px-2 py-2 text-xs font-semibold shadow-sm hover:opacity-95"
                                         style="background-color: {{ $hasColor ? $t->tag_color : '#ffffff' }}; border-color: {{ $hasColor ? $t->tag_color : '#dbe3f0' }};">
                                        <div class="flex items-start gap-2">
                                            <a href="{{ route('coach.trainings.edit', $t) }}" class="min-w-0 flex-1">
                                                <span class="block truncate {{ $hasColor ? 'text-white' : 'text-gray-950' }}">{{ $t->title }}</span>
                                                <span class="mt-1 block text-[11px] {{ $hasColor ? 'text-white/80' : 'text-gray-500' }}">{{ ucfirst($t->level) }}</span>
                                            </a>

                                            <div class="flex shrink-0 items-center gap-1 opacity-100 xl:opacity-0 xl:transition xl:group-hover/training:opacity-100">
                                                <button type="button"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md {{ $hasColor ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}"
                                                        title="Copiar entrenamiento"
                                                        data-copy-training
                                                        data-copy-id="{{ $t->id }}"
                                                        data-copy-action="{{ route('coach.trainings.copy', $t) }}"
                                                        data-copy-title="{{ e($t->title) }}"
                                                        data-copy-color="{{ $t->tag_color ?: '#2563eb' }}"
                                                        data-copy-visibility="{{ $t->visibility }}"
                                                        data-copy-date="{{ optional($t->scheduled_at)->toDateString() }}">
                                                    <i class="fa-regular fa-copy text-[11px]"></i>
                                                </button>

                                                <form action="{{ route('coach.trainings.destroy', $t) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('¿Eliminar este entrenamiento? Esta acción no se puede deshacer.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md {{ $hasColor ? 'bg-white/15 text-white hover:bg-red-500/80' : 'bg-red-50 text-red-600 hover:bg-red-100' }}"
                                                            title="Eliminar entrenamiento">
                                                        <i class="fa-regular fa-trash-can text-[11px]"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if($items->count() > 3)
                                    <a href="{{ route('coach.trainings.index', ['view' => 'list', 'date' => $key]) }}"
                                       class="inline-flex text-xs font-semibold text-blue-700 hover:text-blue-900">
                                        +{{ $items->count() - 3 }} más
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-300 px-5 py-4">
                    <div class="text-sm font-semibold text-gray-700">
                        Mostrando {{ $trainings->firstItem() ?? 0 }}-{{ $trainings->lastItem() ?? 0 }} de {{ $trainings->total() }}
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ filled($date) ? 'Filtrado por '.$date : 'Todos los entrenamientos' }}
                    </div>
                </div>

                <div class="divide-y divide-slate-200">
                    @forelse($trainings as $t)
                        @php $hasColor = filled($t->tag_color); @endphp
                        <div class="group flex flex-col gap-4 px-5 py-4 hover:bg-blue-50/40 md:flex-row md:items-center md:justify-between">
                            <a href="{{ route('coach.trainings.edit', $t) }}" class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-bold border"
                                          style="background-color: {{ $hasColor ? $t->tag_color : '#ffffff' }}; border-color: {{ $hasColor ? $t->tag_color : '#dbe3f0' }}; color: {{ $hasColor ? '#ffffff' : '#111827' }};">
                                        {{ $t->title }}
                                    </span>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $t->visibility === 'free' ? 'border-green-200 bg-green-50 text-green-700' : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                                        {{ $t->visibility === 'free' ? 'Libre' : 'Asignado' }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600">
                                    <span><i class="fa-regular fa-calendar mr-1 text-gray-400"></i>{{ optional($t->scheduled_at)->format('Y-m-d') }}</span>
                                    <span><i class="fa-solid fa-layer-group mr-1 text-gray-400"></i>{{ $t->sections_count }} secciones</span>
                                    <span><i class="fa-solid fa-dumbbell mr-1 text-gray-400"></i>{{ $t->type }}</span>
                                    <span><i class="fa-solid fa-signal mr-1 text-gray-400"></i>{{ ucfirst($t->level) }}</span>
                                </div>
                            </a>

                            <form action="{{ route('coach.trainings.destroy', $t) }}"
                                  method="POST"
                                  onsubmit="return confirm('¿Eliminar este entrenamiento? Esta acción no se puede deshacer.');">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-red-200 text-red-600 transition hover:bg-red-50"
                                        title="Eliminar">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="px-4 py-14 text-center text-gray-600">
                            No hay entrenamientos{{ $date ? ' para esa fecha' : '' }}.
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-300 px-5 py-4">
                    {{ $trainings->links() }}
                </div>
            </div>

            <div class="mt-8 rounded-xl border border-slate-300 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('coach.trainings.index') }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="view" value="list"/>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-2">Fecha opcional</label>
                        <input type="date" name="date" value="{{ $date }}"
                               class="h-12 rounded-lg border-slate-300 text-lg focus:border-blue-600 focus:ring-blue-600"/>
                    </div>

                    <button class="h-12 px-5 rounded-lg bg-gray-950 text-white text-sm font-semibold">Filtrar</button>

                    <a href="{{ route('coach.trainings.index', ['view' => 'list']) }}"
                       class="inline-flex h-12 items-center rounded-lg border border-slate-300 px-5 text-sm font-semibold text-gray-700 hover:bg-slate-50">
                        Limpiar
                    </a>
                </form>
            </div>
        @endif
    </div>

    @if($isCalendar)
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

            $copyClientOptions = ($copyClients ?? collect())->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->full_name,
                'email' => $client->email,
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
                    selectedClients.forEach((client) => appendHiddenInput('assigned_clients[]', client.id));
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
                                selectedClients = selectedClients.filter((client) => Number(client.id) !== Number(item.id));
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
                        selectedClients.forEach((client) => assignedClientsContainer.appendChild(renderChip(client, 'client')));
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
                    const selected = clientOptions.find((client) => Number(client.id) === Number(clientPicker.value));
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
