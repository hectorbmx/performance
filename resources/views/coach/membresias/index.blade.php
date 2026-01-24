<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Membresías</h1>

                <a href="{{ route('coach.membresias.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    + Nueva membresía
                </a>
            </div>

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Nombre
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Precio
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Duración
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Estatus
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($plans as $plan)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">
                                        {{ $plan->name }}
                                    </div>
                                    @if($plan->description)
                                        <div class="text-sm text-gray-500 mt-1">
                                            {{ Str::limit($plan->description, 60) }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                                    ${{ number_format($plan->price, 2) }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $plan->billing_cycle_days }} 
                                    {{ $plan->billing_cycle_days == 1 ? 'día' : 'días' }}
                                </td>

                                <td class="px-6 py-4">
                                    @if($plan->status === 'active')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Activo
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-right space-x-3 text-sm">
                                    <a href="{{ route('coach.membresias.edit', $plan) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        Editar
                                    </a>

                                    <form action="{{ route('coach.membresias.destroy', $plan) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('¿Eliminar esta membresía?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-900">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No hay membresías registradas. 
                                    <a href="{{ route('coach.membresias.create') }}" class="text-indigo-600 hover:text-indigo-900">
                                        Crea tu primera membresía
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>