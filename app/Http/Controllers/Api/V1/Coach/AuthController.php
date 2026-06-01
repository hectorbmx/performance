<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with('coachProfile')
            ->where('email', $data['email'])
            ->first();

        if (!$user || !$user->hasRole('coach') || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Credenciales invalidas.',
            ], 422);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => false,
                'message' => 'Debes verificar tu correo antes de iniciar sesion.',
                'code' => 'email_not_verified',
            ], 403);
        }

        $token = $user->createToken('coach')->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'actor_type' => 'coach',
            'redirect_to' => 'coach',
            'user' => $this->userPayload($user),
            'coach' => $this->coachPayload($user),
            'subscription' => $this->subscriptionPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load('coachProfile');

        return response()->json([
            'ok' => true,
            'actor_type' => 'coach',
            'user' => $this->userPayload($user),
            'coach' => $this->coachPayload($user),
            'subscription' => $this->subscriptionPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    private function coachPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'display_name' => $user->coachProfile?->display_name ?? $user->name,
            'phone' => $user->coachProfile?->phone,
            'status' => $user->coachProfile?->status,
            'stripe_charges_enabled' => (bool) $user->coachProfile?->stripe_charges_enabled,
        ];
    }

    private function subscriptionPayload(User $user): ?array
    {
        $subscription = CoachSubscription::query()
            ->where('coach_id', $user->id)
            ->orderByDesc('ends_at')
            ->first();

        if (!$subscription) {
            return null;
        }

        $today = now()->startOfDay();
        $accessState = 'blocked';

        if ($subscription->billing_status === 'paid') {
            $accessState = 'active';
        } elseif (
            $subscription->billing_status === 'unpaid'
            && $subscription->grace_until
            && $today->lte($subscription->grace_until->copy()->startOfDay())
        ) {
            $accessState = 'grace';
        }

        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan_name_snapshot,
            'status' => $subscription->status,
            'billing_status' => $subscription->billing_status,
            'access_state' => $accessState,
            'starts_at' => optional($subscription->starts_at)->toDateString(),
            'ends_at' => optional($subscription->ends_at)->toDateString(),
            'grace_until' => optional($subscription->grace_until)->toDateString(),
            'client_limit' => $subscription->client_limit_snapshot,
        ];
    }
}
