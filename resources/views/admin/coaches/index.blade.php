<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Coaches</h1>

                <a href="{{ route('admin.coaches.create') }}"
                   class="bg-black text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    + Nuevo Coach
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Display</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estatus</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($coaches as $coach)
                            @php
                                $status = $coach->coachProfile?->status;
                                $statusClass = match ($status) {
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-gray-100 text-gray-800',
                                    'suspended' => 'bg-red-100 text-red-800',
                                    default => 'bg-yellow-100 text-yellow-800',
                                };
                            @endphp

                            <tr>
                                <td class="px-6 py-4">{{ $coach->name }}</td>
                                <td class="px-6 py-4">{{ $coach->email }}</td>
                                <td class="px-6 py-4">{{ $coach->coachProfile?->display_name }}</td>
                                <td class="px-6 py-4">
                                    @php
                                            $sub = $coach->latestSubscription;
                                        @endphp

                                        @if($sub)
                                            <div class="font-medium">{{ $sub->plan_name_snapshot }}</div>
                                            <div class="text-sm text-gray-500">
                                                Vence: {{ $sub->ends_at?->format('Y-m-d') }}
                                            </div>
                                        @else
                                            <a href="{{ route('admin.subscriptions.create', ['coach_id' => $coach->id]) }}"
                                            class="inline-flex items-center px-3 py-1 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                                                Asignar plan
                                            </a>
                                        @endif

                                </td>

                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                        {{ $status ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-3">
                                    <a href="{{ route('admin.coaches.edit', $coach) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        Editar
                                    </a>

                                     <form action="{{ route('admin.coaches.toggleStatus', $coach) }}"
                                        method="POST"
                                        class="inline">
                                        @csrf

                                        @php
                                            $isActive = $coach->coachProfile?->status === 'active';
                                        @endphp

                                        <button type="submit"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition
                                                {{ $isActive ? 'bg-green-500' : 'bg-red-500' }}">
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition
                                                {{ $isActive ? 'translate-x-6' : 'translate-x-1' }}">
                                            </span>
                                        </button>
                                    </form>

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    No hay coaches registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $coaches->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
