<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-6">
                <a href="{{ route('coach.clients.index') }}"
                   class="text-indigo-600 hover:text-indigo-900">
                    ← Volver a clientes
                </a>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <h1 class="text-2xl font-bold mb-6">Registrar Pago</h1>

                {{-- Información de la membresía --}}
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h3 class="font-medium text-gray-900 mb-3">Información de la membresía</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Cliente:</span>
                            <span class="font-medium ml-2">{{ $membership->client->full_name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Plan:</span>
                            <span class="font-medium ml-2">{{ $membership->plan_name_snapshot }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Precio plan:</span>
                            <span class="font-medium ml-2">${{ number_format($membership->price_snapshot, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Vigencia:</span>
                            <span class="font-medium ml-2">
                                {{ $membership->starts_at->format('d/m/Y') }} - {{ $membership->ends_at->format('d/m/Y') }}
                            </span>
                        </div>
                        @if($membership->grace_until)
                            <div class="col-span-2">
                                <span class="text-gray-600">Gracia hasta:</span>
                                <span class="font-medium ml-2 {{ $membership->is_in_grace ? 'text-blue-600' : 'text-red-600' }}">
                                    {{ $membership->grace_until->format('d/m/Y') }}
                                    @if(!$membership->is_in_grace)
                                        <span class="text-xs">(VENCIDA)</span>
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <form action="{{ route('coach.client-payments.store', $membership) }}" method="POST" id="paymentForm">
                    @csrf

                    {{-- Monto --}}
                    <div class="mb-4">
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Monto a cobrar <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                $
                            </span>
                            <input type="number"
                                   name="amount"
                                   id="amount"
                                   value="{{ old('amount', $membership->price_snapshot) }}"
                                   step="0.01"
                                   min="0"
                                   class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   required>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            Precio del plan: ${{ number_format($membership->price_snapshot, 2) }}
                        </p>
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descuento --}}
                    <div class="mb-4">
                        <label for="discount" class="block text-sm font-medium text-gray-700 mb-2">
                            Descuento (opcional)
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                $
                            </span>
                            <input type="number"
                                   name="discount"
                                   id="discount"
                                   value="{{ old('discount', 0) }}"
                                   step="0.01"
                                   min="0"
                                   class="w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        @error('discount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Monto final (calculado automáticamente) --}}
                    <div class="mb-4 p-3 bg-indigo-50 rounded-md">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Monto final a pagar:</span>
                            <span class="text-lg font-bold text-indigo-600" id="finalAmount">
                                ${{ number_format($membership->price_snapshot, 2) }}
                            </span>
                        </div>
                    </div>

                    {{-- Método de pago --}}
                    <div class="mb-4">
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                            Método de pago <span class="text-red-500">*</span>
                        </label>
                        <select name="payment_method"
                                id="payment_method"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="">Selecciona un método</option>
                            <option value="efectivo" {{ old('payment_method') === 'efectivo' ? 'selected' : '' }}>
                                Efectivo
                            </option>
                            <option value="transferencia" {{ old('payment_method') === 'transferencia' ? 'selected' : '' }}>
                                Transferencia bancaria
                            </option>
                            <option value="tarjeta" {{ old('payment_method') === 'tarjeta' ? 'selected' : '' }}>
                                Tarjeta (débito/crédito)
                            </option>
                            <option value="paypal" {{ old('payment_method') === 'paypal' ? 'selected' : '' }}>
                                PayPal
                            </option>
                            <option value="otro" {{ old('payment_method') === 'otro' ? 'selected' : '' }}>
                                Otro
                            </option>
                        </select>
                        @error('payment_method')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Fecha de pago --}}
                    <div class="mb-4">
                        <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Fecha de pago <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="payment_date"
                               id="payment_date"
                               value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                               max="{{ now()->format('Y-m-d') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required>
                        @error('payment_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Notas --}}
                    <div class="mb-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Notas adicionales (opcional)
                        </label>
                        <textarea name="notes"
                                  id="notes"
                                  rows="3"
                                  maxlength="500"
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Ej: Pago con folio #12345, referencia bancaria, etc.">{{ old('notes') }}</textarea>
                        <p class="mt-1 text-sm text-gray-500">
                            Máximo 500 caracteres
                        </p>
                        @error('notes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('coach.clients.index') }}"
                           class="px-4 py-2 text-gray-700 hover:text-gray-900">
                            Cancelar
                        </a>

                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Registrar pago
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        // Calcular monto final automáticamente
        const amountInput = document.getElementById('amount');
        const discountInput = document.getElementById('discount');
        const finalAmountDisplay = document.getElementById('finalAmount');

        function calculateFinalAmount() {
            const amount = parseFloat(amountInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            const finalAmount = Math.max(0, amount - discount);
            
            finalAmountDisplay.textContent = '$' + finalAmount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        amountInput.addEventListener('input', calculateFinalAmount);
        discountInput.addEventListener('input', calculateFinalAmount);

        // Calcular al cargar
        calculateFinalAmount();
    </script>
    @endpush
</x-app-layout>