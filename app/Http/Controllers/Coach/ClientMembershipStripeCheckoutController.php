<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientMembershipStripeCheckoutController extends Controller
{
    public function store(Request $request, ClientMembership $membership, StripeConnectService $connect): RedirectResponse
    {
        abort_unless((int) $membership->coach_id === (int) auth()->id(), 403);

        if ($membership->billing_status === 'paid') {
            return back()->with('error', 'Esta membresía ya está pagada.');
        }

        if (($membership->coachClientPlan?->payment_provider ?? 'manual') !== 'stripe') {
            return back()->with('error', 'Este plan se cobra de forma manual.');
        }

        try {
            $session = $connect->createMembershipCheckout($membership);
        } catch (\Throwable $e) {
            return back()->withErrors(['stripe' => $e->getMessage()]);
        }

        if ($request->boolean('return_link')) {
            return back()
                ->with('success', 'Link de pago generado correctamente.')
                ->with('stripe_payment_link', $session->url)
                ->with('stripe_payment_link_membership_id', $membership->id);
        }

        return redirect()->away($session->url);
    }
}
