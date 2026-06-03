<x-app-layout>
    <div class="py-8">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
                <h1 class="text-2xl font-bold shrink-0">Clientes</h1>

                <form method="GET"
                      action="{{ route('coach.clients.index') }}"
                      class="flex flex-col gap-2 w-full lg:max-w-2xl sm:flex-row sm:items-center">
                    <div class="relative flex-1 min-w-[220px]">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3a6 6 0 104.472 10.03l1.749 1.749a1 1 0 001.414-1.414l-1.749-1.749A6 6 0 009 3zm-4 6a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>

                        <input type="text"
                               name="q"
                               value="{{ $q ?? request('q') }}"
                               placeholder="Buscar por nombre, email o telefono..."
                               class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                    </div>

                    <button type="submit"
                            class="shrink-0 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-black transition">
                        Buscar
                    </button>

                    @if(($q ?? request('q')) !== '')
                        <a href="{{ route('coach.clients.index') }}"
                           class="shrink-0 px-4 py-2 rounded-lg border text-sm text-gray-700 hover:bg-gray-50 transition">
                            Limpiar
                        </a>
                    @endif

                    <a href="{{ route('coach.clients.create') }}"
                       class="shrink-0 inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                        + Nuevo cliente
                    </a>
                </form>
            </div>

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('activation_code'))
                <div class="rounded-lg border p-4 mb-4 bg-yellow-50">
                    <div class="font-semibold">Codigo de activacion (App)</div>
                    <div class="text-2xl tracking-widest">{{ session('activation_code') }}</div>
                    <div class="text-sm text-gray-600">Compartelo al cliente para activar su cuenta.</div>
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Celular</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inicia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Termina</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pago</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Membresia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($clients as $client)
                                @php
                                    /** @var \App\Models\ClientMembership|null $m */
                                    $m = $client->latestMembership;
                                    $isExpired = $m && $m->ends_at && $m->ends_at->lt(now()->startOfDay());
                                @endphp

                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        {{ $client->full_name }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $client->email ?? '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $client->phone ?? '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm">
                                        @if($m)
                                            <div class="font-medium text-gray-900">{{ $m->plan_name_snapshot }}</div>
                                            <div class="text-xs text-gray-500">${{ number_format($m->price_snapshot, 2) }}</div>
                                        @else
                                            <a href="{{ route('coach.client-memberships.create', $client) }}"
                                               class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-medium rounded hover:bg-indigo-200">
                                                Asignar plan
                                            </a>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                        {{ $m ? (optional($m->starts_at)->format('d/m/Y') ?? '-') : '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">
                                        {{ $m ? (optional($m->ends_at)->format('d/m/Y') ?? '-') : '-' }}
                                    </td>

                                    <td class="px-6 py-4">
                                        @if(!$m)
                                            <span class="text-sm text-gray-400">-</span>
                                        @elseif($m->billing_status === 'paid')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                PAID
                                            </span>
                                        @elseif($m->is_in_grace)
                                            <div class="space-y-2">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    EN GRACIA
                                                </span>
                                                <div class="text-xs text-blue-700">
                                                    Hasta {{ optional($m->grace_until)->format('d/m/Y') }}
                                                </div>
                                                <a href="{{ route('coach.client-payments.create', $m) }}"
                                                   class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700">
                                                    Registrar pago
                                                </a>
                                            </div>
                                        @else
                                            <div class="space-y-2">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    UNPAID
                                                </span>
                                                @if($m->grace_until)
                                                    <div class="text-xs text-red-600">
                                                        Gracia {{ $m->grace_until->format('d/m/Y') }}
                                                    </div>
                                                @endif
                                                <a href="{{ route('coach.client-payments.create', $m) }}"
                                                   class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700">
                                                    Registrar pago
                                                </a>
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        @if(!$m)
                                            <span class="text-sm text-gray-400">-</span>
                                        @elseif($isExpired)
                                            <div class="space-y-2">
                                                <span class="px-2 inline-flex text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                    VENCIDA
                                                </span>
                                                <a href="{{ route('coach.client-memberships.create', $client) }}"
                                                   class="inline-flex items-center px-3 py-1 bg-indigo-600 text-white text-xs font-medium rounded hover:bg-indigo-700">
                                                    Renovar
                                                </a>
                                            </div>
                                        @else
                                            <span class="px-2 inline-flex text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                                Vigente
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        @if($client->is_active)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Activo
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('coach.clients.edit', $client) }}"
                                               title="Editar cliente"
                                               class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
                                                    <path fill-rule="evenodd" d="M2 15.25A.75.75 0 002.75 16h14.5a.75.75 0 000-1.5H2.75A.75.75 0 002 15.25z" clip-rule="evenodd"/>
                                                </svg>
                                            </a>

                                            <form action="{{ route('coach.clients.destroy', $client) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Eliminar cliente?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        title="Eliminar cliente"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-600 text-white hover:bg-red-700 transition">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 100 2h12a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9z" clip-rule="evenodd"/>
                                                        <path fill-rule="evenodd" d="M5 6a1 1 0 011-1h8a1 1 0 011 1v10a2 2 0 01-2 2H7a2 2 0 01-2-2V6zm3 3a1 1 0 112 0v7a1 1 0 11-2 0V9zm4 0a1 1 0 10-2 0v7a1 1 0 102 0V9z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                            </form>

                                            <a href="{{ route('coach.clients.trainings.index', $client) }}"
                                               class="inline-flex items-center justify-center h-9 w-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700"
                                               title="Ver calendario de entrenamientos">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M4.5 6.75h15A1.5 1.5 0 0 1 21 8.25v10.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A1.5 1.5 0 0 1 4.5 6.75Z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                        No hay clientes registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $clients->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
