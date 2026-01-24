<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Nuevo cliente</h1>
                <a href="{{ route('coach.clients.index') }}"
                   class="text-sm text-gray-600 hover:text-gray-900">
                    Volver
                </a>
            </div>

            <form method="POST" action="{{ route('coach.clients.store') }}"
                  class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Nombre *</label>
                        <input name="first_name" value="{{ old('first_name') }}"
                               class="mt-1 w-full rounded-md border-gray-300"
                               required>
                        @error('first_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Apellido</label>
                        <input name="last_name" value="{{ old('last_name') }}"
                               class="mt-1 w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Email</label>
                        <input name="email" type="email" value="{{ old('email') }}"
                               class="mt-1 w-full rounded-md border-gray-300">
                        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Teléfono</label>
                        <input name="phone" value="{{ old('phone') }}"
                               class="mt-1 w-full rounded-md border-gray-300">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span class="text-sm">Activo</span>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('coach.clients.index') }}"
                       class="px-4 py-2 rounded-md border">
                        Cancelar
                    </a>
                    <button class="px-4 py-2 rounded-md bg-indigo-600 text-white">
                        Guardar cliente
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
