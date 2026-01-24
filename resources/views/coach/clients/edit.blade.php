<x-app-layout>
    <div class="py-8">
        <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Editar cliente</h1>
                <a href="{{ route('coach.clients.index') }}"
                   class="text-sm text-gray-600 hover:text-gray-900">
                    Volver
                </a>
            </div>
         @if($client->userApp && is_null($client->userApp->password))
    <form method="POST"
          action="{{ route('coach.clients.resendActivationCode', $client) }}"
          class="mb-4">
        @csrf
        <button type="submit"
                class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black">
            Reenviar código de activación
        </button>
    </form>
@endif
{{-- Alerts --}}
@if (session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">
        {{ session('success') }}
    </div>
@endif

@if (session('activation_code'))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
        <div class="font-semibold text-amber-900">Código de activación</div>
        <div class="mt-1 text-sm text-amber-800">
            Compártelo con el cliente:
            <span class="ml-2 inline-flex items-center rounded bg-amber-100 px-2 py-1 font-mono text-base">
                {{ session('activation_code') }}
            </span>
        </div>
    </div>
@endif

@if ($errors->has('activation_code'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
        {{ $errors->first('activation_code') }}
    </div>
@endif


            <form method="POST"
      action="{{ route('coach.clients.update', $client) }}"
      class="bg-white shadow rounded-lg p-6">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-12 gap-4 items-end">

        {{-- Nombre --}}
        <div class="col-span-12 lg:col-span-2">
            <label class="block text-xs font-medium text-gray-600">Nombre *</label>
            <input name="first_name"
                   value="{{ old('first_name', $client->first_name) }}"
                   required
                   class="mt-1 w-full rounded-md border-gray-300 text-sm">
        </div>

        {{-- Apellido --}}
        <div class="col-span-12 lg:col-span-2">
            <label class="block text-xs font-medium text-gray-600">Apellido</label>
            <input name="last_name"
                   value="{{ old('last_name', $client->last_name) }}"
                   class="mt-1 w-full rounded-md border-gray-300 text-sm">
        </div>

        {{-- Email --}}
        <div class="col-span-12 lg:col-span-3">
            <label class="block text-xs font-medium text-gray-600">Email</label>
            <input name="email"
                   type="email"
                   value="{{ old('email', $client->email) }}"
                   class="mt-1 w-full rounded-md border-gray-300 text-sm">
        </div>

        {{-- Teléfono --}}
        <div class="col-span-12 lg:col-span-2">
            <label class="block text-xs font-medium text-gray-600">Teléfono</label>
            <input name="phone"
                   value="{{ old('phone', $client->phone) }}"
                   class="mt-1 w-full rounded-md border-gray-300 text-sm">
        </div>

        {{-- Activo --}}
        <div class="col-span-6 lg:col-span-1 flex items-center gap-2 pb-1">
            <input type="checkbox"
                   name="is_active"
                   value="1"
                   {{ old('is_active', $client->is_active) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">Activo</span>
        </div>

        {{-- Guardar --}}
        <div class="col-span-6 lg:col-span-2 flex justify-end">
            <button
                class="h-10 px-5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                Guardar
            </button>
        </div>

    </div>
</form>


{{-- PERFIL DEL CLIENTE --}}
{{-- <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6"> --}}
    <div class="mt-8 space-y-6">

    {{-- Membresías --}}
    <div class="bg-white rounded-xl border shadow-sm">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Membresías</h2>
                <p class="text-sm text-gray-600">Historial de planes y vigencias.</p>
            </div>
            {{-- <a href="{{ route('coach.clients.memberships.create', $client) }}" --}}
            {{-- <a href="#"
               class="text-sm px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                Asignar plan
            </a> --}}
        </div>

       <div class="p-5 overflow-x-auto">
    @if($client->memberships->isEmpty())
        <p class="text-sm text-gray-600">Este cliente aún no tiene membresías.</p>
    @else
        <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-gray-500">
                <tr class="border-b">
                    <th class="py-2 text-left">Plan</th>
                    <th class="py-2 text-left">Precio</th>
                    <th class="py-2 text-left">Vigencia</th>
                    <th class="py-2 text-left">Pago</th>
                    <th class="py-2 text-left">Método</th>
                    <th class="py-2 text-left">Importe pagado</th>
                    <th class="py-2 text-left">Cobro</th>
                </tr>
            </thead>

            <tbody class="divide-y">
                @foreach($client->memberships as $m)
                    @php
                        // Como payments vienen ordenados DESC por payment_date
                        $lastPayment = $m->payments->first();

                        $billing = strtoupper((string) $m->billing_status);
                        $billingBadge = $billing === 'PAID'
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800';

                        $payStatus = strtolower((string) ($lastPayment?->status));
                        $payBadge = (str_contains($payStatus,'paid') || str_contains($payStatus,'complete') || str_contains($payStatus,'ok'))
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-slate-100 text-slate-800';
                    @endphp

                    <tr>
                        {{-- PLAN --}}
                        <td class="py-3">
                            <div class="font-medium text-gray-900">{{ $m->plan_name_snapshot }}</div>
                            <div class="text-xs text-gray-500">ID: {{ $m->id }}</div>
                        </td>

                        {{-- PRECIO --}}
                        <td class="py-3">
                            ${{ number_format((float)$m->price_snapshot, 2) }}
                        </td>

                        {{-- VIGENCIA --}}
                        <td class="py-3">
                            <div class="text-gray-900">Inicio: {{ optional($m->starts_at)->format('d/m/Y') ?? '—' }}</div>
                            <div class="text-gray-600">Fin: {{ optional($m->ends_at)->format('d/m/Y') ?? '—' }}</div>
                        </td>

                        {{-- PAGO (estatus del último pago) --}}
                        <td class="py-3">
                            @if($lastPayment)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs {{ $payBadge }}">
                                    {{ $lastPayment->status ?? '—' }}
                                </span>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ optional($lastPayment->payment_date)->format('d/m/Y') ?? '—' }}
                                </div>
                            @else
                                <span class="text-gray-500">—</span>
                            @endif
                        </td>

                        {{-- MÉTODO --}}
                        <td class="py-3">
                            {{ $lastPayment?->payment_method ?? '—' }}
                        </td>

                        {{-- IMPORTE PAGADO --}}
                        <td class="py-3">
                            @if($lastPayment)
                                ${{ number_format((float)$lastPayment->final_amount, 2) }}
                                @if((float)$lastPayment->discount > 0)
                                    <div class="text-xs text-gray-500">
                                        Desc: ${{ number_format((float)$lastPayment->discount, 2) }}
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>

                        {{-- COBRO (billing_status + paid_at como ya lo tenías) --}}
                        <td class="py-3">
                            <div class="flex flex-col gap-1">
                                @if($billing)
                                    <span class="inline-flex w-fit items-center px-2 py-1 rounded-full text-xs {{ $billingBadge }}">
                                        {{ $billing }}
                                    </span>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif

                                <span class="text-xs text-gray-500">
                                    Pagado: {{ optional($m->paid_at)->format('d/m/Y') ?? '—' }}
                                </span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

        </div>
        {{-- PERFIL DE SALUD (INLINE) --}}
<div class="bg-white rounded-xl border shadow-sm">
    <div class="px-5 py-4 border-b">
        <h2 class="text-base font-semibold text-gray-900">Datos Generales</h2>
        <p class="text-sm text-gray-600">Datos generales del cliente.</p>
    </div>

    @php $hp = $client->healthProfile; @endphp

    <form method="POST"
          action="{{ route('coach.clients.health-profile.update', $client) }}"
          class="p-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-12 gap-4 items-end">

            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Estado</label>
                <input name="state"
                       value="{{ old('state', $hp?->state) }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Ciudad</label>
                <input name="city"
                       value="{{ old('city', $hp?->city) }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Código postal</label>
                <input name="zip_code"
                       value="{{ old('zip_code', $hp?->zip_code) }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Nacimiento</label>
                <input type="date"
                       name="birth_date"
                       value="{{ old('birth_date', optional($hp?->birth_date)->format('Y-m-d')) }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Género</label>
                <select name="gender"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @php $g = old('gender', $hp?->gender); @endphp
                    <option value="">—</option>
                    <option value="male"   {{ $g === 'male' ? 'selected' : '' }}>male</option>
                    <option value="female" {{ $g === 'female' ? 'selected' : '' }}>female</option>
                    <option value="other"  {{ $g === 'other' ? 'selected' : '' }}>other</option>
                </select>
            </div>

            <div class="col-span-12 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-600">Estatura (cm)</label>
                <input name="height_cm"
                       type="number"
                       min="50"
                       max="260"
                       value="{{ old('height_cm', $hp?->height_cm) }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="col-span-12 lg:col-span-1 flex justify-end">
                <button class="h-10 px-5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    Guardar
                </button>
            </div>

        </div>

        {{-- Errores inline (opcional pero útil) --}}
        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </form>
</div>
{{-- MÉTRICAS (INLINE) --}}
<div class="bg-white rounded-xl border shadow-sm">
    <div class="px-5 py-4 border-b">
        <h2 class="text-base font-semibold text-gray-900">Métricas</h2>
        <p class="text-sm text-gray-600">Registra mediciones usando tu catálogo de métricas.</p>
    </div>

    {{-- FORM INLINE --}}
    <form method="POST"
          action="{{ route('coach.clients.metric-records.store', $client) }}"
          class="p-5">
        @csrf

        <div class="grid grid-cols-12 gap-4 items-end">

            {{-- Métrica --}}
            <div class="col-span-12 lg:col-span-4">
                <label class="block text-xs font-medium text-gray-600">Métrica</label>
                <select name="training_metric_id"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm"
                        required>
                    <option value="">— Selecciona —</option>
                    @foreach($metrics as $m)
                        <option value="{{ $m->id }}"
                            {{ (string)old('training_metric_id') === (string)$m->id ? 'selected' : '' }}>
                            {{ $m->name }} @if($m->unit) ({{ $m->unit }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('training_metric_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Valor --}}
            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Valor</label>
                <input type="number"
                       step="0.01"
                       min="0"
                       name="value"
                       value="{{ old('value') }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm"
                       required>
                @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Fecha --}}
            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Fecha</label>
                <input type="date"
                       name="recorded_at"
                       value="{{ old('recorded_at', now()->format('Y-m-d')) }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm"
                       required>
                @error('recorded_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Source --}}
            <div class="col-span-12 lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Source</label>
                <select name="source"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @php $src = old('source','manual'); @endphp
                    <option value="manual" {{ $src==='manual' ? 'selected' : '' }}>manual</option>
                    <option value="device" {{ $src==='device' ? 'selected' : '' }}>device</option>
                    <option value="coach"  {{ $src==='coach'  ? 'selected' : '' }}>coach</option>
                </select>
            </div>

            {{-- Notas --}}
            <div class="col-span-12 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-600">Notas</label>
                <input name="notes"
                       value="{{ old('notes') }}"
                       class="mt-1 w-full rounded-md border-gray-300 text-sm"
                       placeholder="Opcional">
            </div>

            {{-- Guardar --}}
            <div class="col-span-12 lg:col-span-1 flex justify-end">
                <button class="h-10 px-5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    Guardar
                </button>
            </div>
        </div>
    </form>

    {{-- HISTORIAL --}}
    <div class="px-5 pb-5 overflow-x-auto">
        @if($client->metricRecords->isEmpty())
            <div class="text-sm text-gray-600">Aún no hay métricas registradas.</div>
        @else
            <table class="min-w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr class="border-b">
                        <th class="py-2 text-left">Fecha</th>
                        <th class="py-2 text-left">Métrica</th>
                        <th class="py-2 text-left">Valor</th>
                        <th class="py-2 text-left">Source</th>
                        <th class="py-2 text-left">Notas</th>
                        <th class="py-2 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($client->metricRecords as $r)
                        <tr>
                            <td class="py-3">{{ optional($r->recorded_at)->format('d/m/Y') ?? '—' }}</td>
                            <td class="py-3">
                                {{ $r->trainingMetric?->name ?? '—' }}
                                @if($r->trainingMetric?->unit)
                                    <span class="text-gray-500">({{ $r->trainingMetric->unit }})</span>
                                @endif
                            </td>
                            <td class="py-3">
                                {{ $r->value }}
                            </td>
                            <td class="py-3">{{ $r->source ?? '—' }}</td>
                            <td class="py-3 text-gray-600">{{ $r->notes ?: '—' }}</td>
                            <td class="py-3 text-right">
                                <form method="POST"
                                      action="{{ route('coach.clients.metric-records.destroy', [$client, $r]) }}"
                                      onsubmit="return confirm('¿Eliminar esta métrica?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm text-red-600 hover:text-red-800">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

    </div>
</x-app-layout>
