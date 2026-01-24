<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\CoachClientPlan;
use Illuminate\Http\Request;

class CoachClientMembershipController extends Controller
{
    public function create(Client $client)
    {
        // Verificar que el cliente pertenezca al coach
        if ($client->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para asignar planes a este cliente.');
        }

        // Obtener planes activos del coach
        $plans = CoachClientPlan::where('coach_id', auth()->id())
            ->where('status', 'active')
            ->get();

        // Obtener membresía activa si existe
        $activeMembership = $client->activeMembership;

        return view('coach.client-memberships.create', compact('client', 'plans', 'activeMembership'));
    }

    public function store(Request $request, Client $client)
    {
        // Verificar que el cliente pertenezca al coach
        if ($client->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para asignar planes a este cliente.');
        }

        $validated = $request->validate([
            'coach_client_plan_id' => 'required|exists:coach_client_plans,id',
            'reminder_days_before' => 'nullable|integer|min:1',
            'register_payment' => 'nullable|boolean',
            'grace_days' => 'required_if:register_payment,false|integer|min:0',
        ]);

        // Obtener el plan seleccionado
        $plan = CoachClientPlan::findOrFail($validated['coach_client_plan_id']);

        // Verificar que el plan pertenezca al coach
        if ($plan->coach_id !== auth()->id()) {
            abort(403, 'Este plan no te pertenece.');
        }

        // Obtener membresía activa si existe
        $activeMembership = $client->activeMembership;

        // Calcular fechas
        $starts_at = now()->startOfDay();
        
        if ($activeMembership) {
            // Si hay membresía activa, empezar después de que termine
            $starts_at = $activeMembership->ends_at->addDay()->startOfDay();
        }

        $ends_at = $starts_at->copy()->addDays($plan->billing_cycle_days);
        $next_renewal_at = $ends_at->copy();

        // Determinar billing_status y grace_until
        $billing_status = $request->boolean('register_payment') ? 'paid' : 'unpaid';
        $paid_at = $request->boolean('register_payment') ? now() : null;
        
        $grace_until = null;
        if (!$request->boolean('register_payment') && $validated['grace_days'] > 0) {
            $grace_until = $starts_at->copy()->addDays($validated['grace_days']);
        }

        // Crear snapshot de la membresía
        ClientMembership::create([
            'coach_id' => auth()->id(),
            'client_id' => $client->id,
            'coach_client_plan_id' => $plan->id,
            'plan_name_snapshot' => $plan->name,
            'price_snapshot' => $plan->price,
            'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'next_renewal_at' => $next_renewal_at,
            'reminder_days_before' => $validated['reminder_days_before'] ?? null,
            'status' => 'active',
            'billing_status' => $billing_status,
            'grace_until' => $grace_until,
            'paid_at' => $paid_at,
        ]);

        return redirect()->route('coach.clients.index')
            ->with('success', 'Membresía asignada correctamente.');
    }
}