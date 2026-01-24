<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-6">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Nuevo grupo</h1>
                <p class="text-sm text-gray-600">Crea un grupo para asignar clientes y entrenamientos.</p>
            </div>

            <a href="{{ route('coach.groups.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg border text-sm text-gray-700 hover:bg-gray-50 transition">
                Volver
            </a>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <form method="POST" action="{{ route('coach.groups.store') }}" class="p-6 space-y-6">
                @csrf

                {{-- Nombre --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre del grupo</label>
                    <input type="text"
                           name="name"
                           value="{{ old('name') }}"
                           placeholder="Ej. Grupo Matutino, Grupo Avanzado…"
                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2
                                  focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Descripción --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descripción (opcional)</label>
                    <textarea name="description"
                              rows="3"
                              placeholder="Notas internas, objetivo del grupo, nivel…"
                              class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2
                                     focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Activo --}}
                <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Estatus</p>
                        <p class="text-xs text-gray-600">Puedes desactivar el grupo sin eliminarlo.</p>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Activo</span>
                    </label>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-2 pt-2">
                    <a href="{{ route('coach.groups.index') }}"
                       class="px-4 py-2 rounded-lg border text-sm text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition">
                        Guardar
                    </button>
                </div>
            </form>
        </div>

    </div>
</x-app-layout>
