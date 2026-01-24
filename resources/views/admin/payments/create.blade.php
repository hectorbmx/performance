<x-app-layout>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Nuevo Pago</h1>

                <a href="{{ route('admin.payments.index') }}"
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

            <form method="POST" action="{{ route('admin.payments.store') }}"
                  enctype="multipart/form-data"
                  class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700">Suscripción</label>
                    <select name="coach_subscription_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Selecciona una suscripción</option>
                        @foreach ($subs as $s)
                            <option value="{{ $s->id }}" @selected(old('coach_subscription_id', $selectedSubId ?? null) == $s->id)>
                                {{ $s->coach?->coachProfile?->display_name ?? $s->coach?->name }}
                                · {{ $s->plan_name_snapshot }}
                                · {{ $s->starts_at->format('Y-m-d') }}→{{ $s->ends_at->format('Y-m-d') }}
                                · ({{ $s->status }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Monto</label>
                        <input type="number" step="0.01" name="amount"
                               value="{{ old('amount', 0) }}"
                               class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Moneda</label>
                        <input type="text" name="currency"
                               value="{{ old('currency', 'MXN') }}"
                               class="mt-1 block w-full rounded-md border-gray-300" maxlength="3" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha de pago</label>
                        <input type="date" name="paid_at"
                               value="{{ old('paid_at', now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Método</label>
                        <select name="method" class="mt-1 block w-full rounded-md border-gray-300" required>
                            <option value="manual" selected>Manual</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Referencia (opcional)</label>
                        <input type="text" name="reference"
                               value="{{ old('reference') }}"
                               class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Comprobante (opcional)</label>
                    <input type="file" name="receipt"
                           class="mt-1 block w-full text-sm text-gray-700">
                    <p class="text-xs text-gray-500 mt-1">Máx 5MB. Guardado local ahora (preparado para S3).</p>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.payments.index') }}"
                       class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Registrar Pago
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
