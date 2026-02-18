<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Planes de Membresía</h1>

                <a href="{{ route('admin.plans.create') }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    + Nuevo Plan
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ciclo (días)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Límite clientes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stripe Price ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($plans as $plan)
                            <tr>
                                <td class="px-6 py-4 font-medium">
                                    {{ $plan->name }}
                                </td>

                                <td class="px-6 py-4">
                                    {{ $plan->billing_cycle_days }}
                                </td>

                                <td class="px-6 py-4">
                                    {{ $plan->client_limit ?? 'Ilimitado' }}
                                </td>

                                <td class="px-6 py-4 font-mono text-xs">
                                    {{ $plan->stripe_price_id ?? '—' }}
                                </td>

                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs font-semibold rounded-full
                                        {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $plan->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-right space-x-3">
                                    <a href="{{ route('admin.plans.edit', $plan) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        Editar
                                    </a>

                                   <form action="{{ route('admin.plans.toggleActive', $plan) }}"
                                        method="POST"
                                        class="inline">
                                        @csrf

                                        @php
                                            $isActive = (bool) $plan->is_active;
                                        @endphp

                                        <button type="submit"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition
                                                {{ $isActive ? 'bg-green-500' : 'bg-red-500' }}">
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition
                                                {{ $isActive ? 'translate-x-6' : 'translate-x-1' }}">
                                            </span>
                                        </button>
                                    </form>



                                    <form action="{{ route('admin.plans.destroy', $plan) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('¿Eliminar este plan?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-sm text-red-600 hover:text-red-900">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    No hay planes registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $plans->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
