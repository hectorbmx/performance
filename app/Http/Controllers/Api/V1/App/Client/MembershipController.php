<?php

namespace App\Http\Controllers\Api\V1\App\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Models\CoachClientPlan;
use App\Services\Billing\StripeConnectService;
use App\Services\ClientMembershipAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MembershipController extends Controller
{
    public function index(Request $request)
    {
        $userApp = $request->user();
        $client = $userApp->client;
        abort_if(!$client, 422, 'Cliente no asociado.');

        $today = Carbon::today();
        $access = app(ClientMembershipAccessService::class)->forUserApp($userApp);

        $memberships = ClientMembership::query()
            ->with(['coachClientPlan:id,name,description,price,currency,billing_cycle_days,reminder_days_before,grace_days,status'])
            ->where('client_id', $client->id)
            ->where('coach_id', $client->coach_id)
            ->orderByDesc('starts_at')
            ->get();

        $current = $memberships->first(function (ClientMembership $membership) use ($today) {
            return $membership->status === 'active'
                && Carbon::parse($membership->starts_at)->startOfDay()->lte($today)
                && (!$membership->ends_at || Carbon::parse($membership->ends_at)->startOfDay()->gte($today));
        });

        $future = $memberships->first(function (ClientMembership $membership) use ($today) {
            return $membership->status === 'active'
                && Carbon::parse($membership->starts_at)->startOfDay()->gt($today);
        });

        $pending = $memberships->firstWhere('status', ClientMembership::STATUS_PENDING_PAYMENT);
        $expired = $access->state === ClientMembershipAccessService::STATE_EXPIRED
            ? $access->membership
            : null;

        $plans = CoachClientPlan::query()
            ->with('coach.coachProfile:id,user_id,stripe_charges_enabled')
            ->where('coach_id', $client->coach_id)
            ->active()
            ->orderBy('price')
            ->get(['id', 'coach_id', 'name', 'description', 'price', 'currency', 'payment_provider', 'billing_cycle_days', 'reminder_days_before', 'grace_days', 'stripe_price_id']);

        return response()->json([
            'ok' => true,
            'access_state' => $access->state,
            'can_access_service' => $access->canAccessService,
            'access_message' => $access->message,
            'current_membership' => $current ? $this->membershipPayload($current) : null,
            'future_membership' => $future ? $this->membershipPayload($future) : null,
            'pending_membership' => $pending ? $this->membershipPayload($pending) : null,
            'expired_membership' => $expired ? $this->membershipPayload($expired) : null,
            'memberships' => $memberships->map(fn ($membership) => $this->membershipPayload($membership))->values(),
            'available_plans' => $plans->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'currency' => strtoupper($plan->currency ?: 'MXN'),
                'billing_cycle_days' => (int) $plan->billing_cycle_days,
                'reminder_days_before' => (int) $plan->reminder_days_before,
                'grace_days' => (int) $plan->grace_days,
                'payment_provider' => $plan->payment_provider ?: 'manual',
                'can_checkout' => ($plan->payment_provider ?: 'manual') === 'stripe'
                    && (bool) $plan->coach?->coachProfile?->stripe_charges_enabled,
            ])->values(),
        ]);
    }

    public function storeRenewal(Request $request, StripeConnectService $connect)
    {
        $data = $request->validate([
            'coach_client_plan_id' => ['required', 'integer', 'exists:coach_client_plans,id'],
            'checkout' => ['sometimes', 'boolean'],
        ]);

        $userApp = $request->user();
        $client = $userApp->client;
        abort_if(!$client, 422, 'Cliente no asociado.');

        $plan = CoachClientPlan::query()
            ->where('coach_id', $client->coach_id)
            ->active()
            ->findOrFail($data['coach_client_plan_id']);

        abort_if(($plan->payment_provider ?? 'manual') !== 'stripe', 422, 'Este plan no está disponible para pago con Stripe.');

        $membership = DB::transaction(function () use ($client, $plan) {
            $memberships = ClientMembership::query()
                ->where('client_id', $client->id)
                ->where('coach_id', $client->coach_id)
                ->lockForUpdate()
                ->orderByDesc('ends_at')
                ->orderByDesc('starts_at')
                ->get();

            $today = Carbon::today();
            $hasCurrent = $memberships->contains(function (ClientMembership $membership) use ($today) {
                return $membership->status === 'active'
                    && Carbon::parse($membership->starts_at)->startOfDay()->lte($today)
                    && (!$membership->ends_at || Carbon::parse($membership->ends_at)->startOfDay()->gte($today));
            });

            abort_if($hasCurrent, 422, 'Tu membresía actual sigue activa.');

            $hasFuture = $memberships->contains(function (ClientMembership $membership) use ($today) {
                return $membership->status === 'active'
                    && Carbon::parse($membership->starts_at)->startOfDay()->gt($today);
            });

            abort_if($hasFuture, 422, 'Ya tienes una membresía futura contratada.');

            $pending = $memberships->firstWhere('status', ClientMembership::STATUS_PENDING_PAYMENT);
            if ($pending) {
                abort_if(
                    (int) $pending->coach_client_plan_id !== (int) $plan->id,
                    422,
                    'Ya tienes otra renovación pendiente de pago.'
                );

                return $pending;
            }

            $startsAt = $today;
            $endsAt = $startsAt->copy()->addDays((int) $plan->billing_cycle_days);
            $graceDays = (int) ($plan->grace_days ?? 0);

            return ClientMembership::create([
                'coach_id' => $client->coach_id,
                'client_id' => $client->id,
                'coach_client_plan_id' => $plan->id,
                'plan_name_snapshot' => $plan->name,
                'price_snapshot' => $plan->price,
                'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'next_renewal_at' => $endsAt->toDateString(),
                'reminder_days_before' => $plan->reminder_days_before,
                'status' => ClientMembership::STATUS_PENDING_PAYMENT,
                'billing_status' => 'unpaid',
                'grace_until' => $graceDays > 0 ? $startsAt->copy()->addDays($graceDays)->toDateString() : null,
            ]);
        });

        $checkoutUrl = null;
        $sessionId = null;

        if ($request->boolean('checkout', true)) {
            $session = $connect->createMembershipCheckout($membership);
            $checkoutUrl = $session->url;
            $sessionId = $session->id;
        }

        return response()->json([
            'ok' => true,
            'membership' => $this->membershipPayload($membership->fresh(['coachClientPlan'])),
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
        ], 201);
    }

    public function storeFuture(Request $request, StripeConnectService $connect)
    {
        $data = $request->validate([
            'coach_client_plan_id' => ['required', 'integer', 'exists:coach_client_plans,id'],
            'checkout' => ['sometimes', 'boolean'],
        ]);

        $userApp = $request->user();
        $client = $userApp->client;
        abort_if(!$client, 422, 'Cliente no asociado.');

        $plan = CoachClientPlan::query()
            ->where('coach_id', $client->coach_id)
            ->active()
            ->findOrFail($data['coach_client_plan_id']);

        $membership = DB::transaction(function () use ($client, $plan) {
            $memberships = ClientMembership::query()
                ->where('client_id', $client->id)
                ->where('coach_id', $client->coach_id)
                ->lockForUpdate()
                ->orderByDesc('starts_at')
                ->get();

            $pending = $memberships->firstWhere('status', ClientMembership::STATUS_PENDING_PAYMENT);
            if ($pending) {
                abort_if(
                    (int) $pending->coach_client_plan_id !== (int) $plan->id,
                    422,
                    'Ya tienes otra compra pendiente de pago.'
                );

                return $pending;
            }

            $today = Carbon::today();
            $hasFuture = $memberships->contains(function (ClientMembership $membership) use ($today) {
                return $membership->status === 'active'
                    && Carbon::parse($membership->starts_at)->startOfDay()->gt($today);
            });

            abort_if($hasFuture, 422, 'Ya tienes una membresía futura contratada.');

            $lastMembership = $memberships->first();

            $startsAt = $lastMembership?->ends_at
                ? Carbon::parse($lastMembership->ends_at)->addDay()
                : $today;

            $endsAt = $startsAt->copy()->addDays((int) $plan->billing_cycle_days);
            $graceDays = (int) ($plan->grace_days ?? 0);

            return ClientMembership::create([
                'coach_id' => $client->coach_id,
                'client_id' => $client->id,
                'coach_client_plan_id' => $plan->id,
                'plan_name_snapshot' => $plan->name,
                'price_snapshot' => $plan->price,
                'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'next_renewal_at' => $endsAt->toDateString(),
                'reminder_days_before' => $plan->reminder_days_before,
                'status' => ClientMembership::STATUS_PENDING_PAYMENT,
                'billing_status' => 'unpaid',
                'grace_until' => $graceDays > 0 ? $startsAt->copy()->addDays($graceDays)->toDateString() : null,
            ]);
        });

        $checkoutUrl = null;
        $sessionId = null;

        if ($request->boolean('checkout', true)) {
            $session = $connect->createMembershipCheckout($membership);
            $checkoutUrl = $session->url;
            $sessionId = $session->id;
        }

        return response()->json([
            'ok' => true,
            'membership' => $this->membershipPayload($membership->fresh(['coachClientPlan'])),
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
        ], 201);
    }

    private function membershipPayload(ClientMembership $membership): array
    {
        return [
            'id' => $membership->id,
            'plan_id' => $membership->coach_client_plan_id,
            'plan_name' => $membership->plan_name_snapshot ?: $membership->coachClientPlan?->name,
            'price' => (float) $membership->price_snapshot,
            'currency' => strtoupper($membership->coachClientPlan?->currency ?: 'MXN'),
            'billing_cycle_days' => (int) $membership->billing_cycle_days_snapshot,
            'status' => $membership->status,
            'billing_status' => $membership->billing_status,
            'starts_at' => optional($membership->starts_at)->toDateString(),
            'ends_at' => optional($membership->ends_at)->toDateString(),
            'next_renewal_at' => optional($membership->next_renewal_at)->toDateString(),
            'grace_until' => optional($membership->grace_until)->toDateString(),
            'paid_at' => optional($membership->paid_at)->toDateString(),
            'is_stripe' => (bool) $membership->stripe_subscription_id,
        ];
    }
}
