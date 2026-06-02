<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPayment;

class DashboardController extends Controller
{
    public function index()
    {
        $coachId = auth()->id();
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $expiringUntil = $today->copy()->addDays(7);

        $activeClients = Client::query()
            ->where('coach_id', $coachId)
            ->where('is_active', true)
            ->count();

        $newClientsThisMonth = Client::query()
            ->where('coach_id', $coachId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $expiringMemberships = ClientMembership::query()
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->whereBetween('ends_at', [$today->toDateString(), $expiringUntil->toDateString()])
            ->count();

        $expiredMemberships = ClientMembership::query()
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->whereDate('ends_at', '<', $today->toDateString())
            ->count();

        $monthlyRevenue = ClientPayment::query()
            ->where('coach_id', $coachId)
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('final_amount');

        $pendingPaymentsQuery = ClientMembership::query()
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->where('billing_status', '!=', 'paid');

        $pendingPayments = (clone $pendingPaymentsQuery)->count();
        $pendingAmount = (clone $pendingPaymentsQuery)->sum('price_snapshot');

        $expiringSoon = ClientMembership::query()
            ->with('client:id,first_name,last_name,email,phone')
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->whereBetween('ends_at', [$today->toDateString(), $expiringUntil->toDateString()])
            ->orderBy('ends_at')
            ->limit(8)
            ->get();

        $latestClients = Client::query()
            ->where('coach_id', $coachId)
            ->latest()
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'created_at', 'is_active']);

        $kpis = [
            [
                'label' => 'Clientes activos',
                'value' => number_format($activeClients),
                'hint' => 'Clientes habilitados en tu cuenta',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Clientes nuevos',
                'value' => number_format($newClientsThisMonth),
                'hint' => 'Altas durante ' . now()->translatedFormat('F'),
                'tone' => 'indigo',
            ],
            [
                'label' => 'Por vencer',
                'value' => number_format($expiringMemberships),
                'hint' => 'Membresias en los proximos 7 dias',
                'tone' => 'amber',
            ],
            [
                'label' => 'Vencidas',
                'value' => number_format($expiredMemberships),
                'hint' => 'Membresias activas con fecha vencida',
                'tone' => 'red',
            ],
            [
                'label' => 'Ingresos del mes',
                'value' => '$' . number_format((float) $monthlyRevenue, 2),
                'hint' => 'Pagos completados este mes',
                'tone' => 'sky',
            ],
            [
                'label' => 'Pagos pendientes',
                'value' => number_format($pendingPayments),
                'hint' => '$' . number_format((float) $pendingAmount, 2) . ' por cobrar',
                'tone' => 'slate',
            ],
        ];

        return view('coach.dashboard', [
            'kpis' => $kpis,
            'expiringSoon' => $expiringSoon,
            'latestClients' => $latestClients,
            'today' => $today,
        ]);
    }
}
