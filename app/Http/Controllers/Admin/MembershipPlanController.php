<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class MembershipPlanController extends Controller
{
    public function index()
    {
        $plans = MembershipPlan::orderByDesc('id')->paginate(15);

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPlanData($request);

        DB::beginTransaction();

        try {
            $plan = MembershipPlan::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'payment_provider' => $validated['payment_provider'],
                'billing_cycle_days' => $validated['billing_cycle_days'],
                'client_limit' => $validated['client_limit'] ?? null,
                'is_active' => $validated['is_active'],
                'stripe_product_id' => null,
                'stripe_price_id' => null,
            ]);

            if ($plan->payment_provider === 'stripe') {
                $this->publishPlanToStripe($plan);
            }

            DB::commit();

            return redirect()->route('admin.plans.index')
                ->with('success', $plan->payment_provider === 'stripe'
                    ? 'Plan creado y publicado en Stripe correctamente.'
                    : 'Plan manual creado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['stripe' => 'Error creando el plan: ' . $e->getMessage()]);
        }
    }

    public function edit(MembershipPlan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, MembershipPlan $plan)
    {
        $validated = $this->validatedPlanData($request);

        $priceChanged =
            (float) $plan->amount !== (float) $validated['amount']
            || (string) $plan->currency !== (string) $validated['currency']
            || (int) $plan->billing_cycle_days !== (int) $validated['billing_cycle_days'];

        DB::beginTransaction();

        try {
            $wasStripe = $plan->payment_provider === 'stripe';
            $previousStripePriceId = $plan->stripe_price_id;

            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'payment_provider' => $validated['payment_provider'],
                'billing_cycle_days' => $validated['billing_cycle_days'],
                'client_limit' => $validated['client_limit'] ?? null,
                'is_active' => $validated['is_active'],
            ]);

            if ($plan->payment_provider === 'manual') {
                if ($wasStripe && $previousStripePriceId) {
                    $this->deactivateStripePrice($previousStripePriceId);
                }

                $plan->update([
                    'stripe_product_id' => null,
                    'stripe_price_id' => null,
                ]);

                DB::commit();

                return redirect()->route('admin.plans.index')
                    ->with('success', 'Plan actualizado como pago manual.');
            }

            $this->syncPlanWithStripe($plan, $priceChanged);

            DB::commit();

            return redirect()->route('admin.plans.index')
                ->with('success', $priceChanged || !$previousStripePriceId
                    ? 'Plan actualizado. Se creo un nuevo Price en Stripe.'
                    : 'Plan actualizado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['stripe' => 'Error actualizando el plan: ' . $e->getMessage()]);
        }
    }

    public function destroy(MembershipPlan $plan)
    {
        DB::beginTransaction();

        try {
            if ($plan->stripe_price_id) {
                $this->deactivateStripePrice($plan->stripe_price_id);
            }

            $plan->is_active = false;
            $plan->save();
            $plan->delete();

            DB::commit();

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan retirado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'stripe' => 'Error al retirar plan: ' . $e->getMessage(),
            ]);
        }
    }

    public function toggleActive(MembershipPlan $plan)
    {
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        return back()->with('success', 'Estatus del plan actualizado.');
    }

    private function validatedPlanData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_provider' => ['required', Rule::in(['stripe', 'manual'])],
            'billing_cycle_days' => ['required', 'integer', 'min:1', 'max:365'],
            'client_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['currency'] = strtolower($validated['currency']);

        return $validated;
    }

    private function syncPlanWithStripe(MembershipPlan $plan, bool $priceChanged): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if ($plan->stripe_product_id) {
            Product::update($plan->stripe_product_id, [
                'name' => $plan->name,
                'description' => $plan->description ?: null,
            ]);
        } else {
            $product = Product::create($this->stripeProductPayload($plan));

            $plan->update([
                'stripe_product_id' => $product->id,
            ]);
        }

        if ($priceChanged || !$plan->stripe_price_id) {
            $price = Price::create($this->stripePricePayload($plan));

            $plan->update([
                'stripe_price_id' => $price->id,
            ]);
        }
    }

    private function publishPlanToStripe(MembershipPlan $plan): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $product = Product::create($this->stripeProductPayload($plan));
        $plan->update([
            'stripe_product_id' => $product->id,
        ]);

        $price = Price::create($this->stripePricePayload($plan));
        $plan->update([
            'stripe_price_id' => $price->id,
        ]);
    }

    private function deactivateStripePrice(string $stripePriceId): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        Price::update($stripePriceId, ['active' => false]);
    }

    private function stripeProductPayload(MembershipPlan $plan): array
    {
        return [
            'name' => $plan->name,
            'description' => $plan->description ?: null,
            'metadata' => [
                'membership_plan_id' => (string) $plan->id,
                'applies_to' => 'coach',
            ],
        ];
    }

    private function stripePricePayload(MembershipPlan $plan): array
    {
        $interval = ((int) $plan->billing_cycle_days >= 365) ? 'year' : 'month';

        return [
            'product' => $plan->stripe_product_id,
            'unit_amount' => (int) round(((float) $plan->amount) * 100),
            'currency' => $plan->currency,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => 1,
            ],
            'metadata' => [
                'membership_plan_id' => (string) $plan->id,
                'applies_to' => 'coach',
            ],
        ];
    }
}
