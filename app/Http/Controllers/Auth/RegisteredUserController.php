<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Models\CoachSubscription;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\TenantBaseCatalogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $request->session()->put('coach_registration_started_at', now()->timestamp);

        return view('auth.register');
    }

    public function store(Request $request, TenantBaseCatalogService $catalogs): RedirectResponse
    {
        $this->guardAgainstAutomatedRegistration($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $coachRole = Role::firstOrCreate([
                'name' => 'coach',
                'guard_name' => 'web',
            ]);

            $user->assignRole($coachRole);

            CoachProfile::create([
                'user_id' => $user->id,
                'display_name' => $validated['display_name'],
                'phone' => $validated['phone'] ?? null,
                'status' => 'trial',
                'created_by' => null,
                'updated_by' => null,
            ]);

            $this->assignTrialSubscription($user);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $catalogs->seedForCoach($user);

        $request->session()->forget('coach_registration_started_at');

        return redirect()->route('verification.notice')
            ->with('status', 'Te enviamos un correo para activar tu cuenta.');
    }

    private function guardAgainstAutomatedRegistration(Request $request): void
    {
        $startedAt = (int) $request->session()->get('coach_registration_started_at', 0);
        $elapsedSeconds = now()->timestamp - $startedAt;

        $honeypotTouched = filled($request->input('website'))
            || filled($request->input('company_url'))
            || filled($request->input('work_email_confirmation'));

        if (!$startedAt || $elapsedSeconds < 4 || $elapsedSeconds > 3600 || $honeypotTouched) {
            throw ValidationException::withMessages([
                'email' => 'No pudimos completar el registro. Intentalo nuevamente.',
            ]);
        }
    }

    private function assignTrialSubscription(User $user): void
    {
        $trialDays = 14;

        $plan = MembershipPlan::query()
            ->where('is_active', true)
            ->where('amount', 0)
            ->where(function ($query) {
                $query->where('payment_provider', 'manual')
                    ->orWhereNull('payment_provider');
            })
            ->orderBy('id')
            ->first();

        if (!$plan) {
            $plan = MembershipPlan::create([
                'name' => 'Plan de prueba',
                'description' => 'Acceso de prueba para nuevos coaches.',
                'amount' => 0,
                'currency' => 'mxn',
                'payment_provider' => 'manual',
                'billing_cycle_days' => $trialDays,
                'client_limit' => 5,
                'is_active' => true,
                'stripe_product_id' => null,
                'stripe_price_id' => null,
            ]);
        }

        $startsAt = now()->startOfDay();
        $endsAt = $startsAt->copy()->addDays((int) $plan->billing_cycle_days);

        CoachSubscription::create([
            'coach_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'plan_name_snapshot' => $plan->name,
            'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
            'client_limit_snapshot' => $plan->client_limit,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'next_renewal_at' => $endsAt->toDateString(),
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'paid',
            'grace_until' => null,
            'paid_at' => $startsAt->toDateString(),
        ]);
    }
}
