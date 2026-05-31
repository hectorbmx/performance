<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Stripe;

class BillingController extends Controller
{
    public function clientCheckout(Request $request, StripeConnectService $connect)
    {
        $data = $request->validate([
            'client_membership_id' => ['nullable','integer','exists:client_memberships,id'],
        ]);

        $userApp = $request->user();

        $membership = ClientMembership::query()
            ->where('client_id', $userApp->client_id)
            ->where('billing_status', '!=', 'paid')
            ->when($data['client_membership_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->latest('starts_at')
            ->firstOrFail();

        $session = $connect->createMembershipCheckout($membership);

        return response()->json([
            'ok' => true,
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ]);
    }

    public function coachCheckout(Request $request, StripeBillingService $billing)
    {
        $data = $request->validate([
            'membership_plan_id' => ['required','integer','exists:membership_plans,id'],
        ]);

        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        abort_if(($plan->payment_provider ?? 'stripe') === 'manual', 422, 'Este plan se cobra manualmente.');
        abort_if(!$plan->stripe_price_id, 422, 'Plan no tiene stripe_price_id');

        $user = $request->user();
        $customerId = $billing->getOrCreateCustomer($user);

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = CheckoutSession::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'success_url' => config('app.url') . '/billing/coach/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => config('app.url') . '/billing/coach/cancel',
            'metadata' => [
                'context' => 'coach',
                'user_id' => (string) $user->id,
                'membership_plan_id' => (string) $plan->id,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ]);
    }
}
