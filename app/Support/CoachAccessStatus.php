<?php

namespace App\Support;

use App\Models\CoachSubscription;
use Illuminate\Support\Carbon;

class CoachAccessStatus
{
    public static function for(?CoachSubscription $subscription, ?Carbon $today = null): array
    {
        $today ??= now()->startOfDay();

        if (!$subscription) {
            return [
                'key' => 'no_subscription',
                'label' => 'Sin suscripcion',
                'badge' => 'bg-gray-100 text-gray-800',
                'can_access' => false,
                'reason' => 'No tiene suscripcion asignada.',
            ];
        }

        if ($subscription->billing_status === 'paid') {
            return [
                'key' => 'paid',
                'label' => 'Acceso activo',
                'badge' => 'bg-green-100 text-green-800',
                'can_access' => true,
                'reason' => 'Suscripcion pagada.',
            ];
        }

        if (
            $subscription->billing_status === 'unpaid'
            && $subscription->grace_until
            && $today->lte($subscription->grace_until->copy()->startOfDay())
        ) {
            return [
                'key' => 'grace',
                'label' => 'En gracia',
                'badge' => 'bg-blue-100 text-blue-800',
                'can_access' => true,
                'reason' => 'Pendiente de pago con gracia vigente hasta ' . $subscription->grace_until->format('Y-m-d') . '.',
            ];
        }

        if ($subscription->billing_status === 'unpaid') {
            return [
                'key' => 'grace_expired',
                'label' => 'Gracia vencida',
                'badge' => 'bg-orange-100 text-orange-800',
                'can_access' => false,
                'reason' => $subscription->grace_until
                    ? 'Pendiente de pago. La gracia vencio el ' . $subscription->grace_until->format('Y-m-d') . '.'
                    : 'Pendiente de pago sin periodo de gracia.',
            ];
        }

        return [
            'key' => 'blocked',
            'label' => 'Acceso bloqueado',
            'badge' => 'bg-red-100 text-red-800',
            'can_access' => false,
            'reason' => 'Estatus de cobro: ' . strtoupper((string) $subscription->billing_status) . '.',
        ];
    }
}
