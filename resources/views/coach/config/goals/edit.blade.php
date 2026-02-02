<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Editar objetivo</h1>
                <p class="text-sm text-gray-600">Actualiza el objetivo y su configuración.</p>
            </div>

            <a href="{{ route('coach.config.goals.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-sm hover:bg-gray-50 transition">
                Volver
            </a>
        </div>

        <form action="{{ route('coach.config.goals.update', $goal) }}" method="POST" class="mt-6">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border bg-white p-5">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900">Nombre</label>
                    <p class="text-xs text-gray-500 mt-1">Ejemplo: Fuerza, Cardio, Técnica, Movilidad.</p>

                    <div class="mt-2">
                        <input type="text"
                               name="name"
                               value="{{ old('name', $goal->name) }}"
                               required
                               maxlength="120"
                               class="w-full rounded-xl border-slate-200 focus:border-gray-900 focus:ring-gray-900"
                               placeholder="Nombre del objetivo">
                    </div>

                    @error('name')
                        <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="mt-5">
                    <label class="block text-sm font-medium text-gray-900">Descripción (opcional)</label>
                    <p class="text-xs text-gray-500 mt-1">Útil para aclarar cómo se usa este objetivo.</p>

                    <div class="mt-2">
                        <textarea name="description"
                                  rows="3"
                                  class="w-full rounded-xl border-slate-200 focus:border-gray-900 focus:ring-gray-900"
                                  placeholder="Descripción breve...">{{ old('description', $goal->description) }}</textarea>
                    </div>

                    @error('description')
                        <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Active --}}
                <div class="mt-5 flex items-center gap-3">
                    <input id="is_active"
                           type="checkbox"
                           name="is_active"
                           value="1"
                           {{ old('is_active', $goal->is_active ? '1' : '') ? 'checked' : '' }}
                           class="rounded border-slate-300 text-gray-900 focus:ring-gray-900">
                    <label for="is_active" class="text-sm text-gray-900">Activo</label>
                </div>

                @error('is_active')
                    <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                @enderror

                <div class="mt-6 flex items-center justify-end gap-2">
                    <a href="{{ route('coach.config.goals.index') }}"
                       class="px-4 py-2 rounded-xl border text-sm hover:bg-gray-50 transition">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-gray-900 text-white text-sm hover:bg-gray-800 transition">
                        Guardar cambios
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
