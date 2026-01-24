<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachClientPlan;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        // Validación temporal, luego crearemos el FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle_days' => 'required|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['coach_id'] = auth()->id();

        CoachClientPlan::create($validated);

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

    public function update(Request $request, CoachClientPlan $membresia)
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
            'billing_cycle_days' => 'required|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $membresia->update($validated);

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