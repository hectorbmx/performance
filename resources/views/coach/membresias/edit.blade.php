<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-6">
                <a href="{{ route('coach.membresias.index') }}"
                   class="text-indigo-600 hover:text-indigo-900">
                    ← Volver a membresías
                </a>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <h1 class="text-2xl font-bold mb-6">Editar Membresía</h1>

                <form action="{{ route('coach.membresias.update', $membresia) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- Nombre --}}
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre del plan <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $membresia->name) }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción --}}
                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Descripción
                        </label>
                        <textarea name="description"
                                  id="description"
                                  rows="3"
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $membresia->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Precio --}}
                    <div class="mb-4">
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            Precio <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                $
                            </span>
                            <input type="number"
                                   name="price"
                                   id="price"
                                   value="{{ old('price', $membresia->price) }}"
                                   step="0.01"
                                   min="0"
                                   class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   required>
                        </div>
                        @error('price')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Duración --}}
                    <div class="mb-4">
                        <label for="billing_cycle_days" class="block text-sm font-medium text-gray-700 mb-2">
                            Duración (días) <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               name="billing_cycle_days"
                               id="billing_cycle_days"
                               value="{{ old('billing_cycle_days', $membresia->billing_cycle_days) }}"
                               min="1"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required>
                        <p class="mt-1 text-sm text-gray-500">
                            Ejemplos: 30 (mensual), 90 (trimestral), 365 (anual)
                        </p>
                        @error('billing_cycle_days')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Estatus --}}
                    <div class="mb-6">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Estatus <span class="text-red-500">*</span>
                        </label>
                        <select name="status"
                                id="status"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="active" {{ old('status', $membresia->status) === 'active' ? 'selected' : '' }}>
                                Activo
                            </option>
                            <option value="inactive" {{ old('status', $membresia->status) === 'inactive' ? 'selected' : '' }}>
                                Inactivo
                            </option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('coach.membresias.index') }}"
                           class="px-4 py-2 text-gray-700 hover:text-gray-900">
                            Cancelar
                        </a>

                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Actualizar membresía
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>