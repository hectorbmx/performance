<?php

namespace App\Http\Requests\Auth;

use App\Models\CoachSubscription;
use App\Models\User;
use App\Support\CoachAccessStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->email)->first();

        \Log::info('Login attempt', [
            'email' => $this->email,
            'user_found' => $user ? 'yes' : 'no',
            'user_id' => $user?->id,
        ]);

        \Log::info('Password check', [
            'password_input_length' => strlen($this->password),
            'hash_check_result' => $user ? Hash::check($this->password, $user->password) : 'no_user',
        ]);

        if (!$user || !Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        \Log::info('Credentials OK, checking role', [
            'has_coach_role' => $user->hasRole('coach'),
        ]);

        if ($user->hasRole('coach')) {
            $this->validateCoachSubscription($user);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    protected function validateCoachSubscription(User $user): void
    {
        $sub = CoachSubscription::where('coach_id', $user->id)
            ->orderByDesc('ends_at')
            ->first();

        \Log::info('Coach subscription check', [
            'coach_id' => $user->id,
            'subscription_found' => $sub ? 'yes' : 'no',
            'billing_status' => $sub?->billing_status,
            'grace_until' => $sub?->grace_until,
        ]);

        if (!$sub) {
            throw ValidationException::withMessages([
                'email' => 'Acceso bloqueado: no tienes una suscripcion asignada. Contacta al administrador.',
            ]);
        }

        $access = CoachAccessStatus::for($sub);
        if ($access['can_access']) {
            \Log::info('Subscription allows login', [
                'access_status' => $access['key'],
                'reason' => $access['reason'],
            ]);

            return;
        }

        \Log::info('Subscription blocked', [
            'today' => now()->startOfDay(),
            'grace_until' => $sub->grace_until,
            'access_status' => $access['key'],
        ]);

        throw ValidationException::withMessages([
            'email' => 'Acceso bloqueado: tu suscripcion esta pendiente de pago y el periodo de gracia ha vencido.',
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
