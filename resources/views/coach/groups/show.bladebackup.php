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
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $group->clients->count() }}</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- =========================
                 CLIENTES DEL GRUPO
                 ========================= --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Clientes del grupo</h2>
                        <p class="text-sm text-gray-600">Agrega o quita clientes de este grupo.</p>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    {{-- Agregar clientes --}}
                    <form method="POST" action="{{ route('coach.groups.clients.store', $group) }}" class="space-y-2">
                        @csrf

                        <label class="block text-sm font-medium text-gray-700">Agregar clientes</label>

                        <select name="client_ids[]"
                                multiple
                                class="w-full rounded-lg border border-gray-300 px-3 py-2
                                       focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                            @forelse($availableClients as $c)
                                <option value="{{ $c->id }}">
                                    {{ $c->first_name }} {{ $c->last_name }} @if($c->email) — {{ $c->email }} @endif
                                </option>
                            @empty
                                <option disabled>No hay clientes disponibles para asignar.</option>
                            @endforelse
                        </select>

                        @error('client_ids')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-black transition">
                            Agregar
                        </button>
                    </form>

                    {{-- Lista de clientes asignados --}}
                    <div class="border-t pt-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Asignados</p>

                        <div class="space-y-2">
                            @forelse($group->clients as $client)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-4 py-3">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            {{ $client->first_name }} {{ $client->last_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            @if($client->email) {{ $client->email }} @endif
                                            @if($client->phone) <span class="ml-2">{{ $client->phone }}</span> @endif
                                        </div>
                                    </div>

                                    <form method="POST"
                                          action="{{ route('coach.groups.clients.destroy', [$group, $client]) }}"
                                          onsubmit="return confirm('¿Quitar cliente del grupo?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="Quitar"
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
                                <div class="text-sm text-gray-500">No hay clientes asignados todavía.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- =========================
                 ENTRENAMIENTOS ASIGNADOS
                 ========================= --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Entrenamientos asignados</h2>
                    <p class="text-sm text-gray-600">Asigna entrenamientos a una fecha (mismo día o diferentes).</p>
                </div>

                <div class="p-6 space-y-4">
                    {{-- Asignar entrenamiento + fecha --}}
                  <form method="POST" action="{{ route('coach.groups.trainings.store', $group) }}"
      class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
    @csrf

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Entrenamiento</label>

        <select name="training_session_id"
                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2
                       focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            <option value="">Selecciona…</option>
            @foreach($trainings as $t)
                <option value="{{ $t->id }}" {{ old('training_session_id') == $t->id ? 'selected' : '' }}>
                    {{ $t->title ?? $t->name ?? ('Entrenamiento #' . $t->id) }}
                </option>
            @endforeach
        </select>

        @error('training_session_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Fecha</label>
        <input type="date"
               name="scheduled_for"
               value="{{ old('scheduled_for', now()->toDateString()) }}"
               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2
                      focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
        @error('scheduled_for')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-3">
        <button type="submit"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-black transition">
            Asignar
        </button>
    </div>
</form>

{{-- Lista de asignaciones --}}
<div class="border-t pt-4">
    <p class="text-sm font-medium text-gray-700 mb-2">Agenda del grupo</p>

    <div class="space-y-2">
        @forelse($assignments as $a)
            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-4 py-3">
                <div>
                    <div class="font-medium text-gray-900">
                        {{ $a->trainingSession->title ?? $a->trainingSession->name ?? ('Entrenamiento #' . $a->training_session_id) }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ optional($a->scheduled_for)->format('d/m/Y') ?? $a->scheduled_for }}
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('coach.groups.trainings.destroy', [$group, $a]) }}"
                      onsubmit="return confirm('¿Quitar entrenamiento asignado?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            title="Quitar"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-600 text-white hover:bg-red-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 100 2h12a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </form>
            </div>
        @empty
            <div class="text-sm text-gray-500">No hay entrenamientos asignados todavía.</div>
        @endforelse
    </div>
</div>
</x-app-layout>
