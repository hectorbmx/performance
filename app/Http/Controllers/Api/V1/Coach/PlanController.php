<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachClientPlan;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = CoachClientPlan::query()
            ->where('coach_id', $request->user()->id)
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 50));

        return response()->json([
            'ok' => true,
            'data' => $plans->through(fn (CoachClientPlan $plan) => $this->payload($plan)),
        ]);
    }

    public function store(Request $request, StripeConnectService $connect)
    {
        $data = $this->validatedData($request);

        $plan = CoachClientPlan::create([
            ...$data,
            'coach_id' => $request->user()->id,
            'currency' => strtolower($data['currency'] ?? 'mxn'),
            'payment_provider' => $data['payment_provider'] ?? 'manual',
            'reminder_days_before' => $data['reminder_days_before'] ?? 5,
            'grace_days' => $data['grace_days'] ?? 0,
        ]);

        if ($plan->payment_provider === 'stripe' && $request->user()->coachProfile?->stripe_charges_enabled) {
            $connect->ensurePlanPrice($plan);
            $plan->refresh();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Plan creado correctamente.',
            'data' => $this->payload($plan),
        ], 201);
    }

    public function show(Request $request, CoachClientPlan $plan)
    {
        $this->authorizePlan($request, $plan);

        return response()->json([
            'ok' => true,
            'data' => $this->payload($plan),
        ]);
    }

    public function update(Request $request, CoachClientPlan $plan, StripeConnectService $connect)
    {
        $this->authorizePlan($request, $plan);

        $data = $this->validatedData($request);

        $priceChanged = (float) $plan->price !== (float) $data['price']
            || (int) $plan->billing_cycle_days !== (int) $data['billing_cycle_days']
            || strtolower($plan->currency ?: 'mxn') !== strtolower($data['currency'] ?? 'mxn');

        $payload = [
            ...$data,
            'currency' => strtolower($data['currency'] ?? 'mxn'),
            'payment_provider' => $data['payment_provider'] ?? 'manual',
            'reminder_days_before' => $data['reminder_days_before'] ?? 5,
            'grace_days' => $data['grace_days'] ?? 0,
        ];

        if ($priceChanged || $payload['payment_provider'] === 'manual') {
            $payload['stripe_price_id'] = null;
        }

        if ($payload['payment_provider'] === 'manual') {
            $payload['stripe_product_id'] = null;
        }

        $plan->update($payload);

        if ($plan->payment_provider === 'stripe' && $request->user()->coachProfile?->stripe_charges_enabled) {
            $connect->ensurePlanPrice($plan->fresh());
        }

        return response()->json([
            'ok' => true,
            'message' => 'Plan actualizado correctamente.',
            'data' => $this->payload($plan->fresh()),
        ]);
    }

    public function destroy(Request $request, CoachClientPlan $plan)
    {
        $this->authorizePlan($request, $plan);

        $plan->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Plan eliminado correctamente.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_provider' => ['required', Rule::in(['manual', 'stripe'])],
            'billing_cycle_days' => ['required', 'integer', 'min:1'],
            'reminder_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizePlan(Request $request, CoachClientPlan $plan): void
    {
        abort_unless((int) $plan->coach_id === (int) $request->user()->id, 403);
    }

    private function payload(CoachClientPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => (float) $plan->price,
            'currency' => strtoupper($plan->currency ?: 'mxn'),
            'payment_provider' => $plan->payment_provider ?? 'manual',
            'billing_cycle_days' => (int) $plan->billing_cycle_days,
            'reminder_days_before' => (int) ($plan->reminder_days_before ?? 5),
            'grace_days' => (int) ($plan->grace_days ?? 0),
            'status' => $plan->status,
            'stripe_product_id' => $plan->stripe_product_id,
            'stripe_price_id' => $plan->stripe_price_id,
            'created_at' => optional($plan->created_at)->toISOString(),
        ];
    }
}
