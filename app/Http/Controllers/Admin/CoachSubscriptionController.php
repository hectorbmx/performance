<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\CoachSubscription;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoachSubscriptionController extends Controller
{
    public function index()
    {
        $subs = CoachSubscription::with(['coach.coachProfile', 'plan'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.subscriptions.index', compact('subs'));
    }

    public function create()
    {
        $coaches = User::role('coach')
            ->with('coachProfile')
            ->orderBy('name')
            ->get();
        $selectedCoachId = request()->integer('coach_id');

        $plans = MembershipPlan::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.subscriptions.create', compact('coaches', 'plans','selectedCoachId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'coach_id' => ['required','integer', Rule::exists('users','id')],
            'membership_plan_id' => ['required','integer', Rule::exists('membership_plans','id')],
            'starts_at' => ['required','date'],
            'ends_at' => ['required','date','after_or_equal:starts_at'],
            'reminder_days_before' => ['nullable','integer','min:0','max:60'],
            'status' => ['required', Rule::in(['active','past_due','suspended','cancelled'])],
            'register_payment_now' => ['nullable','boolean'],
            'grace_days' => ['nullable','integer','min:0','max:60'],

        ]);

        $coach = User::findOrFail($validated['coach_id']);
        abort_unless($coach->hasRole('coach'), 422);

        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);
        $graceDays = (int)($validated['grace_days'] ?? 5);

        $sub = CoachSubscription::create([
            'coach_id' => $coach->id,
            'membership_plan_id' => $plan->id,

            // Snapshot
            'plan_name_snapshot' => $plan->name,
            'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
            'client_limit_snapshot' => $plan->client_limit,

            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'next_renewal_at' => $validated['ends_at'],
            'reminder_days_before' => $validated['reminder_days_before'] ?? 5,
            'status' => $validated['status'],

            // Billing
            'billing_status' => 'unpaid',
            'grace_until' => \Carbon\Carbon::parse($validated['starts_at'])->addDays($graceDays)->toDateString(),
            'paid_at' => null,
        ]);

            if ($request->boolean('register_payment_now')) {
                return redirect()->route('admin.payments.create', [
                    'subscription_id' => $sub->id,
                ])->with('success', 'Suscripción creada. Registra el pago para marcarla como pagada.');
            }

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Suscripción creada y snapshot aplicado.');
    }
}
