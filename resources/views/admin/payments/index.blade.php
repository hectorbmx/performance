<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Pagos</h1>

                <a href="{{ route('admin.payments.create') }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    + Nuevo Pago
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coach</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suscripción</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($payments as $p)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ $p->coach?->coachProfile?->display_name ?? $p->coach?->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $p->coach?->email }}</div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-medium">
                                        {{ $p->subscription?->plan_name_snapshot ?? 'N/A' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $p->subscription?->starts_at?->format('Y-m-d') }} → {{ $p->subscription?->ends_at?->format('Y-m-d') }}
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    {{ number_format((float)$p->amount, 2) }} {{ $p->currency }}
                                </td>

                                <td class="px-6 py-4">
                                    {{ $p->paid_at?->format('Y-m-d') }}
                                </td>

                                <td class="px-6 py-4 text-right space-x-3">
                                    <a href="{{ route('admin.payments.edit', $p) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        Editar
                                    </a>

                                    <form action="{{ route('admin.payments.destroy', $p) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('¿Eliminar este pago?')">
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
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    No hay pagos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $payments->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
