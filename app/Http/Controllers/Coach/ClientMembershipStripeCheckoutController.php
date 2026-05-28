<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\RedirectResponse;

class ClientMembershipStripeCheckoutController extends Controller
{
    public function store(ClientMembership $membership, StripeConnectService $connect): RedirectResponse
    {
        abort_unless((int) $membership->coach_id === (int) auth()->id(), 403);

        if ($membership->billing_status === 'paid') {
            return back()->with('error', 'Esta membresía ya está pagada.');
        }

        try {
            $session = $connect->createMembershipCheckout($membership);
        } catch (\Throwable $e) {
            return back()->withErrors(['stripe' => $e->getMessage()]);
        }

        return redirect()->away($session->url);
    }
}
