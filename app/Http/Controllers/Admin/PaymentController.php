<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachSubscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['coach.coachProfile', 'subscription'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.payments.index', compact('payments'));
    }

    public function create()
    {
        $subs = CoachSubscription::with(['coach.coachProfile'])
            ->orderByDesc('id')
            ->get();
        $selectedSubId = request()->integer('subscription_id');

        return view('admin.payments.create', compact('subs','selectedSubId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'coach_subscription_id' => ['required','integer', Rule::exists('coach_subscriptions','id')],
            'amount' => ['required','numeric','min:0'],
            'currency' => ['required','string','size:3'],
            'paid_at' => ['required','date'],
            'method' => ['required', Rule::in(['manual'])],
            'reference' => ['nullable','string','max:255'],
            'receipt' => ['nullable','file','max:5120'], // 5MB
        ]);

        $sub = CoachSubscription::with('coach')->findOrFail($validated['coach_subscription_id']);

        $disk = config('filesystems.default'); // hoy: public, futuro: s3
        $receiptPath = null;

        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', $disk);
        }

        Payment::create([
            'coach_subscription_id' => $sub->id,
            'coach_id' => $sub->coach_id,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'paid_at' => $validated['paid_at'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'receipt_disk' => $disk,
            'receipt_path' => $receiptPath,
            'created_by' => auth()->id(),
        ]);

        $sub->billing_status = 'paid';
        $sub->paid_at = $validated['paid_at'];
        if ($sub->status === 'past_due') {
            $sub->status = 'active';
        }
        $sub->save();

        return redirect()->route('admin.payments.index')
            ->with('success', 'Pago registrado correctamente.');
    }

    public function edit(Payment $payment)
    {
        $payment->load(['coach.coachProfile', 'subscription']);

        $subs = CoachSubscription::with(['coach.coachProfile'])
            ->orderByDesc('id')
            ->get();

        return view('admin.payments.edit', compact('payment', 'subs'));
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'coach_subscription_id' => ['required','integer', Rule::exists('coach_subscriptions','id')],
            'amount' => ['required','numeric','min:0'],
            'currency' => ['required','string','size:3'],
            'paid_at' => ['required','date'],
            'method' => ['required', Rule::in(['manual'])],
            'reference' => ['nullable','string','max:255'],
            'receipt' => ['nullable','file','max:5120'], // 5MB
        ]);

        $sub = CoachSubscription::findOrFail($validated['coach_subscription_id']);

        $disk = $payment->receipt_disk ?: config('filesystems.default');
        $receiptPath = $payment->receipt_path;

        if ($request->hasFile('receipt')) {
            // borrar anterior si existe
            if ($payment->receipt_path) {
                Storage::disk($disk)->delete($payment->receipt_path);
            }
            $disk = config('filesystems.default');
            $receiptPath = $request->file('receipt')->store('receipts', $disk);
        }

        $payment->update([
            'coach_subscription_id' => $sub->id,
            'coach_id' => $sub->coach_id,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'paid_at' => $validated['paid_at'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'receipt_disk' => $disk,
            'receipt_path' => $receiptPath,
        ]);

        return redirect()->route('admin.payments.index')
            ->with('success', 'Pago actualizado correctamente.');
    }

    public function destroy(Payment $payment)
    {
        if ($payment->receipt_path) {
            Storage::disk($payment->receipt_disk)->delete($payment->receipt_path);
        }

        $payment->delete();

        return redirect()->route('admin.payments.index')
            ->with('success', 'Pago eliminado (soft delete).');
    }
}
