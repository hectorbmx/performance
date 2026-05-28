<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\RedirectResponse;

class StripeConnectController extends Controller
{
    public function start(StripeConnectService $connect): RedirectResponse
    {
        $profile = $connect->ensureConnectedAccount(auth()->user());

        $url = $connect->createOnboardingLink(
            $profile,
            route('coach.stripe-connect.refresh'),
            route('coach.stripe-connect.return')
        );

        return redirect()->away($url);
    }

    public function refresh(StripeConnectService $connect): RedirectResponse
    {
        return $this->start($connect);
    }

    public function return(StripeConnectService $connect): RedirectResponse
    {
        $profile = auth()->user()->coachProfile;

        if ($profile) {
            $profile = $connect->syncAccountStatus($profile);
        }

        return redirect()
            ->route('coach.membresias.index')
            ->with(
                $profile?->stripe_charges_enabled ? 'success' : 'error',
                $profile?->stripe_charges_enabled
                    ? 'Stripe Connect quedó listo para cobrar membresías.'
                    : 'Stripe Connect aún requiere información para activar cobros.'
            );
    }
}
