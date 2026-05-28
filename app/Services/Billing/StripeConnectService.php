<?php

namespace App\Services\Billing;

use App\Models\ClientMembership;
use App\Models\CoachClientPlan;
use App\Models\CoachProfile;
use App\Models\User;
use Carbon\Carbon;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Subscription;

class StripeConnectService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function ensureConnectedAccount(User $coach): CoachProfile
    {
        $profile = $coach->coachProfile()->firstOrCreate([
            'user_id' => $coach->id,
        ], [
            'display_name' => $coach->name,
            'status' => 'active',
        ]);

        if ($profile->stripe_account_id) {
            return $this->syncAccountStatus($profile);
        }

        $account = Account::create([
            'type' => config('services.stripe.connect_account_type', 'express'),
            'country' => 'MX',
            'email' => $coach->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'coach_id' => (string) $coach->id,
            ],
        ]);

        $profile->update([
            'stripe_account_id' => $account->id,
        ]);

        return $this->syncAccountStatus($profile);
    }

    public function syncAccountStatus(CoachProfile $profile): CoachProfile
    {
        if (!$profile->stripe_account_id) {
            return $profile;
        }

        $account = Account::retrieve($profile->stripe_account_id);

        $profile->update([
            'stripe_charges_enabled' => (bool) $account->charges_enabled,
            'stripe_payouts_enabled' => (bool) $account->payouts_enabled,
            'stripe_details_submitted' => (bool) $account->details_submitted,
            'stripe_onboarding_completed_at' => $account->details_submitted
                ? ($profile->stripe_onboarding_completed_at ?? now())
                : null,
        ]);

        return $profile->fresh();
    }

    public function createOnboardingLink(CoachProfile $profile, string $refreshUrl, string $returnUrl): string
    {
        $link = AccountLink::create([
            'account' => $profile->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return $link->url;
    }

    public function ensurePlanPrice(CoachClientPlan $plan): CoachClientPlan
    {
        $profile = $plan->coach->coachProfile;

        if (!$profile?->stripe_account_id) {
            throw new \RuntimeException('El coach no tiene una cuenta Stripe conectada.');
        }

        if ($plan->stripe_product_id && $plan->stripe_price_id) {
            return $plan;
        }

        $product = Product::create([
            'name' => $plan->name,
            'description' => $plan->description ?: null,
            'metadata' => [
                'coach_id' => (string) $plan->coach_id,
                'coach_client_plan_id' => (string) $plan->id,
            ],
        ], [
            'stripe_account' => $profile->stripe_account_id,
        ]);

        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => (int) round(((float) $plan->price) * 100),
            'currency' => strtolower($plan->currency ?: 'mxn'),
            'recurring' => $this->recurringFromDays((int) $plan->billing_cycle_days),
            'metadata' => [
                'coach_id' => (string) $plan->coach_id,
                'coach_client_plan_id' => (string) $plan->id,
            ],
        ], [
            'stripe_account' => $profile->stripe_account_id,
        ]);

        $plan->update([
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);

        return $plan->fresh();
    }

    public function createMembershipCheckout(ClientMembership $membership): CheckoutSession
    {
        $membership->loadMissing(['coach.coachProfile', 'client.userApp', 'coachClientPlan']);

        $profile = $membership->coach->coachProfile;
        if (!$profile?->stripe_account_id || !$profile->stripe_charges_enabled) {
            throw new \RuntimeException('El coach debe completar Stripe Connect antes de cobrar con Stripe.');
        }

        $plan = $this->ensurePlanPrice($membership->coachClientPlan);
        $userApp = $membership->client->userApp;
        if (!$userApp) {
            throw new \RuntimeException('El cliente no tiene usuario de app para crear el customer de Stripe.');
        }

        $customerId = app(StripeClientBillingService::class)
            ->getOrCreateCustomer($userApp, $profile->stripe_account_id);

        $params = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'success_url' => route('coach.clients.index') . '?stripe=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('coach.client-payments.create', $membership) . '?stripe=cancel',
            'metadata' => [
                'context' => 'client_membership',
                'client_membership_id' => (string) $membership->id,
                'coach_id' => (string) $membership->coach_id,
                'client_id' => (string) $membership->client_id,
                'coach_client_plan_id' => (string) $membership->coach_client_plan_id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'client_membership_id' => (string) $membership->id,
                    'coach_id' => (string) $membership->coach_id,
                    'client_id' => (string) $membership->client_id,
                ],
            ],
        ];

        $feePercent = config('services.stripe.application_fee_percent');
        if ($feePercent !== null && $feePercent !== '') {
            $params['subscription_data']['application_fee_percent'] = (float) $feePercent;
        }

        $startsAt = $membership->starts_at ? Carbon::parse($membership->starts_at)->startOfDay() : null;
        if ($startsAt && $startsAt->isFuture()) {
            $params['subscription_data']['trial_end'] = $startsAt->timestamp;
        }

        $session = CheckoutSession::create($params, [
            'stripe_account' => $profile->stripe_account_id,
        ]);

        $membership->update([
            'stripe_connected_account_id' => $profile->stripe_account_id,
            'stripe_checkout_session_id' => $session->id,
        ]);

        return $session;
    }

    public function retrieveSubscription(string $subscriptionId, string $connectedAccountId): Subscription
    {
        return Subscription::retrieve($subscriptionId, [
            'stripe_account' => $connectedAccountId,
        ]);
    }

    private function recurringFromDays(int $days): array
    {
        if ($days % 365 === 0) {
            return ['interval' => 'year', 'interval_count' => max(1, (int) ($days / 365))];
        }

        if ($days % 30 === 0) {
            return ['interval' => 'month', 'interval_count' => max(1, (int) ($days / 30))];
        }

        if ($days % 7 === 0) {
            return ['interval' => 'week', 'interval_count' => max(1, (int) ($days / 7))];
        }

        return ['interval' => 'day', 'interval_count' => max(1, $days)];
    }
}
