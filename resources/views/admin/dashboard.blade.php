<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard Admin</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Salud comercial y operativa de coaches en Training Flow.
                    </p>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('admin.coaches.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Ver coaches
                    </a>
                    <a href="{{ route('admin.payments.create') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-700">
                        Registrar pago
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <section class="bg-white shadow rounded-lg p-6 border border-gray-100">
                    <p class="text-sm font-medium text-gray-500">Coaches registrados</p>
                    <div class="mt-3 flex items-end justify-between">
                        <p class="text-4xl font-bold text-gray-900">{{ $coachesTotal }}</p>
                        <span class="text-xs font-semibold rounded-full bg-green-100 text-green-800 px-2 py-1">
                            {{ $activeCoaches }} activos
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        {{ $inactiveCoaches }} inactivos / {{ $suspendedCoaches }} suspendidos
                    </p>
                </section>

                <section class="bg-white shadow rounded-lg p-6 border border-gray-100">
                    <p class="text-sm font-medium text-gray-500">Ingresos del mes</p>
                    <p class="mt-3 text-4xl font-bold text-gray-900">
                        ${{ number_format((float) $monthlyRevenue, 2) }}
                    </p>
                    <p class="mt-3 text-xs text-gray-500">
                        {{ $paymentsThisMonth }} pagos registrados este mes
                    </p>
                </section>

                <section class="bg-white shadow rounded-lg p-6 border border-gray-100">
                    <p class="text-sm font-medium text-gray-500">Subs por expirar</p>
                    <p class="mt-3 text-4xl font-bold text-gray-900">{{ $expiringSubscriptionsCount }}</p>
                    <p class="mt-3 text-xs text-gray-500">
                        Vencen en los proximos 7 dias
                    </p>
                </section>

                <section class="bg-white shadow rounded-lg p-6 border border-gray-100">
                    <p class="text-sm font-medium text-gray-500">Sin pago / gracia vencida</p>
                    <div class="mt-3 flex items-end justify-between">
                        <p class="text-4xl font-bold text-red-700">{{ $overdueSubscriptionsCount }}</p>
                        <span class="text-xs font-semibold rounded-full bg-red-100 text-red-800 px-2 py-1">
                            Bloqueo
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Coaches con acceso comercial bloqueado
                    </p>
                </section>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <section class="bg-white shadow rounded-lg p-6 border border-gray-100">
                    <p class="text-sm font-medium text-gray-500">Coaches en gracia</p>
                    <p class="mt-3 text-3xl font-bold text-blue-700">{{ $graceSubscriptionsCount }}</p>
                    <p class="mt-2 text-sm text-gray-500">
                        Aun tienen acceso, pero requieren seguimiento antes de bloquearse.
                    </p>
                </section>

                <section class="bg-white shadow rounded-lg p-6 border border-gray-100 lg:col-span-2">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Cobertura de cobro del mes</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $collectionRate }}%</p>
                        </div>
                        <div class="text-right text-sm text-gray-500">
                            <p><span class="font-semibold text-gray-900">{{ $paidDueThisMonth }}</span> pagadas</p>
                            <p><span class="font-semibold text-gray-900">{{ $dueThisMonth }}</span> vencen este mes</p>
                        </div>
                    </div>
                    <div class="mt-5 h-3 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ min($collectionRate, 100) }}%"></div>
                    </div>
                </section>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="bg-white shadow rounded-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Por expirar</h2>
                            <p class="text-sm text-gray-500">Suscripciones que vencen en los proximos 7 dias.</p>
                        </div>
                        <a href="{{ route('admin.subscriptions.index') }}"
                           class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                            Ver todas
                        </a>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse($expiringSubscriptions as $sub)
                            <div class="p-5 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-gray-900">
                                        {{ $sub->coach?->coachProfile?->display_name ?? $sub->coach?->name ?? 'Coach sin nombre' }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ $sub->plan_name_snapshot }} / vence {{ $sub->ends_at?->format('Y-m-d') }}
                                    </p>
                                </div>
                                <a href="{{ $sub->coach ? route('admin.coaches.edit', $sub->coach) : route('admin.subscriptions.index') }}"
                                   class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                    Revisar
                                </a>
                            </div>
                        @empty
                            <div class="p-6 text-sm text-gray-500">
                                No hay suscripciones por expirar en los proximos 7 dias.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow rounded-lg overflow-hidden border border-gray-100">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Requieren cobro</h2>
                            <p class="text-sm text-gray-500">Pendientes de pago con gracia vencida o sin gracia.</p>
                        </div>
                        <a href="{{ route('admin.payments.create') }}"
                           class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">
                            Registrar pago
                        </a>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse($overdueSubscriptions as $sub)
                            <div class="p-5 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-gray-900">
                                        {{ $sub->coach?->coachProfile?->display_name ?? $sub->coach?->name ?? 'Coach sin nombre' }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ $sub->plan_name_snapshot }} / gracia {{ $sub->grace_until?->format('Y-m-d') ?? 'sin fecha' }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.payments.create', ['subscription_id' => $sub->id]) }}"
                                   class="text-sm font-semibold text-emerald-600 hover:text-emerald-800">
                                    Cobrar
                                </a>
                            </div>
                        @empty
                            <div class="p-6 text-sm text-gray-500">
                                No hay coaches con gracia vencida.
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
