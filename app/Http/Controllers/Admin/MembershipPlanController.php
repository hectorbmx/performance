<?php

namespace App\Http\Controllers\Admin;

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
            'billing_cycle_days' => ['required','integer','min:1','max:365'],
            'client_limit' => ['nullable','integer','min:1','max:1000000'],
            'is_active' => ['nullable','boolean'],
        ]);

        $validated['is_active'] = (bool)($request->boolean('is_active'));

        MembershipPlan::create($validated);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan creado correctamente.');
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
            'billing_cycle_days' => ['required','integer','min:1','max:365'],
            'client_limit' => ['nullable','integer','min:1','max:1000000'],
            'is_active' => ['nullable','boolean'],
        ]);

        $validated['is_active'] = (bool)($request->boolean('is_active'));

        $plan->update($validated);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan actualizado correctamente.');
    }

    public function destroy(MembershipPlan $plan)
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan eliminado (soft delete).');
    }

    public function toggleActive(MembershipPlan $plan)
    {
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        return back()->with('success', 'Estatus del plan actualizado.');
    }
}
