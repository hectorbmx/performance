<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Services\Billing\StripeConnectService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeConnectService $connect)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.connect_webhook_secret')
            ?: config('services.stripe.webhook_secret');

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $signature, $secret)
                : json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response('Invalid Stripe webhook signature.', 400);
        }

        $connectedAccountId = $event->account ?? null;

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object, $connectedAccountId, $connect),
            'customer.subscription.updated' => $this->handleSubscriptionChanged($event->data->object, $connectedAccountId),
            'customer.subscription.deleted' => $this->handleSubscriptionChanged($event->data->object, $connectedAccountId, true),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    private function handleCheckoutCompleted(object $session, ?string $connectedAccountId, StripeConnectService $connect): void
    {
        $membershipId = $session->metadata->client_membership_id ?? null;
        if (!$membershipId || !$connectedAccountId || empty($session->subscription)) {
            return;
        }

        $subscription = $connect->retrieveSubscription($session->subscription, $connectedAccountId);

        $this->updateMembershipFromSubscription(
            ClientMembership::find($membershipId),
            $subscription,
            $connectedAccountId,
            $session->id
        );
    }

    private function handleSubscriptionChanged(
        object $subscription,
        ?string $connectedAccountId,
        bool $deleted = false
    ): void
    {
        $membershipId = $subscription->metadata->client_membership_id ?? null;
        if (!$membershipId || !$connectedAccountId) {
            return;
        }

        $this->updateMembershipFromSubscription(
            ClientMembership::find($membershipId),
            $subscription,
            $connectedAccountId,
            null,
            $deleted
        );
    }

    private function updateMembershipFromSubscription(
        ?ClientMembership $membership,
        object $subscription,
        string $connectedAccountId,
        ?string $checkoutSessionId = null,
        bool $deleted = false
    ): void {
        if (!$membership) {
            return;
        }

        $stripeStatus = $deleted ? 'canceled' : (string) ($subscription->status ?? '');
        $isPaidLike = in_array($stripeStatus, ['active', 'trialing'], true);
        $isCanceled = $stripeStatus === 'canceled';
        $isPendingPayment = in_array($stripeStatus, ['incomplete', 'incomplete_expired'], true);
        $isPastDue = in_array($stripeStatus, ['past_due', 'unpaid', 'paused'], true);
        $periodEnd = isset($subscription->current_period_end)
            ? Carbon::createFromTimestamp($subscription->current_period_end)
            : null;

        if ($isPaidLike) {
            $membershipStatus = 'active';
            $billingStatus = 'paid';
        } elseif ($isCanceled) {
            $membershipStatus = 'cancelled';
            $billingStatus = 'canceled';
        } elseif ($isPendingPayment && $stripeStatus === 'incomplete') {
            $membershipStatus = $membership->status === ClientMembership::STATUS_PENDING_PAYMENT
                ? ClientMembership::STATUS_PENDING_PAYMENT
                : 'past_due';
            $billingStatus = 'unpaid';
        } elseif ($isPastDue) {
            $membershipStatus = 'past_due';
            $billingStatus = 'past_due';
        } elseif ($stripeStatus === 'incomplete_expired') {
            $membershipStatus = 'expired';
            $billingStatus = 'unpaid';
        } else {
            // No activar una membresía por un estado Stripe desconocido.
            $membershipStatus = $membership->status;
            $billingStatus = $membership->billing_status;
        }

        $membership->update([
            'billing_status' => $billingStatus,
            'status' => $membershipStatus,
            'paid_at' => $isPaidLike ? ($membership->paid_at ?? now()->toDateString()) : $membership->paid_at,
            'ends_at' => $periodEnd?->toDateString() ?? $membership->ends_at,
            'next_renewal_at' => $periodEnd?->toDateString() ?? $membership->next_renewal_at,
            'stripe_connected_account_id' => $connectedAccountId,
            'stripe_checkout_session_id' => $checkoutSessionId ?? $membership->stripe_checkout_session_id,
            'stripe_subscription_id' => $subscription->id,
            'stripe_status' => $stripeStatus,
            'stripe_current_period_end' => $periodEnd,
        ]);
    }
}
