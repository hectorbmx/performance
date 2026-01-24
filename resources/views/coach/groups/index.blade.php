<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6 gap-4">
            <h1 class="text-2xl font-bold shrink-0">Grupos</h1>

            <form method="GET"
                  action="{{ route('coach.groups.index') }}"
                  class="flex items-center gap-2 w-full max-w-2xl">

                {{-- Buscador --}}
                <div class="relative flex-1 min-w-[220px]">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4"
                             viewBox="0 0 20 20"
                             fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M9 3a6 6 0 104.472 10.03l1.749 1.749a1 1 0 001.414-1.414l-1.749-1.749A6 6 0 009 3zm-4 6a4 4 0 118 0 4 4 0 01-8 0z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </span>

                    <input type="text"
                           name="q"
                           value="{{ $q ?? request('q') }}"
                           placeholder="Buscar grupo…"
                           class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300
                                  focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                </div>

                <button type="submit"
                        class="shrink-0 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-black transition">
                    Buscar
                </button>

                @if(($q ?? request('q')) !== '')
                    <a href="{{ route('coach.groups.index') }}"
                       class="shrink-0 px-4 py-2 rounded-lg border text-sm text-gray-700 hover:bg-gray-50 transition">
                        Limpiar
                    </a>
                @endif

                <a href="{{ route('coach.groups.create') }}"
                   class="shrink-0 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    + Nuevo grupo
                </a>
            </form>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Grupo</th>
                        <th class="px-4 py-3 text-center">Clientes</th>
                        <th class="px-4 py-3 text-center">Entrenamientos</th>
                        <th class="px-4 py-3 text-center">Estatus</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse($groups as $group)
                        <tr class="hover:bg-gray-50">
                            {{-- Nombre --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">
                                    {{ $group->name }}
                                </div>
                                @if($group->description)
                                    <div class="text-sm text-gray-500">
                                        {{ Str::limit($group->description, 50) }}
                                    </div>
                                @endif
                            </td>

                            {{-- Clientes --}}
                            <td class="px-4 py-3 text-center text-sm">
                                {{ $group->clients_count ?? $group->clients()->count() }}
                            </td>

                            {{-- Entrenamientos --}}
                            <td class="px-4 py-3 text-center text-sm">
                                {{ $group->training_assignments_count ?? $group->trainingAssignments()->count() }}
                            </td>

                            {{-- Estatus --}}
                            <td class="px-4 py-3 text-center">
                                @if($group->is_active)
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full bg-gray-200 text-gray-600">
                                        Inactivo
                                    </span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">

                                    {{-- Ver --}}
                                    <a href="{{ route('coach.groups.show', $group) }}"
                                       title="Ver grupo"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                              bg-gray-100 text-gray-700 hover:bg-gray-800 hover:text-white transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M4.5 6.75h15A1.5 1.5 0 0 1 21 8.25v10.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A1.5 1.5 0 0 1 4.5 6.75Z" />
                                                    </svg>
                                    </a>

                                    {{-- Editar --}}
                                    {{-- <a href="{{ route('coach.groups.edit', $group) }}"
                                       title="Editar grupo"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                              bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
                                        </svg>
                                    </a> --}}

                                    {{-- Eliminar --}}
                                    <form action="{{ route('coach.groups.destroy', $group) }}"
                                          method="POST"
                                          onsubmit="return confirm('¿Eliminar grupo?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="Eliminar grupo"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                                       bg-red-600 text-white hover:bg-red-700 transition">
                                           <svg xmlns="http://www.w3.org/2000/svg"
                                                        class="h-4 w-4"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                        aria-hidden="true">
                                                        <path fill-rule="evenodd"
                                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 100 2h12a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9z"
                                                            clip-rule="evenodd"/>
                                                        <path fill-rule="evenodd"
                                                            d="M5 6a1 1 0 011-1h8a1 1 0 011 1v10a2 2 0 01-2 2H7a2 2 0 01-2-2V6zm3 3a1 1 0 112 0v7a1 1 0 11-2 0V9zm4 0a1 1 0 10-2 0v7a1 1 0 102 0V9z"
                                                            clip-rule="evenodd"/>
                                                    </svg>

                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                No hay grupos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="mt-4">
            {{ $groups->links() }}
        </div>

    </div>
</x-app-layout>
