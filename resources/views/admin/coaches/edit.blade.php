<x-app-layout>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">
                    Editar Coach
                </h1>

                <a href="{{ route('admin.coaches.index') }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                    Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.coaches.update', $coach) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="name" value="{{ old('name', $coach->name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email', $coach->email) }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre público</label>
                        <input type="text" name="display_name" value="{{ old('display_name', $coach->coachProfile?->display_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                        <input type="text" name="phone" value="{{ old('phone', $coach->coachProfile?->phone) }}"
                               class="mt-1 block w-full rounded-md border-gray-300">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estatus</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300">
                            @php $status = old('status', $coach->coachProfile?->status); @endphp
                            <option value="active" @selected($status === 'active')>Activo</option>
                            <option value="inactive" @selected($status === 'inactive')>Inactivo</option>
                            <option value="trial" @selected($status === 'trial')>Trial</option>
                            <option value="suspended" @selected($status === 'suspended')>Suspendido</option>
                            <option value="cancelled" @selected($status === 'cancelled')>Cancelado</option>
                        </select>
                    </div>

                </div>

                <div class="border-t pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold">Suspensión</h2>
                            <p class="text-sm text-gray-500">
                                Si activas la suspensión, se guardará fecha y motivo.
                            </p>
                        </div>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="suspend" value="1"
                                   class="rounded border-gray-300"
                                   {{ old('suspend', $coach->coachProfile?->suspended_at ? 1 : 0) ? 'checked' : '' }}>
                            <span class="text-sm">Suspender</span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Motivo de suspensión</label>
                        <input type="text" name="suspension_reason"
                               value="{{ old('suspension_reason', $coach->coachProfile?->suspension_reason) }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               placeholder="Ej. Falta de pago, solicitud del coach, etc.">
                    </div>

                    @if ($coach->coachProfile?->suspended_at)
                        <div class="mt-3 text-sm text-gray-600">
                            Suspendido desde: <span class="font-semibold">{{ $coach->coachProfile->suspended_at->format('Y-m-d H:i') }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.coaches.index') }}"
                       class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Guardar cambios
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
