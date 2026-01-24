<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Suscripciones (Coaches)</h1>

                <a href="{{ route('admin.subscriptions.create') }}"
                   class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    + Nueva Suscripción
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan (snapshot)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vigencia</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($subs as $s)
                            @php
                                $statusClass = match ($s->status) {
                                    'active' => 'bg-green-100 text-green-800',
                                    'past_due' => 'bg-yellow-100 text-yellow-800',
                                    'suspended' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                            @endphp

                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ $s->coach?->coachProfile?->display_name ?? $s->coach?->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $s->coach?->email }}</div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ $s->plan_name_snapshot }}</div>
                                    <div class="text-sm text-gray-500">
                                        Ciclo: {{ $s->billing_cycle_days_snapshot }} días
                                        · Límite: {{ $s->client_limit_snapshot ?? 'Ilimitado' }}
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="text-sm">Inicio: <span class="font-medium">{{ $s->starts_at->format('Y-m-d') }}</span></div>
                                    <div class="text-sm">Fin: <span class="font-medium">{{ $s->ends_at->format('Y-m-d') }}</span></div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs font-semibold rounded-full {{ $statusClass }}">
                                        {{ $s->status }}
                                    </span>
                                    @php
                                        $today = now()->toDateString();
                                        $billing = $s->billing_status ?? 'unpaid';

                                        $billingClass = match ($billing) {
                                            'paid' => 'bg-green-100 text-green-800',
                                            'partial' => 'bg-yellow-100 text-yellow-800',
                                            default => 'bg-red-100 text-red-800',
                                        };

                                        $graceLabel = null;
                                        if ($billing === 'unpaid' && $s->grace_until) {
                                            $graceLabel = ($today <= $s->grace_until) ? 'EN GRACIA' : 'GRACIA VENCIDA';
                                        }
                                    @endphp

                                    <div class="mt-2">
                                        <span class="px-2 inline-flex text-xs font-semibold rounded-full {{ $billingClass }}">
                                            {{ strtoupper($billing) }}
                                        </span>

                                        @if($graceLabel)
                                            <span class="ml-2 px-2 inline-flex text-xs font-semibold rounded-full
                                                {{ $today <= $s->grace_until ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800' }}">
                                                {{ $graceLabel }} ({{ $s->grace_until->format('Y-m-d') }})
                                            </span>
                                        @endif
                                    </div>

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                    No hay suscripciones registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $subs->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
