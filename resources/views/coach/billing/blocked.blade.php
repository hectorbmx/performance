<x-app-layout>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded-lg p-8">
                <h1 class="text-2xl font-bold text-gray-900">Acceso restringido</h1>

                <p class="mt-3 text-gray-700">
                    {{ $reason }}
                </p>

                @if($subscription)
                    <div class="mt-6 border rounded-lg p-4 bg-gray-50">
                        <div class="font-medium">{{ $subscription->plan_name_snapshot }}</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Estatus: <span class="font-semibold">{{ strtoupper($subscription->billing_status) }}</span>
                            @if($subscription->grace_until)
                                · Gracia hasta: <span class="font-semibold">{{ $subscription->grace_until->format('Y-m-d') }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            Vigencia: {{ $subscription->starts_at->format('Y-m-d') }} → {{ $subscription->ends_at->format('Y-m-d') }}
                        </div>
                    </div>
                @endif

                <div class="mt-8 flex gap-3">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800">
                            Cerrar sesión
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
