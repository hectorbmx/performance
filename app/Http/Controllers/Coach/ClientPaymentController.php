<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPayment;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    public function create(ClientMembership $membership)
    {
        // Verificar que la membresía pertenezca al coach
        if ($membership->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para registrar pagos en esta membresía.');
        }

        // Verificar que la membresía esté unpaid
        if ($membership->billing_status === 'paid') {
            return redirect()->route('coach.clients.index')
                ->with('error', 'Esta membresía ya está pagada.');
        }

        return view('coach.client-payments.create', compact('membership'));
    }

    public function store(Request $request, ClientMembership $membership)
    {
        // Verificar que la membresía pertenezca al coach
        if ($membership->coach_id !== auth()->id()) {
            abort(403, 'No tienes permiso para registrar pagos en esta membresía.');
        }

        // Verificar que la membresía esté unpaid
        if ($membership->billing_status === 'paid') {
            return redirect()->route('coach.clients.index')
                ->with('error', 'Esta membresía ya está pagada.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:efectivo,transferencia,tarjeta,paypal,otro',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        // Calcular monto final
        $discount = $validated['discount'] ?? 0;
        $final_amount = $validated['amount'] - $discount;

        // Crear registro de pago
        ClientPayment::create([
            'coach_id' => auth()->id(),
            'client_id' => $membership->client_id,
            'client_membership_id' => $membership->id,
            'amount' => $validated['amount'],
            'discount' => $discount,
            'final_amount' => $final_amount,
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'completed',
        ]);

        // Actualizar membresía a pagada
        $membership->update([
            'billing_status' => 'paid',
            'paid_at' => $validated['payment_date'],
        ]);

        return redirect()->route('coach.clients.index')
            ->with('success', 'Pago registrado exitosamente.');
    }
}