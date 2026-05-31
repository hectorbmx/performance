<x-app-layout>
    {{-- Toast de Éxito --}}
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
        {{-- Contenedor Expandido --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Encabezado con Diseño --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Nueva Suscripción</h1>
                    <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Asigna un plan de membresía a un coach y configura sus reglas de vigencia y pago.
                    </p>
                </div>

                <a href="{{ route('admin.subscriptions.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition-all focus:ring-2 focus:ring-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Regresar
                </a>
            </div>

            {{-- Manejo de Errores --}}
            @if ($errors->any())
                <div class="mb-8 bg-red-50 border-l-4 border-red-500 rounded-r-xl p-4 flex gap-3 shadow-sm">
                    <div class="text-red-500">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-red-800 tracking-tight">Detectamos inconsistencias en el formulario</h4>
                        <ul class="text-xs text-red-700 mt-1 list-disc list-inside opacity-80">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form id="subscriptionForm" method="POST" action="{{ route('admin.subscriptions.store') }}">
                @csrf
                
                {{-- Grid de 3 Columnas (2 para datos principales, 1 para configuraciones laterales) --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {{-- Columna Izquierda: Datos del Plan y Relaciones --}}
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8 space-y-6">
                            
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2 border-b border-gray-50 pb-4">
                                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                </span>
                                Parámetros de la Membresía
                            </h3>

                            {{-- Asignación de Coach --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Asignar a un Coach</label>
                                <select name="coach_id" class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm cursor-pointer" required>
                                    <option value="">Selecciona un coach administrativo</option>
                                    @foreach ($coaches as $c)
                                        <option value="{{ $c->id }}" @selected(old('coach_id', $selectedCoachId ?? null) == $c->id)>
                                            {{ $c->coachProfile?->display_name ?? $c->name }} ({{ $c->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Selección de Plan --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Plan de Membresía Destino</label>
                                <select name="membership_plan_id" class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm cursor-pointer" required>
                                    <option value="">Selecciona el plan de cobro</option>
                                    @foreach ($plans as $p)
                                        <option value="{{ $p->id }}" @selected(old('membership_plan_id') == $p->id)>
                                            {{ $p->name }} — (Ciclo: {{ $p->billing_cycle_days }} días · Límite: {{ $p->client_limit ?? 'Ilimitado' }} usuarios)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Fechas de Vigencia --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha de Inicio de Cobertura</label>
                                    <input type="date" name="starts_at"
                                           value="{{ old('starts_at', now()->toDateString()) }}"
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha de Vencimiento</label>
                                    <input type="date" name="ends_at"
                                           value="{{ old('ends_at', now()->addDays(30)->toDateString()) }}"
                                           class="w-full rounded-xl border-gray-200 bg-gray-50/30 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all text-sm" required>
                                </div>
                            </div>
                        </div>

                        {{-- Botonera de Envío --}}
                        <div class="flex items-center justify-between p-2">
                            <p class="text-xs text-gray-400 italic max-w-sm">
                                Nota: Dependiendo de tu elección, el sistema puede solicitar pasarela de pago o generar saldo pendiente de cobro.
                            </p>
                            <div class="flex gap-3">
                                <a href="{{ route('admin.subscriptions.index') }}" class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
                                    Cancelar
                                </a>
                                <button type="submit" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    Crear Suscripción
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Columna Derecha: Estado, Períodos de Gracia y Switchs --}}
                    <div class="space-y-6">
                        
                        {{-- Card Premium: Configuración de Cobros e Incentivos --}}
                        <div class="bg-indigo-900 rounded-2xl shadow-xl p-6 md:p-8 text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-indigo-800 rounded-full opacity-50"></div>
                            
                            <h3 class="text-lg font-bold mb-6 flex items-center gap-2 relative z-10">
                                <span class="w-8 h-8 rounded-lg bg-indigo-500/30 text-indigo-100 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </span>
                                Cobros y Estatus
                            </h3>

                            <div class="space-y-6 relative z-10">
                                {{-- Select de Estatus --}}
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-indigo-300 mb-2">Estatus Operativo</label>
                                    <select name="status" class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 focus:border-indigo-400 text-white transition-all appearance-none cursor-pointer" required>
                                        @php $st = old('status','active'); @endphp
                                        <option value="active" @selected($st==='active') class="text-gray-900">Active (Activo)</option>
                                        <option value="past_due" @selected($st==='past_due') class="text-gray-900">Past Due (Vencido)</option>
                                        <option value="suspended" @selected($st==='suspended') class="text-gray-900">Suspended (Suspendido)</option>
                                        <option value="cancelled" @selected($st==='cancelled') class="text-gray-900">Cancelled (Cancelado)</option>
                                    </select>
                                </div>

                                {{-- Días de gracia --}}
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-indigo-300 mb-2">Días de Gracia Disponibles</label>
                                    <input type="number" name="grace_days" value="{{ old('grace_days', 5) }}" min="0" max="60"
                                           class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 focus:border-indigo-400 text-white placeholder-indigo-300 transition-all text-sm">
                                    <p class="text-[11px] text-indigo-200 mt-1.5 leading-relaxed">Permite conservar el acceso transitorio aunque se encuentre en estado "unpaid".</p>
                                </div>

                                {{-- Días para recordatorio --}}
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-indigo-300 mb-2">Alerta de Renovación</label>
                                    <div class="relative rounded-xl shadow-sm">
                                        <input type="number" name="reminder_days_before" value="{{ old('reminder_days_before', 5) }}" min="0" max="60"
                                               class="w-full rounded-xl border-transparent bg-white/10 focus:bg-white/20 focus:ring-0 focus:border-indigo-400 text-white placeholder-indigo-300 transition-all text-sm pr-12">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-xs font-medium text-indigo-200">días antes</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Switch Interno de Pago Inline --}}
                                <div class="pt-4 border-t border-white/10 flex items-start gap-3">
                                    <input type="checkbox" name="register_payment_now" value="1" id="register_payment_now"
                                           class="mt-1 w-4 h-4 text-indigo-600 border-white/20 rounded bg-white/10 focus:ring-0 focus:ring-offset-0 transition-all"
                                           {{ old('register_payment_now') ? 'checked' : '' }}>
                                    <label for="register_payment_now" class="text-xs font-medium text-indigo-100 select-none cursor-pointer leading-relaxed">
                                        <span class="block font-bold text-white text-sm mb-0.5">Registrar cobro de inmediato</span>
                                        Genera automáticamente un ledger/recibo pagado en base a este plan.
                                    </label>
                                </div>

                            </div>
                        </div>

                        {{-- Card Informativa Dinámica --}}
                        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-6 flex gap-3 items-start">
                            <div class="p-2 bg-amber-50 rounded-lg text-amber-600 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Corte Automatizado</p>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Al cumplirse la fecha de vencimiento + los días de gracia, los accesos del Coach al ecosistema API se congelarán automáticamente.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Estilizado con Backdrop Blur --}}
    <div id="payModal" class="fixed inset-0 hidden items-center justify-center bg-gray-900/40 backdrop-blur-sm z-50 transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-100 transform transition-all m-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <h2 class="text-xl font-extrabold text-gray-900 tracking-tight">¿Registrar pago ahora?</h2>
            </div>
            
            <p class="text-sm text-gray-600 leading-relaxed">
                Puedes asentar el flujo de efectivo inmediatamente en caja o guardar la suscripción con estatus <span class="font-bold text-amber-600">UNPAID (Pendiente)</span> para procesarla posteriormente.
            </p>

            <div class="mt-6 flex justify-end gap-3 border-t border-gray-50 pt-4">
                <button type="button" id="btnNoPay"
                    class="px-5 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                    No, después
                </button>

                <button type="button" id="btnYesPay"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">
                    Sí, registrar pago
                </button>
            </div>
        </div>
    </div>
</x-app-layout>

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
        if (pendingAction) return;
        e.preventDefault();
        openModal();
    });

    btnNo.addEventListener('click', function() {
        pendingAction = 'no';
        closeModal();
        form.submit();
    });

    btnYes.addEventListener('click', function() {
        let input = form.querySelector('input[name="register_payment_now"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'register_payment_now';
            input.value = '1';
            form.appendChild(input);
        } else {
            input.checked = true; // Asegura que si existe el checkbox visual, se mande activo
            input.value = '1';
        }

        pendingAction = 'yes';
        closeModal();
        form.submit();
    });
})();
</script>