<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;

// Services
use App\Services\Billing\StripeBillingService;        // User (coach)
use App\Services\Billing\StripeClientBillingService;  // UserApp (cliente)

class BillingController extends Controller
{
    /**
     * CLIENTE FINAL (UserApp) paga al coach (Checkout subscription)
     * Auth esperado: UserApp (sanctum en tu app)
     */
    public function clientCheckout(Request $request, StripeClientBillingService $billing)
    {
        $data = $request->validate([
            'membership_plan_id' => ['required','integer','exists:membership_plans,id'],
        ]);

        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        abort_if(!$plan->stripe_price_id, 422, 'Plan no tiene stripe_price_id');

        $userApp = $request->user(); // <- UserApp
        $customerId = $billing->getOrCreateCustomer($userApp);

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = CheckoutSession::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'success_url' => config('app.url') . '/billing/client/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => config('app.url') . '/billing/client/cancel',
            'metadata' => [
                'context' => 'client',
                'user_app_id' => (string) $userApp->id,
                // si tienes coach_id/client_id en UserApp, mete metadata útil:
                'coach_id' => (string) ($userApp->coach_id ?? ''),
                'client_id' => (string) ($userApp->client_id ?? ''),
                'membership_plan_id' => (string) $plan->id,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ]);
    }

    /**
     * COACH (User) paga a la plataforma (Checkout subscription)
     * Auth esperado: User (panel coach)
     */
    public function coachCheckout(Request $request, StripeBillingService $billing)
    {
        $data = $request->validate([
            'membership_plan_id' => ['required','integer','exists:membership_plans,id'],
        ]);

        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        abort_if(!$plan->stripe_price_id, 422, 'Plan no tiene stripe_price_id');

        $user = $request->user(); // <- User
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
