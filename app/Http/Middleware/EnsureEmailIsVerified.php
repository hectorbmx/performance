<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as Middleware;

class EnsureEmailIsVerified extends Middleware
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole('admin')) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
