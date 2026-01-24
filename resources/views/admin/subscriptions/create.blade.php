<x-app-layout>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Nueva Suscripción</h1>

                <a href="{{ route('admin.subscriptions.index') }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                    Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

          <form id="subscriptionForm" method="POST" action="{{ route('admin.subscriptions.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">

                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700">Coach</label>
                    <select name="coach_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Selecciona un coach</option>
                        @foreach ($coaches as $c)
                            <option value="{{ $c->id }}" @selected(old('coach_id', $selectedCoachId ?? null) == $c->id)>
                                {{ $c->coachProfile?->display_name ?? $c->name }} ({{ $c->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Plan</label>
                    <select name="membership_plan_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Selecciona un plan</option>
                        @foreach ($plans as $p)
                            <option value="{{ $p->id }}" @selected(old('membership_plan_id') == $p->id)>
                                {{ $p->name }} · ciclo {{ $p->billing_cycle_days }} días · límite {{ $p->client_limit ?? 'Ilimitado' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Inicio</label>
                        <input type="date" name="starts_at"
                               value="{{ old('starts_at', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fin</label>
                        <input type="date" name="ends_at"
                               value="{{ old('ends_at', now()->addDays(30)->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Días antes para recordatorio</label>
                        <input type="number" name="reminder_days_before"
                               value="{{ old('reminder_days_before', 5) }}"
                               class="mt-1 block w-full rounded-md border-gray-300" min="0" max="60">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estatus</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300" required>
                            @php $st = old('status','active'); @endphp
                            <option value="active" @selected($st==='active')>Active</option>
                            <option value="past_due" @selected($st==='past_due')>Past Due</option>
                            <option value="suspended" @selected($st==='suspended')>Suspended</option>
                            <option value="cancelled" @selected($st==='cancelled')>Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="border-t pt-6 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-semibold">Cobro</h2>
            <p class="text-sm text-gray-500">
                Puedes crear la suscripción como no pagada y registrar el pago después.
            </p>
        </div>

        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="register_payment_now" value="1"
                   class="rounded border-gray-300"
                   {{ old('register_payment_now') ? 'checked' : '' }}>
            <span class="text-sm">Registrar pago ahora</span>
        </label>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700">Días de gracia</label>
            <input type="number" name="grace_days"
                   value="{{ old('grace_days', 5) }}"
                   class="mt-1 block w-full rounded-md border-gray-300"
                   min="0" max="60">
            <p class="text-xs text-gray-500 mt-1">Durante la gracia puede seguir con acceso aunque esté “unpaid”.</p>
        </div>
    </div>
</div>


                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.subscriptions.index') }}"
                       class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Crear Suscripción
                    </button>
                </div>
                {{-- Modal: Registrar pago --}}
<div id="payModal" class="fixed inset-0 hidden items-center justify-center bg-black/50 z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h2 class="text-lg font-semibold">¿Registrar pago ahora?</h2>
        <p class="text-sm text-gray-600 mt-2">
            Puedes registrar el pago inmediato o dejar la suscripción como <span class="font-semibold">UNPAID</span> con gracia.
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" id="btnNoPay"
                class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                No, después
            </button>

            <button type="button" id="btnYesPay"
                class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                Sí, registrar pago
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('subscriptionForm');
    const modal = document.getElementById('payModal');
    const btnNo = document.getElementById('btnNoPay');
    const btnYes = document.getElementById('btnYesPay');

    if (!form || !modal || !btnNo || !btnYes) return;

    let pendingAction = null;

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Intercepta submit normal para preguntar
    form.addEventListener('submit', function(e) {
        // si ya decidimos, deja continuar
        if (pendingAction) return;

        e.preventDefault();
        openModal();
    });

    btnNo.addEventListener('click', function() {
        // guardar suscripción sin pago
        pendingAction = 'no';
        closeModal();
        form.submit();
    });

    btnYes.addEventListener('click', function() {
        // marcar flag para que el backend redirija a pago
        let input = form.querySelector('input[name="register_payment_now"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'register_payment_now';
            input.value = '1';
            form.appendChild(input);
        } else {
            input.value = '1';
        }

        pendingAction = 'yes';
        closeModal();
        form.submit();
    });
})();
</script>

            </form>

        </div>
    </div>
</x-app-layout>
