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

    public function getOrCreateCustomer(UserApp $userApp): string
    {
        if ($userApp->stripe_customer_id) {
            return $userApp->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $userApp->email ?? null,
            'name'  => trim(($userApp->first_name ?? '').' '.($userApp->last_name ?? '')) ?: null,
            'metadata' => [
                'user_app_id' => $userApp->id,
                'coach_id' => (string) ($userApp->coach_id ?? ''),
                'client_id' => (string) ($userApp->client_id ?? ''),
            ],
        ]);

        $userApp->stripe_customer_id = $customer->id;
        $userApp->save();

        return $customer->id;
    }
}
