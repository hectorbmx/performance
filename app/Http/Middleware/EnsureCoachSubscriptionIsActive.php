<?php

namespace App\Http\Middleware;

use App\Models\CoachSubscription;
use App\Support\CoachAccessStatus;
use Closure;
use Illuminate\Http\Request;

class EnsureCoachSubscriptionIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('coach')) {
            return $next($request);
        }

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $sub = CoachSubscription::where('coach_id', $user->id)
            ->orderByDesc('ends_at')
            ->first();

        if (!$sub) {
            return redirect()->route('coach.blocked');
        }

        $access = CoachAccessStatus::for($sub);
        if ($access['can_access']) {
            return $next($request);
        }

        return redirect()->route('coach.blocked');
    }
}
