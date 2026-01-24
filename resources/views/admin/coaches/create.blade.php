<x-app-layout>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <h1 class="text-2xl font-bold mb-6">
                Nuevo Coach
            </h1>

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.coaches.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="password" name="password"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre público</label>
                        <input type="text" name="display_name" value="{{ old('display_name') }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="mt-1 block w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estatus</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="trial">Trial</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.coaches.index') }}"
                       class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Guardar Coach
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
