<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class BlockCoachIfGraceExpired
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (!$user || !$user->hasRole('coach')) {
            return;
        }

        $sub = CoachSubscription::where('coach_id', $user->id)
            ->orderByDesc('ends_at')
            ->first();

        // Si no hay suscripción => bloquear
        if (!$sub) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            session()->flash('status', 'Acceso bloqueado: no tienes una suscripción asignada. Contacta al administrador.');
            return;
        }

        // Pagado => ok
        if ($sub->billing_status === 'paid') {
            return;
        }

        // Unpaid pero en gracia => ok
        $today = now()->startOfDay();
        if ($sub->billing_status === 'unpaid' && $sub->grace_until && $today->lte($sub->grace_until->startOfDay())) {
            return;
        }

        // Bloqueado (gracia vencida)
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        session()->flash('status', 'Acceso bloqueado: tu suscripción está pendiente de pago y la gracia ya venció.');
    }
}
