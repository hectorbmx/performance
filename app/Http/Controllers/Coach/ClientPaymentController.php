<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Services\RecordClientPayment;
use Illuminate\Http\Request;
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

    public function store(Request $request, ClientMembership $membership, RecordClientPayment $payments)
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

        $validated = $request->validate(RecordClientPayment::RULES + [
            'idempotency_key' => 'required|string|max:80',
        ]);

        $sessionKey = $this->paymentIdempotencySessionKey($coachId, $membership->id);
        if (!hash_equals((string) session()->get($sessionKey, ''), $validated['idempotency_key'])) {
            return back()
                ->withInput()
                ->with('error', 'No se pudo validar el intento de pago. Vuelve a intentarlo.');
        }

        $paymentWasCreated = $payments->record($membership, $coachId, $validated);

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
