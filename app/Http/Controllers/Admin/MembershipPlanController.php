<?php

namespace App\Http\Controllers\Admin;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;

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
    $validated = $request->validate([
        'name' => ['required','string','max:255'],
        'description' => ['nullable','string'],
        'amount' => ['required','numeric','min:0.01'],
        'currency' => ['required','string','size:3'],
        'billing_cycle_days' => ['required','integer','min:1','max:365'],
        'client_limit' => ['nullable','integer','min:1','max:1000000'],
        'is_active' => ['nullable','boolean'],
    ]);

    $validated['is_active'] = (bool) $request->boolean('is_active');
    $validated['currency'] = strtolower($validated['currency']);

    // Derivamos intervalo desde billing_cycle_days (por ahora)
    $interval = ((int)$validated['billing_cycle_days'] >= 365) ? 'year' : 'month';

    Stripe::setApiKey(config('services.stripe.secret'));

    DB::beginTransaction();

    try {
        // 1) Crear plan en DB (sin stripe ids aún)
        $plan = \App\Models\MembershipPlan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'billing_cycle_days' => $validated['billing_cycle_days'],
            'client_limit' => $validated['client_limit'] ?? null,
            'is_active' => $validated['is_active'],
            'stripe_product_id' => null,
            'stripe_price_id' => null,
        ]);

        // 2) Crear Product en Stripe
        $product = Product::create([
            'name' => $plan->name,
            'description' => $plan->description ?: null,
            'metadata' => [
                'membership_plan_id' => (string) $plan->id,
                'applies_to' => 'coach',
            ],
        ]);

        // 3) Crear Price recurrente en Stripe
        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => (int) round($plan->amount * 100), // centavos
            'currency' => $plan->currency,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => 1,
            ],
            'metadata' => [
                'membership_plan_id' => (string) $plan->id,
                'applies_to' => 'coach',
            ],
        ]);

        // 4) Guardar ids en DB
        $plan->update([
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);

        DB::commit();

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan creado y publicado en Stripe correctamente.');
    } catch (\Throwable $e) {
        DB::rollBack();

        return back()
            ->withInput()
            ->withErrors(['stripe' => 'Error creando el plan en Stripe: ' . $e->getMessage()]);
    }
}


    public function edit(MembershipPlan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

   public function update(Request $request, MembershipPlan $plan)
{
    $validated = $request->validate([
        'name' => ['required','string','max:255'],
        'description' => ['nullable','string'],
        'amount' => ['required','numeric','min:0.01'],
        'currency' => ['required','string','size:3'],
        'billing_cycle_days' => ['required','integer','min:1','max:365'],
        'client_limit' => ['nullable','integer','min:1','max:1000000'],
        'is_active' => ['nullable','boolean'],
    ]);

    $validated['is_active'] = (bool) $request->boolean('is_active');
    $validated['currency'] = strtolower($validated['currency']);

    $interval = ((int)$validated['billing_cycle_days'] >= 365) ? 'year' : 'month';

    $priceChanged =
        (float)$plan->amount !== (float)$validated['amount']
        || (string)$plan->currency !== (string)$validated['currency']
        || (int)$plan->billing_cycle_days !== (int)$validated['billing_cycle_days'];

    Stripe::setApiKey(config('services.stripe.secret'));

    DB::beginTransaction();

    try {
        // 1) Actualiza datos locales del plan (sin tocar ids)
        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'billing_cycle_days' => $validated['billing_cycle_days'],
            'client_limit' => $validated['client_limit'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        // 2) Actualiza Product en Stripe (nombre/desc)
        if ($plan->stripe_product_id) {
            Product::update($plan->stripe_product_id, [
                'name' => $plan->name,
                'description' => $plan->description ?: null,
            ]);
        }

        // 3) Si cambió precio/ciclo/moneda -> crear nuevo Price y actualizar stripe_price_id
        if ($priceChanged) {
            abort_if(!$plan->stripe_product_id, 422, 'El plan no tiene stripe_product_id');

            $newPrice = Price::create([
                'product' => $plan->stripe_product_id,
                'unit_amount' => (int) round(((float)$plan->amount) * 100),
                'currency' => $plan->currency,
                'recurring' => [
                    'interval' => $interval,
                    'interval_count' => 1,
                ],
                'metadata' => [
                    'membership_plan_id' => (string) $plan->id,
                    'applies_to' => 'coach',
                ],
            ]);

            $plan->update([
                'stripe_price_id' => $newPrice->id,
            ]);
        }

        DB::commit();

        return redirect()->route('admin.plans.index')
            ->with('success', $priceChanged
                ? 'Plan actualizado. Se creó un nuevo Price en Stripe.'
                : 'Plan actualizado correctamente.');
    } catch (\Throwable $e) {
        DB::rollBack();

        return back()
            ->withInput()
            ->withErrors(['stripe' => 'Error actualizando en Stripe: '.$e->getMessage()]);
    }
}
public function destroy(MembershipPlan $plan)
{
    // (Opcional pero recomendado) Si ya hay suscripciones activas, solo retiramos (igual aplica)
    // Si quieres bloquear, lo activamos después.

    Stripe::setApiKey(config('services.stripe.secret'));

    DB::beginTransaction();

    try {
        // 1) Desactivar el Price en Stripe para que NO se pueda contratar de nuevo
        if ($plan->stripe_price_id) {
            Price::update($plan->stripe_price_id, ['active' => false]);
        }

        // 2) Retirar del catálogo local
        $plan->is_active = false;
        $plan->save();

        // 3) Soft delete
        $plan->delete();

        DB::commit();

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan retirado: desactivado en Stripe y eliminado (soft delete).');
    } catch (\Throwable $e) {
        DB::rollBack();

        return back()->withErrors([
            'stripe' => 'Error al retirar plan en Stripe: ' . $e->getMessage()
        ]);
    }
}
    public function toggleActive(MembershipPlan $plan)
    {
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        return back()->with('success', 'Estatus del plan actualizado.');
    }
}
