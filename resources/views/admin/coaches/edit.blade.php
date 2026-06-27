<x-app-layout>
    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Editar Coach</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Gestiona el perfil operativo y el acceso SaaS de este tenant.
                    </p>
                </div>

                <a href="{{ route('admin.coaches.index') }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                    Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="bg-white shadow rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Acceso SaaS</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <h2 class="text-xl font-bold text-gray-900">
                                {{ $subscription?->plan_name_snapshot ?? 'Sin plan asignado' }}
                            </h2>
                            <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $access['badge'] }}">
                                {{ $access['label'] }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $access['reason'] }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($subscription && !$access['can_access'])
                            <a href="{{ route('admin.payments.create', ['subscription_id' => $subscription->id]) }}"
                               class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                                Registrar pago
                            </a>
                        @endif

                        @if(!$subscription)
                            <a href="{{ route('admin.subscriptions.create', ['coach_id' => $coach->id]) }}"
                               class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                                Crear suscripcion
                            </a>
                        @else
                            <a href="{{ route('admin.subscriptions.create', ['coach_id' => $coach->id]) }}"
                               class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                Nueva suscripcion
                            </a>
                        @endif

                        <a href="{{ route('admin.subscriptions.index') }}"
                           class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                            Ver suscripciones
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                    <div class="p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Cobro</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">
                            {{ $subscription ? strtoupper($subscription->billing_status ?? 'N/A') : 'N/A' }}
                        </p>
                    </div>
                    <div class="p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Vigencia</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">
                            @if($subscription)
                                {{ $subscription->starts_at?->format('Y-m-d') }} a {{ $subscription->ends_at?->format('Y-m-d') }}
                            @else
                                Sin suscripcion
                            @endif
                        </p>
                    </div>
                    <div class="p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Gracia</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">
                            {{ $subscription?->grace_until?->format('Y-m-d') ?? 'Sin gracia' }}
                        </p>
                    </div>
                    <div class="p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Ultimo pago</p>
                        @php $lastPayment = $payments->first(); @endphp
                        <p class="mt-1 text-sm font-semibold text-gray-900">
                            @if($lastPayment)
                                {{ $lastPayment->paid_at?->format('Y-m-d') }} · ${{ number_format((float) $lastPayment->amount, 2) }} {{ $lastPayment->currency }}
                            @else
                                Sin pagos
                            @endif
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <form method="POST" action="{{ route('admin.coaches.update', $coach) }}" class="xl:col-span-2 bg-white shadow rounded-lg p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Perfil operativo</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Este estado describe al coach en el sistema; el acceso real depende tambien de su suscripcion.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" name="name" value="{{ old('name', $coach->name) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" value="{{ old('email', $coach->email) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre publico</label>
                            <input type="text" name="display_name" value="{{ old('display_name', $coach->coachProfile?->display_name) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Telefono</label>
                            <input type="text" name="phone" value="{{ old('phone', $coach->coachProfile?->phone) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estatus operativo</label>
                            <select name="status" class="mt-1 block w-full rounded-md border-gray-300">
                                @php $status = old('status', $coach->coachProfile?->status); @endphp
                                <option value="active" @selected($status === 'active')>Activo</option>
                                <option value="inactive" @selected($status === 'inactive')>Inactivo</option>
                                <option value="trial" @selected($status === 'trial')>Trial</option>
                                <option value="suspended" @selected($status === 'suspended')>Suspendido</option>
                                <option value="cancelled" @selected($status === 'cancelled')>Cancelado</option>
                            </select>
                        </div>

                    </div>

                    <div class="border-t pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="font-semibold">Suspension manual</h2>
                                <p class="text-sm text-gray-500">
                                    Si activas la suspension, se guardara fecha y motivo operativo.
                                </p>
                            </div>

                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="suspend" value="1"
                                       class="rounded border-gray-300"
                                       {{ old('suspend', $coach->coachProfile?->suspended_at ? 1 : 0) ? 'checked' : '' }}>
                                <span class="text-sm">Suspender</span>
                            </label>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Motivo de suspension</label>
                            <input type="text" name="suspension_reason"
                                   value="{{ old('suspension_reason', $coach->coachProfile?->suspension_reason) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300"
                                   placeholder="Ej. Falta de pago, solicitud del coach, etc.">
                        </div>

                        @if ($coach->coachProfile?->suspended_at)
                            <div class="mt-3 text-sm text-gray-600">
                                Suspendido desde: <span class="font-semibold">{{ $coach->coachProfile->suspended_at->format('Y-m-d H:i') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.coaches.index') }}"
                           class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                            Cancelar
                        </a>

                        <button type="submit"
                                class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                            Guardar cambios
                        </button>
                    </div>
                </form>

                <aside class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Pagos recientes</h2>
                            <p class="text-sm text-gray-500">Ultimos movimientos del coach.</p>
                        </div>
                        <a href="{{ route('admin.payments.index') }}"
                           class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                            Ver todos
                        </a>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse($payments as $payment)
                            <div class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">
                                            ${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $payment->paid_at?->format('Y-m-d') }} · {{ strtoupper($payment->method) }}
                                        </p>
                                    </div>
                                    <a href="{{ route('admin.payments.edit', $payment) }}"
                                       class="text-sm text-indigo-600 hover:text-indigo-800">
                                        Editar
                                    </a>
                                </div>

                                <p class="mt-2 text-sm text-gray-600">
                                    {{ $payment->subscription?->plan_name_snapshot ?? 'Sin plan asociado' }}
                                </p>

                                @if($payment->reference)
                                    <p class="mt-1 text-xs text-gray-500">
                                        Ref: {{ $payment->reference }}
                                    </p>
                                @endif
                            </div>
                        @empty
                            <div class="p-6 text-sm text-gray-500">
                                No hay pagos registrados para este coach.
                            </div>
                        @endforelse
                    </div>
                </aside>
            </div>

        </div>
    </div>
</x-app-layout>
