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
                <h1 class="text-2xl font-bold mb-2">Asignar Membresía</h1>
                <p class="text-gray-600 mb-6">Cliente: <span class="font-medium">{{ $client->full_name }}</span></p>

                {{-- Alerta si ya tiene membresía activa --}}
                @if($activeMembership)
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                        <p class="font-medium">⚠️ Este cliente ya tiene una membresía activa</p>
                        <p class="text-sm mt-1">
                            Plan actual: <strong>{{ $activeMembership->plan_name_snapshot }}</strong><br>
                            Vigencia: {{ $activeMembership->starts_at->format('d/m/Y') }} - {{ $activeMembership->ends_at->format('d/m/Y') }}
                        </p>
                        <p class="text-sm mt-2 font-medium">
                            La nueva membresía comenzará el {{ $activeMembership->ends_at->addDay()->format('d/m/Y') }}
                        </p>
                    </div>
                @endif

                @if($plans->isEmpty())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                        No tienes planes activos. 
                        <a href="{{ route('coach.membresias.create') }}" class="underline font-medium">
                            Crea tu primer plan aquí
                        </a>
                    </div>
                @else
                    <form action="{{ route('coach.client-memberships.store', $client) }}" 
                          method="POST" 
                          id="membershipForm">
                        @csrf

                        {{-- Seleccionar Plan --}}
                        <div class="mb-4">
                            <label for="coach_client_plan_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Plan <span class="text-red-500">*</span>
                            </label>
                            <select name="coach_client_plan_id"
                                    id="coach_client_plan_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required>
                                <option value="">Selecciona un plan</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" 
                                            data-price="{{ $plan->price }}"
                                            data-days="{{ $plan->billing_cycle_days }}"
                                            {{ old('coach_client_plan_id') == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }} - ${{ number_format($plan->price, 2) }} 
                                        ({{ $plan->billing_cycle_days }} días)
                                    </option>
                                @endforeach
                            </select>
                            @error('coach_client_plan_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Vista previa de fechas --}}
                        <div id="datePreview" class="mb-4 p-4 bg-gray-50 rounded-md hidden">
                            <p class="text-sm font-medium text-gray-700 mb-2">Vista previa de vigencia:</p>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div>Inicio: <span id="previewStart" class="font-medium"></span></div>
                                <div>Fin: <span id="previewEnd" class="font-medium"></span></div>
                                <div>Duración: <span id="previewDuration" class="font-medium"></span></div>
                            </div>
                        </div>

                        {{-- Días antes para recordatorio --}}
                        <div class="mb-4">
                            <label for="reminder_days_before" class="block text-sm font-medium text-gray-700 mb-2">
                                Días antes para recordatorio (opcional)
                            </label>
                            <input type="number"
                                   name="reminder_days_before"
                                   id="reminder_days_before"
                                   value="{{ old('reminder_days_before', 5) }}"
                                   min="1"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-sm text-gray-500">
                                Ejemplo: 5 días antes del vencimiento
                            </p>
                            @error('reminder_days_before')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sección de Cobro --}}
                        <div class="mb-6 p-4 border border-gray-200 rounded-md">
                            <h3 class="text-lg font-medium mb-4">Cobro</h3>
                            
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           name="register_payment" 
                                           id="register_payment"
                                           value="1"
                                           {{ old('register_payment') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">
                                        Registrar pago ahora
                                    </span>
                                </label>
                                <p class="mt-1 ml-6 text-sm text-gray-500">
                                    Puedes crear la suscripción como no pagada y registrar el pago después.
                                </p>
                            </div>

                            {{-- Días de gracia (solo si NO se registra pago) --}}
                            <div id="graceDaysSection" class="{{ old('register_payment') ? 'hidden' : '' }}">
                                <label for="grace_days" class="block text-sm font-medium text-gray-700 mb-2">
                                    Días de gracia
                                </label>
                                <input type="number"
                                       name="grace_days"
                                       id="grace_days"
                                       value="{{ old('grace_days', 5) }}"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-sm text-gray-500">
                                    Durante la gracia puede seguir con acceso aunque esté "unpaid".
                                </p>
                                @error('grace_days')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Botones --}}
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('coach.clients.index') }}"
                               class="px-4 py-2 text-gray-700 hover:text-gray-900">
                                Cancelar
                            </a>

                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Asignar membresía
                            </button>
                        </div>
                    </form>
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        // Toggle días de gracia según checkbox
        document.getElementById('register_payment').addEventListener('change', function() {
            const graceDaysSection = document.getElementById('graceDaysSection');
            if (this.checked) {
                graceDaysSection.classList.add('hidden');
            } else {
                graceDaysSection.classList.remove('hidden');
            }
        });

        // Vista previa de fechas
        const planSelect = document.getElementById('coach_client_plan_id');
        const datePreview = document.getElementById('datePreview');
        
        planSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption.value) {
                datePreview.classList.add('hidden');
                return;
            }

            const days = parseInt(selectedOption.dataset.days);
            
            @if($activeMembership)
                const startDate = new Date('{{ $activeMembership->ends_at->addDay()->format('Y-m-d') }}');
            @else
                const startDate = new Date();
            @endif
            
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + days);

            document.getElementById('previewStart').textContent = startDate.toLocaleDateString('es-MX');
            document.getElementById('previewEnd').textContent = endDate.toLocaleDateString('es-MX');
            document.getElementById('previewDuration').textContent = days + ' días';
            
            datePreview.classList.remove('hidden');
        });
    </script>
    @endpush
</x-app-layout>