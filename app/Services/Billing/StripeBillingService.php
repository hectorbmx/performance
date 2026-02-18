<?php

namespace App\Services\Billing;

use App\Models\User;
use Stripe\Stripe;
use Stripe\Customer;

class StripeBillingService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Obtiene o crea el Stripe Customer
     */
    public function getOrCreateCustomer(User $user): string
    {
        // Si ya existe en DB, lo regresamos
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        // Crear customer en Stripe
        $customer = Customer::create([
            'email' => $user->email,
            'name'  => $user->name ?? null,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        // Guardar en DB
        $user->stripe_customer_id = $customer->id;
        $user->save();

        return $customer->id;
    }
}
