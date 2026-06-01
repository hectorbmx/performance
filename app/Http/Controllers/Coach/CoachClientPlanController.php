<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachClientPlan;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoachClientPlanController extends Controller
{
    public function index()
    {
        $plans = CoachClientPlan::where('coach_id', auth()->id())
            ->latest()
             ->paginate(10); 
            

        return view('coach.membresias.index', compact('plans'));
    }

    public function create()
    {
        return view('coach.membresias.create');
    }

    public function store(Request $request, StripeConnectService $connect)
    {
        // Validación temporal, luego crearemos el FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_provider' => ['required', Rule::in(['manual', 'stripe'])],
            'billing_cycle_days' => 'required|integer|min:1',
            'reminder_days_before' => 'nullable|integer|min:1|max:365',
            'grace_days' => 'nullable|integer|min:0|max:365',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['coach_id'] = auth()->id();
        $validated['currency'] = strtolower($validated['currency'] ?? 'mxn');
        $validated['payment_provider'] = $validated['payment_provider'] ?? 'manual';
        $validated['reminder_days_before'] = $validated['reminder_days_before'] ?? 5;
        $validated['grace_days'] = $validated['grace_days'] ?? 0;

        $plan = CoachClientPlan::create($validated);

        if ($plan->payment_provider === 'stripe' && auth()->user()->coachProfile?->stripe_charges_enabled) {
            $connect->ensurePlanPrice($plan);
        }

        return redirect()->route('coach.membresias.index')
            ->with('success', 'Plan creado exitosamente.');
    }

    public function edit(CoachClientPlan $membresia)
    {
        // Verificar que el plan pertenezca al coach autenticado
        if ($membresia->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para editar este plan.');
        }

        return view('coach.membresias.edit', compact('membresia'));
    }

    public function update(Request $request, CoachClientPlan $membresia, StripeConnectService $connect)
    {
        // Verificar que el plan pertenezca al coach autenticado
        if ($membresia->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para actualizar este plan.');
        }

        // Validación temporal, luego crearemos el FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'payment_provider' => ['required', Rule::in(['manual', 'stripe'])],
            'billing_cycle_days' => 'required|integer|min:1',
            'reminder_days_before' => 'nullable|integer|min:1|max:365',
            'grace_days' => 'nullable|integer|min:0|max:365',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['currency'] = strtolower($validated['currency'] ?? ($membresia->currency ?: 'mxn'));
        $validated['payment_provider'] = $validated['payment_provider'] ?? 'manual';
        $validated['reminder_days_before'] = $validated['reminder_days_before'] ?? 5;
        $validated['grace_days'] = $validated['grace_days'] ?? 0;
        $priceChanged = (float) $membresia->price !== (float) $validated['price']
            || (int) $membresia->billing_cycle_days !== (int) $validated['billing_cycle_days']
            || strtolower($membresia->currency ?: 'mxn') !== $validated['currency'];

        if ($priceChanged || $validated['payment_provider'] === 'manual') {
            $validated['stripe_price_id'] = null;
        }

        if ($validated['payment_provider'] === 'manual') {
            $validated['stripe_product_id'] = null;
        }

        $membresia->update($validated);

        if ($membresia->payment_provider === 'stripe' && auth()->user()->coachProfile?->stripe_charges_enabled) {
            $connect->ensurePlanPrice($membresia->fresh());
        }

        return redirect()->route('coach.membresias.index')
            ->with('success', 'Plan actualizado exitosamente.');
    }

    public function destroy(CoachClientPlan $membresia)
    {
        // Verificar que el plan pertenezca al coach autenticado
        if ($membresia->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para eliminar este plan.');
        }

        $membresia->delete();

        return redirect()->route('coach.membresias.index')
            ->with('success', 'Plan eliminado exitosamente.');
    }
}
