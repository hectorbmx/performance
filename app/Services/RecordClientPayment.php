<?php

namespace App\Services;

use App\Models\ClientMembership;
use App\Models\ClientPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordClientPayment
{
    public const RULES = [
        'amount' => 'required|numeric|min:0',
        'discount' => 'nullable|numeric|min:0|lte:amount',
        'payment_method' => 'required|string|in:efectivo,transferencia,tarjeta,paypal,otro',
        'payment_date' => 'required|date',
        'notes' => 'nullable|string|max:500',
    ];

    public function record(ClientMembership $membership, int $coachId, array $validated): bool
    {
        return DB::transaction(function () use ($membership, $validated, $coachId) {
            $lockedMembership = ClientMembership::query()
                ->whereKey($membership->id)
                ->where('client_id', $membership->client_id)
                ->where('coach_id', $coachId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMembership->billing_status === 'paid') {
                return false;
            }

            if ($lockedMembership->status === 'cancelled' || !in_array($lockedMembership->billing_status, ['unpaid', 'past_due'], true)) {
                throw ValidationException::withMessages([
                    'membership' => 'Esta membresía no admite pagos manuales.',
                ]);
            }

            $discount = $validated['discount'] ?? 0;
            $finalAmount = $validated['amount'] - $discount;

            ClientPayment::create([
                'coach_id' => $coachId,
                'client_id' => $lockedMembership->client_id,
                'client_membership_id' => $lockedMembership->id,
                'amount' => $validated['amount'],
                'discount' => $discount,
                'final_amount' => $finalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'idempotency_key' => $validated['idempotency_key'],
            ]);

            $lockedMembership->update([
                'billing_status' => 'paid',
                'paid_at' => $validated['payment_date'],
            ]);

            return true;
        });
    }
}
