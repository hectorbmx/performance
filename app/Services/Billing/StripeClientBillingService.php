<?php

namespace App\Services\Billing;

use App\Models\UserApp;
use Stripe\Stripe;
use Stripe\Customer;

class StripeClientBillingService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function getOrCreateCustomer(UserApp $userApp, ?string $connectedAccountId = null): string
    {
        if (
            $userApp->stripe_customer_id
            && (!$connectedAccountId || $userApp->stripe_customer_account_id === $connectedAccountId)
        ) {
            return $userApp->stripe_customer_id;
        }

        $params = [
            'email' => $userApp->email ?? null,
            'name'  => $userApp->client?->full_name ?: null,
            'metadata' => [
                'user_app_id' => $userApp->id,
                'coach_id' => (string) ($userApp->client?->coach_id ?? ''),
                'client_id' => (string) ($userApp->client_id ?? ''),
            ],
        ];

        $customer = $connectedAccountId
            ? Customer::create($params, ['stripe_account' => $connectedAccountId])
            : Customer::create($params);

        $userApp->stripe_customer_id = $customer->id;
        $userApp->stripe_customer_account_id = $connectedAccountId;
        $userApp->save();

        return $customer->id;
    }
}
