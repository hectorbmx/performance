<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\CoachClientPlan;
use App\Services\Billing\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
public function store(Request $request, Client $client, StripeConnectService $connect)
{
    abort_unless($client->coach_id === auth()->id(), 403, 'No tienes permiso para asignar planes a este cliente.');

    $validated = $request->validate([
        'coach_client_plan_id' => ['required','exists:coach_client_plans,id'],
        'reminder_days_before' => ['nullable','integer','min:1'],
        'register_payment'     => ['nullable'], // compatibilidad con el checkbox anterior
        'payment_flow'         => ['nullable','in:later,manual_now,stripe_now'],
        'grace_days'           => ['nullable','integer','min:0'],
    ]);

    $plan = CoachClientPlan::findOrFail($validated['coach_client_plan_id']);
    abort_unless($plan->coach_id === auth()->id(), 403, 'Este plan no te pertenece.');

    $paymentFlow = $validated['payment_flow'] ?? ($request->boolean('register_payment') ? 'manual_now' : 'later');
    $registerPaymentNow = $paymentFlow === 'manual_now';
    $collectWithStripe = $paymentFlow === 'stripe_now';

    // ✅ OJO: para renovar debe considerar la ÚLTIMA membresía (activa o vencida)
    $lastMembership = $client->memberships()->latest('ends_at')->first();

    $starts_at = now()->startOfDay();
    if ($lastMembership && $lastMembership->ends_at) {
        $starts_at = $lastMembership->ends_at->copy()->addDay()->startOfDay();
    }

    $ends_at = $starts_at->copy()->addDays($plan->billing_cycle_days);
    $next_renewal_at = $ends_at->copy();

    // Si NO pagará ahora, calcula gracia
    $grace_until = null;
    $graceDays = (int)($validated['grace_days'] ?? $plan->grace_days ?? 0);
    if (!$registerPaymentNow && !$collectWithStripe && $graceDays > 0) {
        $grace_until = $starts_at->copy()->addDays($graceDays);
    }

    // ✅ Crea SIEMPRE como unpaid. El pago real lo cambia a paid.
    $membership = ClientMembership::create([
        'coach_id' => auth()->id(),
        'client_id' => $client->id,
        'coach_client_plan_id' => $plan->id,
        'plan_name_snapshot' => $plan->name,
        'price_snapshot' => $plan->price,
        'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        'next_renewal_at' => $next_renewal_at,
        'reminder_days_before' => $validated['reminder_days_before'] ?? $plan->reminder_days_before,
        'status' => 'active',
        'billing_status' => 'unpaid',
        'grace_until' => $grace_until,
        'paid_at' => null,
    ]);

    // ✅ Flujo 2: pagar ahora -> mandar a pantalla de pago
    if ($registerPaymentNow) {
        return redirect()
            ->route('coach.client-payments.create', $membership)
            ->with('success', 'Membresía creada. Ahora registra el pago.');
    }

    // ✅ Flujo 1: no pagar ahora -> index
    if ($collectWithStripe) {
        try {
            $session = $connect->createMembershipCheckout($membership);
        } catch (\Throwable $e) {
            return redirect()
                ->route('coach.client-payments.create', $membership)
                ->withErrors(['stripe' => $e->getMessage()]);
        }

        return redirect()->away($session->url);
    }

    return redirect()
        ->route('coach.clients.index')
        ->with('success', 'Membresía asignada correctamente (pendiente de pago).');
}

    public function destroy(ClientMembership $membership)
{
    // 🔒 Seguridad: que pertenezca al coach logueado
    if ((int)$membership->coach_id !== (int)auth()->id()) {
        abort(403);
    }

    DB::transaction(function () use ($membership) {

        // 1) Revertir pagos asociados (soft delete) y marcarlos como void
        $membership->payments()
            ->get()
            ->each(function ($p) {
                $p->status = 'void';     // o 'cancelled'
                $p->save();
                $p->delete();            // soft delete
            });

        // 2) Marcar membresía como no pagada (consistencia) y borrarla
        $membership->update([
            'billing_status' => 'unpaid',
            'paid_at'        => null,
        ]);

        $membership->delete(); // soft delete
    });

    return back()->with('success', 'Membresía eliminada y pagos asociados anulados.');
}
}
