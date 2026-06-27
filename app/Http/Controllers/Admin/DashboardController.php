<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\CoachSubscription;
use App\Models\Payment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $soonLimit = $today->copy()->addDays(7);

        $coachesTotal = User::role('coach')->count();

        $activeCoaches = CoachProfile::where('status', 'active')->count();
        $inactiveCoaches = CoachProfile::where('status', 'inactive')->count();
        $suspendedCoaches = CoachProfile::whereNotNull('suspended_at')->count();

        $monthlyRevenue = Payment::query()
            ->whereBetween('paid_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $paymentsThisMonth = Payment::query()
            ->whereBetween('paid_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $expiringSubscriptions = CoachSubscription::query()
            ->with('coach.coachProfile')
            ->where('status', 'active')
            ->whereBetween('ends_at', [$today->toDateString(), $soonLimit->toDateString()])
            ->orderBy('ends_at')
            ->limit(8)
            ->get();

        $expiringSubscriptionsCount = CoachSubscription::query()
            ->where('status', 'active')
            ->whereBetween('ends_at', [$today->toDateString(), $soonLimit->toDateString()])
            ->count();

        $overdueSubscriptions = CoachSubscription::query()
            ->with('coach.coachProfile')
            ->where('billing_status', 'unpaid')
            ->where(function ($query) use ($today) {
                $query->whereNull('grace_until')
                    ->orWhereDate('grace_until', '<', $today->toDateString());
            })
            ->orderBy('grace_until')
            ->limit(8)
            ->get();

        $overdueSubscriptionsCount = CoachSubscription::query()
            ->where('billing_status', 'unpaid')
            ->where(function ($query) use ($today) {
                $query->whereNull('grace_until')
                    ->orWhereDate('grace_until', '<', $today->toDateString());
            })
            ->count();

        $graceSubscriptionsCount = CoachSubscription::query()
            ->where('billing_status', 'unpaid')
            ->whereNotNull('grace_until')
            ->whereDate('grace_until', '>=', $today->toDateString())
            ->count();

        $dueThisMonth = CoachSubscription::query()
            ->whereBetween('ends_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $paidDueThisMonth = CoachSubscription::query()
            ->whereBetween('ends_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('billing_status', 'paid')
            ->count();

        $collectionRate = $dueThisMonth > 0
            ? round(($paidDueThisMonth / $dueThisMonth) * 100)
            : 100;

        return view('admin.dashboard', compact(
            'coachesTotal',
            'activeCoaches',
            'inactiveCoaches',
            'suspendedCoaches',
            'monthlyRevenue',
            'paymentsThisMonth',
            'expiringSubscriptions',
            'expiringSubscriptionsCount',
            'overdueSubscriptions',
            'overdueSubscriptionsCount',
            'graceSubscriptionsCount',
            'dueThisMonth',
            'paidDueThisMonth',
            'collectionRate'
        ));
    }
}
