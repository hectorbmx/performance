<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientPaymentController extends Controller
{
    public function create(ClientMembership $membership)
    {
        $coachId = auth()->id();

        // Verificar que la membresía pertenezca al coach
        if ($membership->coach_id !== $coachId) {
            abort(403, 'No tienes permiso para registrar pagos en esta membresía.');
        }

        // Verificar que la membresía esté unpaid
        if ($membership->billing_status === 'paid') {
            return redirect()->route('coach.clients.index')
                ->with('error', 'Esta membresía ya está pagada.');
        }

        $paymentIdempotencyKey = (string) Str::uuid();
        session()->put($this->paymentIdempotencySessionKey($coachId, $membership->id), $paymentIdempotencyKey);

        return view('coach.client-payments.create', compact('membership', 'paymentIdempotencyKey'));
    }

    public function store(Request $request, ClientMembership $membership)
    {
        $coachId = auth()->id();

        // Verificar que la membresía pertenezca al coach
        if ($membership->coach_id !== $coachId) {
            abort(403, 'No tienes permiso para registrar pagos en esta membresía.');
        }

        // Verificar que la membresía esté unpaid
        if ($membership->billing_status === 'paid') {
            return redirect()->route('coach.clients.index')
                ->with('error', 'Esta membresía ya está pagada.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|lte:amount',
            'payment_method' => 'required|string|in:efectivo,transferencia,tarjeta,paypal,otro',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'idempotency_key' => 'required|string|max:80',
        ]);

        $sessionKey = $this->paymentIdempotencySessionKey($coachId, $membership->id);
        if (!hash_equals((string) session()->get($sessionKey, ''), $validated['idempotency_key'])) {
            return back()
                ->withInput()
                ->with('error', 'No se pudo validar el intento de pago. Vuelve a intentarlo.');
        }

        $paymentWasCreated = DB::transaction(function () use ($membership, $validated, $coachId) {
            $lockedMembership = ClientMembership::query()
                ->whereKey($membership->id)
                ->where('coach_id', $coachId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMembership->billing_status === 'paid') {
                return false;
            }

            // Calcular monto final
            $discount = $validated['discount'] ?? 0;
            $final_amount = $validated['amount'] - $discount;

            // Crear registro de pago
            ClientPayment::create([
                'coach_id' => $coachId,
                'client_id' => $lockedMembership->client_id,
                'client_membership_id' => $lockedMembership->id,
                'amount' => $validated['amount'],
                'discount' => $discount,
                'final_amount' => $final_amount,
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'idempotency_key' => $validated['idempotency_key'],
            ]);

            // Actualizar membresía a pagada
            $lockedMembership->update([
                'billing_status' => 'paid',
                'paid_at' => $validated['payment_date'],
            ]);

            return true;
        });

        session()->forget($sessionKey);

        if (!$paymentWasCreated) {
            return redirect()->route('coach.clients.index')
                ->with('error', 'Esta membresía ya está pagada.');
        }

        return redirect()->route('coach.clients.index')
            ->with('success', 'Pago registrado exitosamente.');
    }

    private function paymentIdempotencySessionKey(int $coachId, int $membershipId): string
    {
        return "client_payment_idempotency.{$coachId}.{$membershipId}";
    }
}
