<x-app-layout>
    <div class="py-10 bg-slate-50/50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <!-- Encabezado del Panel -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-5">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Panel Coach</h1>
                    <p class="text-sm text-slate-500 mt-1">Resumen rápido de clientes, cobros y renovaciones activas.</p>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('coach.clients.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-all duration-200">
                        Ver clientes
                    </a>
                    <a href="{{ route('coach.clients.create') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-600/10 hover:bg-indigo-700 transition-all duration-200 hover:scale-[1.02]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo cliente
                    </a>
                </div>
            </div>

            <!-- Sección de KPIs con Animación y Color de Acento -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($kpis as $kpi)
                    @php
                        // Configuramos variaciones más vivas y modernas basadas en tu tone original
                        $badgeStyle = [
                            'emerald' => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 ring-emerald-500/10',
                            'indigo'  => 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20 ring-indigo-500/10',
                            'amber'   => 'bg-amber-500/10 text-amber-600 border-amber-500/20 ring-amber-500/10',
                            'red'     => 'bg-red-500/10 text-red-600 border-red-500/20 ring-red-500/10',
                            'sky'     => 'bg-sky-500/10 text-sky-600 border-sky-500/20 ring-sky-500/10',
                            'slate'   => 'bg-slate-500/10 text-slate-600 border-slate-500/20 ring-slate-500/10',
                        ][$kpi['tone']] ?? 'bg-slate-50 text-slate-600 border-slate-200';

                        // Un sutil borde izquierdo de color para dar el toque "vivo" al card
                        $borderLeft = [
                            'emerald' => 'hover:border-l-emerald-500',
                            'indigo'  => 'hover:border-l-indigo-500',
                            'amber'   => 'hover:border-l-amber-500',
                            'red'     => 'hover:border-l-red-500',
                            'sky'     => 'hover:border-l-sky-500',
                            'slate'   => 'hover:border-l-slate-500',
                        ][$kpi['tone']] ?? 'hover:border-l-slate-400';
                    @endphp

                    <div class="group relative rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-md border-l-4 border-l-transparent {{ $borderLeft }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 group-hover:text-slate-500 transition-colors">{{ $kpi['label'] }}</p>
                                <p class="mt-2 text-3xl font-black tracking-tight text-slate-900">{{ $kpi['value'] }}</p>
                            </div>
                            <!-- Contenedor del Icono/Indicador -->
                            <div class="h-10 w-10 shrink-0 rounded-xl border flex items-center justify-center ring-4 transition-all duration-300 group-hover:scale-110 {{ $badgeStyle }}">
                                <span class="w-2 h-2 rounded-full bg-current animate-pulse"></span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between">
                            <p class="text-xs font-medium text-slate-500">{{ $kpi['hint'] }}</p>
                            <span class="text-slate-300 group-hover:text-slate-500 group-hover:translate-x-0.5 transition-all text-xs">→</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Bloques Secundarios (Tablas / Listados) -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                
                <!-- Card: Por vencer pronto -->
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden hover:shadow-md/50 transition-shadow duration-300">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Por vencer pronto
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Membresías que terminan en los próximos 7 días.</p>
                    </div>

                    <div class="p-6">
                        @if($expiringSoon->isEmpty())
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-sm text-slate-500">
                                No hay membresías por vencer esta semana.
                            </div>
                        @else
                            <div class="overflow-x-auto -mx-6 -my-6">
                                <table class="min-w-full text-sm divide-y divide-slate-100">
                                    <thead class="bg-slate-50/30 text-xs font-bold uppercase tracking-wider text-slate-400">
                                        <tr>
                                            <th class="py-3 px-6 text-left">Cliente</th>
                                            <th class="py-3 px-3 text-left">Plan</th>
                                            <th class="py-3 px-3 text-left">Vence</th>
                                            <th class="py-3 px-3 text-left">Estado</th>
                                            <th class="py-3 px-6 text-right">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach($expiringSoon as $membership)
                                            <tr class="hover:bg-slate-50/60 transition-colors duration-150">
                                                <td class="py-3 px-6">
                                                    <div class="font-semibold text-slate-900">{{ $membership->client?->full_name ?: 'Cliente #' . $membership->client_id }}</div>
                                                    <div class="text-xs text-slate-400 font-medium">{{ $membership->client?->email ?: $membership->client?->phone }}</div>
                                                </td>
                                                <td class="py-3 px-3 text-slate-600 font-medium">{{ $membership->plan_name_snapshot }}</td>
                                                <td class="py-3 px-3">
                                                    <span class="font-semibold text-slate-800">{{ optional($membership->ends_at)->format('d/m/Y') }}</span>
                                                    <div class="text-[11px] font-bold text-amber-600">
                                                        En {{ $today->diffInDays($membership->ends_at, false) }} días
                                                    </div>
                                                </td>
                                                <td class="py-3 px-3">
                                                    @if($membership->billing_status === 'paid')
                                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-600/10">PAGADO</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-600/10">PENDIENTE</span>
                                                    @endif
                                                </td>
                                                <td class="py-3 px-6 text-right">
                                                    <a href="{{ $membership->billing_status === 'paid'
                                                        ? route('coach.client-memberships.create', $membership->client_id)
                                                        : route('coach.client-payments.create', $membership) }}"
                                                       class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition-colors">
                                                        {{ $membership->billing_status === 'paid' ? 'Renovar' : 'Cobrar' }}
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card: Últimos clientes -->
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden hover:shadow-md/50 transition-shadow duration-300">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            Últimos clientes
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Altas recientes y acceso rápido a edición.</p>
                    </div>

                    <div class="p-6">
                        @if($latestClients->isEmpty())
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-sm text-slate-500">
                                Todavía no hay clientes registrados.
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($latestClients as $client)
                                    <div class="group/item flex items-center justify-between gap-4 rounded-xl border border-slate-100 p-3.5 bg-white shadow-sm hover:border-slate-200 hover:bg-slate-50/30 transition-all duration-200">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="truncate font-semibold text-slate-900 group-hover/item:text-indigo-600 transition-colors">{{ $client->full_name ?: 'Cliente #' . $client->id }}</p>
                                                @if($client->is_active)
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/10">Activo</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">Inactivo</span>
                                                @endif
                                            </div>
                                            <p class="truncate text-xs text-slate-400 mt-0.5 font-medium">
                                                {{ $client->email ?: $client->phone ?: 'Sin datos de contacto' }}
                                                <span class="mx-1 text-slate-200">•</span>
                                                Reg. {{ optional($client->created_at)->format('d/m/Y') }}
                                            </p>
                                        </div>

                                        <a href="{{ route('coach.clients.edit', $client) }}"
                                           class="shrink-0 inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-all group-hover/item:border-slate-300">
                                            Abrir
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1 text-slate-400 group-hover/item:text-slate-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
