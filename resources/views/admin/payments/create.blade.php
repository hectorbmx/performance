<x-app-layout>
    {{-- Toast Notificación de Éxito --}}
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
             class="fixed top-5 right-5 z-50 max-w-sm w-full bg-white shadow-2xl rounded-2xl border border-emerald-100 p-4 flex items-start gap-3"
             style="display: none;">
            <div class="flex-shrink-0 p-1.5 bg-emerald-100 rounded-xl text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="flex-1 pt-0.5">
                <p class="text-sm font-bold text-gray-900">¡Éxito!</p>
                <p class="text-xs text-gray-500 mt-1">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <div class="py-10 bg-gray-50/30 min-h-screen">
        {{-- Contenedor Expandido Premium --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Encabezado Principal --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Registrar Nuevo Pago</h1>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Asienta transacciones manuales de conciliación para las membresías de la plataforma.
                    </p>
                </div>

                <a href="{{ route('admin.payments.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition-all focus:ring-2 focus:ring-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Regresar
                </a>
            </div>

            {{-- Alertas de Validación --}}
            @if ($errors->any())
                <div class="mb-8 bg-red-50 border-l-4 border-red-500 rounded-r-xl p-4 flex gap-3 shadow-sm">
                    <div class="text-red-500">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-red-800 tracking-tight">Detectamos inconsistencias en la captura</h4>
                        <ul class="text-xs text-red-700 mt-1 list-disc list-inside opacity-80">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Formulario Principal con Grid de 3 Columnas --}}
            <form method="POST" action="{{ route('admin.payments.store') }}" enctype="multipart/form-data">
                @csrf
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {{-- Columna de Datos Financieros (Ancha) --}}
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8 space-y-6">
                            
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2 border-b border-gray-50 pb-4">
                                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </span>
                                Detalles de la Transacción
                            </h3>

                            {{-- Selección de Suscripción --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Vincular a Suscripción Vigente</label>
                                <select name="coach_subscription_id" class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm cursor-pointer" required>
                                    <option value="">Selecciona la cuenta destino del coach</option>
                                    @foreach ($subs as $s)
                                        <option value="{{ $s->id }}" @selected(old('coach_subscription_id', $selectedSubId ?? null) == $s->id)>
                                            {{ $s->coach?->coachProfile?->display_name ?? $s->coach?->name }} 
                                            · {{ $s->plan_name_snapshot }} 
                                            · [{{ $s->starts_at->format('Y-m-d') }} → {{ $s->ends_at->format('Y-m-d') }}] 
                                            · ({{ strtoupper($s->status) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Campos en Fila: Monto, Moneda, Fecha --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Monto Neto</label>
                                    <div class="relative rounded-xl shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                            <span class="text-gray-400 text-sm">$</span>
                                        </div>
                                        <input type="number" step="0.01" name="amount" value="{{ old('amount', 0.00) }}"
                                               class="w-full rounded-xl border-gray-200 bg-gray-50/30 pl-8 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-medium text-gray-900" required>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Divisa / Moneda</label>
                                    <input type="text" name="currency" value="{{ old('currency', 'MXN') }}" maxlength="3"
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm font-bold uppercase text-center tracking-wider" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha de Cobro</label>
                                    <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}"
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm" required>
                                </div>
                            </div>

                            {{-- Referencia Adicional --}}
                            <div class="pt-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Clave o ID de Referencia (Opcional)</label>
                                <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Ej. TRF-839201923 o Código de autorización"
                                       class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm placeholder-gray-400">
                            </div>

                        </div>

                        {{-- Footer del Formulario con Botonera --}}
                        <div class="flex items-center justify-between p-2">
                            <p class="text-xs text-gray-400 italic max-w-sm">
                                Asegúrate de cotejar que la referencia bancaria corresponda exactamente al ledger de la suscripción seleccionada.
                            </p>
                            <div class="flex gap-3">
                                <a href="{{ route('admin.payments.index') }}" class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
                                    Cancelar
                                </a>
                                <button type="submit" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Registrar Pago
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Columna Lateral Derecha (Auditoría y Carga de Archivos) --}}
                    <div class="space-y-6">
                        
                        {{-- Card Premium Financiera --}}
                        <div class="bg-slate-900 rounded-2xl shadow-xl p-6 md:p-8 text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-slate-800 rounded-full opacity-40"></div>
                            
                            <h3 class="text-lg font-bold mb-6 flex items-center gap-2 relative z-10">
                                <span class="w-8 h-8 rounded-lg bg-slate-800 text-indigo-400 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                </span>
                                Origen e Infraestructura
                            </h3>

                            <div class="space-y-6 relative z-10">
                                {{-- Método de Pago (Estático pero Estilizado) --}}
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Método de Captura</label>
                                    <div class="relative">
                                        <select name="method" class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 text-white transition-all appearance-none cursor-not-allowed" readonly>
                                            <option value="manual" selected class="text-gray-900">Manual (Efectivo / Transferencia)</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-slate-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dropzone/Input para Comprobante Digital --}}
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Comprobante Físico (PDF / IMG)</label>
                                    <div class="mt-1 flex justify-center px-4 py-4 border-2 border-slate-700 border-dashed rounded-xl hover:border-indigo-500/50 transition-all bg-slate-950/30">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-8 w-8 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20a4 4 0 004 4h24a4 4 0 004-4V20L28 8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M28 8v12h12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-xs text-slate-300 justify-center">
                                                <label class="relative cursor-pointer font-bold text-indigo-400 hover:text-indigo-300">
                                                    <span>Sube un archivo</span>
                                                    <input type="file" name="receipt" class="sr-only">
                                                </label>
                                            </div>
                                            <p class="text-[10px] text-slate-400">Máximo permitido: 5MB</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Card de Alerta Técnica S3 --}}
                        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-6 flex gap-3 items-start">
                            <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5 5 0 00-4.591-2.854A5 5 0 005 13a4 4 0 00-2 1.998V15z"></path></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Almacenamiento Local Habilitado</p>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Los archivos están preparados estructuralmente para migrar al Driver de AWS S3 sin alterar las referencias del modelo.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>