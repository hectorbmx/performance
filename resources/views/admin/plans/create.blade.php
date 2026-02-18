<x-app-layout>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Nuevo Plan</h1>

                <a href="{{ route('admin.plans.index') }}"
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

            <form method="POST"
                  action="{{ route('admin.plans.store') }}"
                  class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre del plan</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300"
                           required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Descripción</label>
                    <textarea name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ciclo (días)</label>
                        <input type="number" name="billing_cycle_days"
                               value="{{ old('billing_cycle_days', 30) }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               min="1" max="365" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Límite de clientes</label>
                        <input type="number" name="client_limit"
                               value="{{ old('client_limit') }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               min="1">
                        <p class="text-xs text-gray-500 mt-1">Vacío = ilimitado</p>
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input type="checkbox" name="is_active" value="1"
                               class="rounded border-gray-300"
                               {{ old('is_active', true) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Plan activo</span>
                    </div>
                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stripe Product ID</label>
                        <input type="text" name="stripe_product_id"
                               value="{{ old('stripe_product_id') }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               placeholder="prod_...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stripe Price ID</label>
                        <input type="text" name="stripe_price_id"
                               value="{{ old('stripe_price_id') }}"
                               class="mt-1 block w-full rounded-md border-gray-300"
                               placeholder="price_...">
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.plans.index') }}"
                       class="px-4 py-2 rounded-md border border-gray-300 text-gray-700">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        Guardar Plan
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
