<x-app-layout>
    @if (session('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-2"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed top-5 right-5 z-50 max-w-sm w-full bg-white shadow-xl rounded-xl border border-emerald-100 p-4 pointer-events-auto flex items-start gap-3"
             style="display: none;">
            <div class="flex-shrink-0 p-1 bg-emerald-50 rounded-lg text-emerald-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="flex-1 pt-0.5">
                <p class="text-sm font-semibold text-gray-900">¡Operación Exitosa!</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <div class="py-10 bg-gray-50/50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-8 pb-5 border-b border-gray-200/80">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Nuevo Plan de Membresía</h1>
                    <p class="text-sm text-gray-500 mt-1">Configura las características, precios y pasarelas de cobro para tu nuevo plan.</p>
                </div>

                <a href="{{ route('admin.plans.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 hover:text-gray-900 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Volver al listado
                </a>
            </div>

            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3 text-red-800">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-sm">Por favor corrige los siguientes errores:</h4>
                        <ul class="list-disc list-inside text-xs mt-1 space-y-1 opacity-90">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.plans.store') }}" class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl p-6 md:p-8 space-y-8">
                @csrf

                <div class="space-y-5">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Información General</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del plan</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 placeholder-gray-400 text-sm transition-all"
                               placeholder="Ej. Plan Premium Mensual" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                        <textarea name="description" rows="3"
                                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 placeholder-gray-400 text-sm transition-all"
                                  placeholder="Describe los beneficios principales de este plan para tus usuarios...">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ciclo de facturación (días)</label>
                            <input type="number" name="billing_cycle_days" value="{{ old('billing_cycle_days', 30) }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-sm transition-all"
                                   min="1" max="365" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Límite de clientes</label>
                            <input type="number" name="client_limit" value="{{ old('client_limit') }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-sm transition-all"
                                   placeholder="Ilimitado" min="1">
                            <p class="text-xs text-gray-400 mt-1.5">Dejar vacío para no asignar límites.</p>
                        </div>

                        <div class="flex items-center pt-2 md:pt-6">
                            <label class="relative flex items-center gap-3 cursor-pointer select-none">
                                <input type="checkbox" name="is_active" value="1"
                                       class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition-all"
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-gray-700">Disponibilizar plan inmediatamente</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-indigo-600 mb-4">Estructura de Precios</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Monto / Precio</label>
                            <div class="relative rounded-lg shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-400 sm:text-sm">$</span>
                                </div>
                                <input type="number" name="amount" value="{{ old('amount') }}"
                                       class="w-full pl-7 rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-sm transition-all"
                                       step="0.01" min="0" required placeholder="0.00">
                            </div>
                            <p class="text-xs text-gray-400 mt-1.5">Este monto se utilizará para dar de alta el <b>Price</b> en la API de Stripe.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Divisa / Moneda</label>
                            <input type="text" name="currency" value="{{ old('currency', 'mxn') }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-sm uppercase transition-all"
                                   maxlength="3" required placeholder="mxn">
                            <p class="text-xs text-gray-400 mt-1.5">Código ISO de 3 letras. Por defecto: <b>MXN</b>.</p>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">Método de Procesamiento</label>
                    
                    @php $provider = old('payment_provider', 'stripe'); @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="relative flex items-start p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50/50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/30 transition-all shadow-sm">
                            <input type="radio" name="payment_provider" value="stripe"
                                   class="mt-1 text-indigo-600 border-gray-300 focus:ring-indigo-500 transition-all"
                                   @checked($provider === 'stripe')>
                            <span class="ml-3">
                                <span class="block font-semibold text-sm text-gray-900 flex items-center gap-1.5">
                                    Stripe Autogestionado
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">Recomendado</span>
                                </span>
                                <span class="block text-xs text-gray-500 mt-1">Crea y vincula el Producto/Precio de forma automatizada en tu cuenta productiva de Stripe.</span>
                            </span>
                        </label>

                        <label class="relative flex items-start p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50/50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/30 transition-all shadow-sm">
                            <input type="radio" name="payment_provider" value="manual"
                                   class="mt-1 text-indigo-600 border-gray-300 focus:ring-indigo-500 transition-all"
                                   @checked($provider === 'manual')>
                            <span class="ml-3">
                                <span class="block font-semibold text-sm text-gray-900">Registro Manual</span>
                                <span class="block text-xs text-gray-500 mt-1">Ideal para flujos tradicionales (transferencias, efectivo). El recaudo se gestiona desde el panel interno.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="bg-gray-50/70 border border-gray-200/60 rounded-xl p-5 space-y-4">
                    <div class="flex items-center gap-2 text-gray-700">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Metadatos de Sincronización API</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Stripe Product ID</label>
                            <input type="text" name="stripe_product_id" value="{{ old('stripe_product_id') }}"
                                   class="w-full rounded-lg border-gray-200 bg-gray-100 text-gray-400 text-xs font-mono select-none"
                                   placeholder="Generado por el servidor" readonly>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Stripe Price ID</label>
                            <input type="text" name="stripe_price_id" value="{{ old('stripe_price_id') }}"
                                   class="w-full rounded-lg border-gray-200 bg-gray-100 text-gray-400 text-xs font-mono select-none"
                                   placeholder="Generado por el servidor" readonly>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 italic">Los identificadores de arriba se poblarán en tiempo de respuesta una vez Stripe retorne el webhook o callback de creación.</p>
                </div>

                <div class="flex justify-end items-center gap-3 pt-4 border-t border-gray-100">
                    <a href="{{ route('admin.plans.index') }}"
                       class="px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all">
                        <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Guardar y Publicar Plan
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>