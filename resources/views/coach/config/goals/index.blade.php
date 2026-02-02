<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Objetivos</h1>
                <p class="text-sm text-gray-600">Administra los objetivos disponibles para tus sesiones.</p>
            </div>

            <a href="{{ route('coach.config.goals.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 text-white text-sm hover:bg-gray-800 transition">
                Nuevo
            </a>
        </div>

        @if (session('success'))
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="mt-6 rounded-2xl border bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-700">
                        <tr>
                            <th class="text-left font-medium px-4 py-3">Nombre</th>
                            <th class="text-left font-medium px-4 py-3">Descripción</th>
                            <th class="text-left font-medium px-4 py-3">Estatus</th>
                            <th class="text-right font-medium px-4 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($items as $item)
                            <tr>
                                <td class="px-4 py-3 text-slate-900">
                                    {{ $item->name }}
                                </td>

                                <td class="px-4 py-3 text-slate-600">
                                    {{ $item->description ? Str::limit($item->description, 80) : '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    @if ($item->is_active)
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs border bg-green-50 text-green-700 border-green-200">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs border bg-slate-50 text-slate-600 border-slate-200">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('coach.config.goals.edit', $item) }}"
                                           class="px-3 py-1.5 rounded-lg border text-xs hover:bg-gray-50 transition">
                                            Editar
                                        </a>

                                        <form action="{{ route('coach.config.goals.destroy', $item) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar objetivo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-lg border text-xs text-red-600 hover:bg-red-50 transition">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                    No hay objetivos todavía.
                                    <a href="{{ route('coach.config.goals.create') }}" class="text-slate-900 underline">
                                        Crea el primero
                                    </a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="px-4 py-3 border-t bg-white">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
