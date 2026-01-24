<?php

namespace App\Http\Middleware;

use App\Models\CoachSubscription;
use Closure;
use Illuminate\Http\Request;

class EnsureCoachSubscriptionIsActive
{
    public function handle($request, \Closure $next)
{
    $user = $request->user();

    // Solo aplica a coach
    if (!$user || !$user->hasRole('coach')) {
        return $next($request);
    }

    $sub = \App\Models\CoachSubscription::where('coach_id', $user->id)
        ->orderByDesc('ends_at')
        ->first();

    // Sin suscripción → bloquear
    if (!$sub) {
        return redirect()->route('coach.blocked');
    }

    $today = now()->startOfDay();

    // ✅ PAGADO → siempre pasa
    if ($sub->billing_status === 'paid') {
        return $next($request);
    }

    // ✅ UNPAID PERO EN GRACIA → pasa
    if (
        $sub->billing_status === 'unpaid'
        && $sub->grace_until
        && $today->lte($sub->grace_until->startOfDay())
    ) {
        return $next($request);
    }

    // ❌ Todo lo demás → bloqueado
    return redirect()->route('coach.blocked');
}

}
