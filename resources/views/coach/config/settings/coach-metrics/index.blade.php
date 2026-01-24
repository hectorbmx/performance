@extends('coach.config.layout')

@section('config_content')
<div x-data="{ createOpen:false, editOpen:false, editMetric:null }">
    {{-- FORM BATCH: enabled/required/sort_order --}}
    <form method="POST" action="{{ route('coach.config.settings.coach-metrics.update') }}">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Métricas del coach</h2>
                    <p class="text-sm text-gray-600">
                        Activa las métricas que deseas capturar en la app del atleta. Al guardar, se actualizará lo que se muestra en el perfil.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                        @click="createOpen=true"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-800 text-sm hover:bg-gray-50">
                        Nueva métrica
                    </button>

                    <button type="submit"
                        class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-black">
                        Guardar
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr class="border-b border-gray-100">
                                <th class="py-3 pr-4 text-left">Activo</th>
                                <th class="py-3 pr-4 text-left">Métrica</th>
                                <th class="py-3 pr-4 text-left">Unidad</th>
                                <th class="py-3 pr-4 text-left">Tipo</th>
                                <th class="py-3 pr-4 text-left">Requerida</th>
                                <th class="py-3 pr-4 text-left">Orden</th>
                                <th class="py-3 pr-0 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @forelse($items as $i => $m)
                                <tr>
                                    <input type="hidden" name="metrics[{{ $i }}][id]" value="{{ $m['id'] }}"/>

                                    <td class="py-3 pr-4">
                                        <input type="hidden" name="metrics[{{ $i }}][enabled]" value="0">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300"
                                            name="metrics[{{ $i }}][enabled]"
                                            value="1"
                                            {{ $m['enabled'] ? 'checked' : '' }}
                                        />
                                    </td>

                                    <td class="py-3 pr-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $m['name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $m['code'] }}</div>
                                                <div class="text-[11px] mt-1">
                                                    @if($m['is_owner'])
                                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">
                                                            Personal
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-0.5 text-gray-700">
                                                            Global
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3 pr-4 text-gray-700">{{ $m['unit'] ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-gray-700">{{ $m['type'] ?? '—' }}</td>

                                    <td class="py-3 pr-4">
                                        <input type="hidden" name="metrics[{{ $i }}][is_required]" value="0">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300"
                                            name="metrics[{{ $i }}][is_required]"
                                            value="1"
                                            {{ $m['is_required'] ? 'checked' : '' }}
                                        />
                                    </td>

                                    <td class="py-3 pr-4">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-24 rounded-lg border border-gray-300 px-2 py-1 text-sm"
                                            name="metrics[{{ $i }}][sort_order]"
                                            value="{{ $m['sort_order'] ?? 0 }}"
                                        />
                                    </td>

                                    <td class="py-3 pr-0 text-right">
                                        @if($m['is_owner'])
                                            <div class="inline-flex items-center gap-2">
                                                <button type="button"
                                                    class="px-2 py-1 rounded-lg text-sm border border-gray-200 hover:bg-gray-50"
                                                    @click="editOpen=true; editMetric=@js($m)">
                                                    Editar
                                                </button>

                                                {{-- <form method="POST"
                                                        action="{{ route('coach.config.settings.coach-metrics.catalog.destroy', $m['id']) }}"
                                                        class="inline"
                                                        onsubmit="return confirm('¿Desactivar esta métrica?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                            class="px-2 py-1 rounded-lg text-sm border border-red-200 text-red-700 hover:bg-red-50"
                                                            onclick="if(confirm('¿Desactivar esta métrica?')) document.getElementById('disable-metric-{{ $m['id'] }}').submit();">
                                                        Desactivar
                                                    </button>

                                                    </form> --}}
                                                    <button type="button"
                                                            class="px-2 py-1 rounded-lg text-sm border border-red-200 text-red-700 hover:bg-red-50"
                                                            onclick="if(confirm('¿Desactivar esta métrica?')) document.getElementById('disable-metric-{{ $m['id'] }}').submit();">
                                                        Desactivar
                                                    </button>


                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-gray-600">
                                        No hay métricas disponibles.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </form> {{-- cierra el form batch PUT aquí --}}

                    @foreach($items as $m)
                        @if($m['is_owner'])
                            <form id="disable-metric-{{ $m['id'] }}"
                                method="POST"
                                action="{{ route('coach.config.settings.coach-metrics.catalog.destroy', $m['id']) }}"
                                class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    @endforeach

                </div>

                <div class="mt-4 text-xs text-gray-500">
                    Nota: Si desactivas una métrica, el atleta conservará su historial en base de datos, pero dejará de mostrarse en su perfil.
                </div>
            </div>
        </div>
    </form>

    {{-- MODAL: CREAR --}}
    <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="createOpen=false"></div>

        <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="font-semibold text-gray-900">Nueva métrica</div>
                <button class="text-gray-500 hover:text-gray-700" @click="createOpen=false">✕</button>
            </div>

            <form method="POST" action="{{ route('coach.config.settings.coach-metrics.catalog.store') }}">
                @csrf

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input name="name" value="{{ old('name') }}"
                               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Código (opcional)</label>
                        <input name="code" value="{{ old('code') }}"
                               class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                               placeholder="ej. fran_time" />
                        <div class="text-xs text-gray-500 mt-1">Si lo dejas vacío se genera automáticamente.</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unidad (opcional)</label>
                            <input name="unit" value="{{ old('unit') }}"
                                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                                   placeholder="kg, reps, min, s, etc." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo (opcional)</label>
                            <input name="type" value="{{ old('type') }}"
                                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                                   placeholder="max, time, count, custom, etc." />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="create_is_active" type="checkbox" name="is_active" value="1" checked
                               class="h-4 w-4 rounded border-gray-300" />
                        <label for="create_is_active" class="text-sm text-gray-700">Activa</label>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-2">
                    <button type="button"
                            class="px-3 py-2 rounded-lg border border-gray-200"
                            @click="createOpen=false">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-3 py-2 rounded-lg bg-gray-900 text-white">
                        Crear
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL: EDITAR --}}
    <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="editOpen=false"></div>

        <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="font-semibold text-gray-900">Editar métrica</div>
                <button class="text-gray-500 hover:text-gray-700" @click="editOpen=false">✕</button>
            </div>

            <template x-if="editMetric">
                <form method="POST"
                      :action="`{{ route('coach.config.settings.coach-metrics.catalog.update', 0) }}`.replace('/0', '/' + editMetric.id)">
                    @csrf
                    @method('PUT')

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input name="name" x-model="editMetric.name"
                                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código</label>
                            <input name="code" x-model="editMetric.code"
                                   class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Unidad</label>
                                <input name="unit" x-model="editMetric.unit"
                                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tipo</label>
                                <input name="type" x-model="editMetric.type"
                                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="edit_is_active" type="checkbox" name="is_active" value="1"
                                   class="h-4 w-4 rounded border-gray-300"
                                   :checked="!!editMetric.is_active" />
                            <label for="edit_is_active" class="text-sm text-gray-700">Activa</label>
                            <div class="text-xs text-gray-500">(si la desactivas, no se mostrará como opción)</div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button type="button"
                                class="px-3 py-2 rounded-lg border border-gray-200"
                                @click="editOpen=false">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-3 py-2 rounded-lg bg-gray-900 text-white">
                            Guardar
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
