<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Tipos de entrenamiento</h1>
                <p class="text-sm text-gray-600">Administra el catálogo de tipos disponibles para tus entrenamientos.</p>
            </div>

            <a href="{{ route('coach.config.types.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 text-white text-sm hover:bg-gray-800 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 5a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H6a1 1 0 110-2h5V6a1 1 0 011-1z"/>
                </svg>
                Nuevo tipo
            </a>
        </div>

        {{-- Flash --}}
        @if (session('success'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
                Ocurrió un error. Revisa los campos e intenta de nuevo.
            </div>
        @endif

        {{-- List --}}
        <div class="mt-6 rounded-2xl border bg-white overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
                <div class="text-sm font-medium text-gray-900">Listado</div>
                <div class="text-xs text-gray-500">
                    {{ $types->total() ?? $types->count() }} tipos
                </div>
            </div>

            @if(($types->count() ?? 0) === 0)
                <div class="p-6">
                    <div class="rounded-xl border bg-gray-50 p-5">
                        <div class="text-sm font-semibold text-gray-900">Aún no tienes tipos</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Crea tu primer tipo para empezar a clasificar entrenamientos.
                        </div>
                        <a href="{{ route('coach.config.types.create') }}"
                           class="inline-flex mt-4 items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 text-white text-sm hover:bg-gray-800 transition">
                            Crear tipo
                        </a>
                    </div>
                </div>
            @else
                <div class="divide-y">
                    @foreach($types as $t)
                        <div class="px-4 py-4 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold text-gray-900 truncate">
                                        {{ $t->name }}
                                    </div>

                                    @if(isset($t->is_active))
                                        @if($t->is_active)
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Activo
                                            </span>
                                        @else
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border">
                                                Inactivo
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                @if(!empty($t->slug))
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $t->slug }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('coach.config.types.edit', $t) }}"
                                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border hover:bg-gray-50 transition"
                                   title="Editar">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-700" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M16.862 3.487a1.5 1.5 0 012.121 0l1.53 1.53a1.5 1.5 0 010 2.121l-9.9 9.9a1 1 0 01-.42.245l-4 1.2a1 1 0 01-1.245-1.245l1.2-4a1 1 0 01.245-.42l9.9-9.9z"/>
                                        <path d="M15.44 4.91l3.65 3.65"/>
                                    </svg>
                                </a>

                                <form action="{{ route('coach.config.types.destroy', $t) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar este tipo?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl border hover:bg-red-50 transition"
                                            title="Eliminar">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M9 3a1 1 0 00-1 1v1H5a1 1 0 100 2h14a1 1 0 100-2h-3V4a1 1 0 00-1-1H9z"/>
                                            <path d="M7 9a1 1 0 011 1v9a1 1 0 11-2 0v-9a1 1 0 011-1zm5 0a1 1 0 011 1v9a1 1 0 11-2 0v-9a1 1 0 011-1zm6 1a1 1 0 10-2 0v9a1 1 0 102 0v-9z"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(method_exists($types, 'links'))
                    <div class="px-4 py-4 border-t bg-white">
                        {{ $types->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
