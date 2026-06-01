<?php

namespace App\Http\Middleware;

use App\Models\CoachSubscription;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureCoachApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user instanceof User || !$user->hasRole('coach')) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado para el area coach.',
            ], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => false,
                'message' => 'Debes verificar tu correo antes de usar el area coach.',
                'code' => 'email_not_verified',
            ], 403);
        }

        $subscription = CoachSubscription::query()
            ->where('coach_id', $user->id)
            ->orderByDesc('ends_at')
            ->first();

        if (!$subscription) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay una suscripcion activa para este coach.',
                'code' => 'subscription_missing',
            ], 402);
        }

        $today = now()->startOfDay();
        $isPaid = $subscription->billing_status === 'paid';
        $isInGrace = $subscription->billing_status === 'unpaid'
            && $subscription->grace_until
            && $today->lte($subscription->grace_until->copy()->startOfDay());

        if (!$isPaid && !$isInGrace) {
            return response()->json([
                'ok' => false,
                'message' => 'La suscripcion del coach no permite acceso.',
                'code' => 'subscription_blocked',
            ], 402);
        }

        return $next($request);
    }
}
