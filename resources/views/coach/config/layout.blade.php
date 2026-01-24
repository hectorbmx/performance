{{-- resources/views/coach/config/layout.blade.php --}}
<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Configuración</h1>
                <p class="text-sm text-gray-600">Administra catálogos y ajustes del coach.</p>
            </div>
        </div>

        @php
            $is = fn(string $name) => request()->routeIs($name);

            $links = [
                ['label' => 'Inicio',          'route' => 'coach.config.index'],
                ['label' => 'Tipos',           'route' => 'coach.config.types.index'],
                ['label' => 'Objetivos',       'route' => 'coach.config.goals.index'],
                ['label' => 'Tipos de sección','route' => 'coach.config.section-types.index'],
                ['label' => 'Métricas (catálogo)', 'route' => 'coach.config.metrics.index'],
                ['label' => 'Métricas del coach',  'route' => 'coach.config.settings.coach-metrics.index'],
                // Si usas secciones
                // ['label' => 'Secciones', 'route' => 'coach.config.sections.index'],
            ];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Submenú --}}
            <aside class="lg:col-span-3">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <div class="text-sm font-semibold text-gray-900">Menú</div>
                        <div class="text-xs text-gray-600">Opciones de configuración</div>
                    </div>

                    <nav class="p-2 space-y-1">
                        @foreach($links as $item)
                            <a href="{{ route($item['route']) }}"
                               class="block px-3 py-2 rounded-lg text-sm
                                      {{ $is($item['route']) ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            {{-- Contenido --}}
            <main class="lg:col-span-9">
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <div class="font-semibold mb-1">Revisa los campos:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('config_content')
            </main>
        </div>
    </div>
</x-app-layout>
