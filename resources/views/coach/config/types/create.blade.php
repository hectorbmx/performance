<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Nuevo tipo</h1>
                <p class="text-sm text-gray-600">Crea un tipo de entrenamiento para usarlo en tus sesiones.</p>
            </div>

            <a href="{{ route('coach.config.types.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-sm hover:bg-gray-50 transition">
                Volver
            </a>
        </div>

        <form action="{{ route('coach.config.types.store') }}" method="POST" class="mt-6">
            @csrf

            <div class="rounded-2xl border bg-white p-5">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-900">Nombre</label>
                    <p class="text-xs text-gray-500 mt-1">Ejemplo: Fitness, Weightlifting, Home Training.</p>

                    <div class="mt-2">
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               maxlength="80"
                               class="w-full rounded-xl border-slate-200 focus:border-gray-900 focus:ring-gray-900"
                               placeholder="Nombre del tipo">
                    </div>

                    @error('name')
                        <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Slug (optional) --}}
                <div class="mt-5">
                    <label class="block text-sm font-medium text-gray-900">Clave (opcional)</label>
                    <p class="text-xs text-gray-500 mt-1">Útil para integraciones o normalizar. Ej: <span class="font-mono">functional_fitness</span>.</p>

                    <div class="mt-2">
                        <input type="text"
                               name="slug"
                               value="{{ old('slug') }}"
                               maxlength="80"
                               class="w-full rounded-xl border-slate-200 focus:border-gray-900 focus:ring-gray-900"
                               placeholder="clave_opcional">
                    </div>

                    @error('slug')
                        <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Active --}}
                <div class="mt-5 flex items-center gap-3">
                    <input id="is_active"
                           type="checkbox"
                           name="is_active"
                           value="1"
                           {{ old('is_active', '1') ? 'checked' : '' }}
                           class="rounded border-slate-300 text-gray-900 focus:ring-gray-900">
                    <label for="is_active" class="text-sm text-gray-900">Activo</label>
                </div>

                @error('is_active')
                    <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                @enderror

                <div class="mt-6 flex items-center justify-end gap-2">
                    <a href="{{ route('coach.config.types.index') }}"
                       class="px-4 py-2 rounded-xl border text-sm hover:bg-gray-50 transition">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-gray-900 text-white text-sm hover:bg-gray-800 transition">
                        Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
