<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $coachId = $request->user()->id;
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $perPage = min((int) $request->query('per_page', 25), 50);

        $memberships = ClientMembership::query()
            ->where('coach_id', $coachId)
            ->with([
                'client:id,first_name,last_name,email,phone,is_active',
                'coachClientPlan:id,name,currency,payment_provider',
            ])
            ->when($status !== '', function ($query) use ($status) {
                $query->where(function ($sub) use ($status) {
                    $sub->where('status', $status)
                        ->orWhere('billing_status', $status);
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('plan_name_snapshot', 'like', "%{$q}%")
                        ->orWhereHas('client', function ($clientQuery) use ($q) {
                            $clientQuery->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByRaw('CASE WHEN billing_status = ? THEN 0 ELSE 1 END', ['unpaid'])
            ->orderBy('ends_at')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $memberships->through(fn (ClientMembership $membership) => $this->payload($membership)),
        ]);
    }

    private function payload(ClientMembership $membership): array
    {
        $today = now()->startOfDay();
        $endsAt = $membership->ends_at?->copy()->startOfDay();
        $graceUntil = $membership->grace_until?->copy()->startOfDay();
        $isInGrace = $membership->billing_status === 'unpaid'
            && $graceUntil
            && $today->lte($graceUntil);

        return [
            'id' => $membership->id,
            'client' => $membership->client ? [
                'id' => $membership->client->id,
                'full_name' => $membership->client->full_name,
                'email' => $membership->client->email,
                'phone' => $membership->client->phone,
                'is_active' => (bool) $membership->client->is_active,
            ] : null,
            'plan' => [
                'id' => $membership->coach_client_plan_id,
                'name' => $membership->plan_name_snapshot,
                'price' => (float) $membership->price_snapshot,
                'currency' => strtoupper($membership->coachClientPlan?->currency ?: 'mxn'),
                'billing_cycle_days' => (int) $membership->billing_cycle_days_snapshot,
                'payment_provider' => $membership->coachClientPlan?->payment_provider
                    ?: ($membership->stripe_subscription_id ? 'stripe' : 'manual'),
            ],
            'status' => $membership->status,
            'billing_status' => $membership->billing_status,
            'status_label' => $this->statusLabel($membership, $isInGrace, $endsAt),
            'starts_at' => optional($membership->starts_at)->toDateString(),
            'ends_at' => optional($membership->ends_at)->toDateString(),
            'next_renewal_at' => optional($membership->next_renewal_at)->toDateString(),
            'grace_until' => optional($membership->grace_until)->toDateString(),
            'paid_at' => optional($membership->paid_at)->toDateString(),
            'days_until_end' => $endsAt ? $today->diffInDays($endsAt, false) : null,
            'is_expired' => $endsAt ? $today->gt($endsAt) : false,
            'is_in_grace' => (bool) $isInGrace,
            'is_stripe' => (bool) $membership->stripe_subscription_id,
            'stripe_status' => $membership->stripe_status,
        ];
    }

    private function statusLabel(ClientMembership $membership, bool $isInGrace, ?Carbon $endsAt): string
    {
        if ($membership->status === 'cancelled') {
            return 'Cancelada';
        }

        if ($endsAt && now()->startOfDay()->gt($endsAt)) {
            return 'Vencida';
        }

        if ($membership->billing_status === 'paid') {
            return 'Pagada';
        }

        if ($isInGrace) {
            return 'Gracia';
        }

        return 'Pendiente';
    }
}
