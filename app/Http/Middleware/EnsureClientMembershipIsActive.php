<?php

namespace App\Http\Middleware;

use App\Models\UserApp;
use App\Services\ClientMembershipAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsureClientMembershipIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user instanceof UserApp) {
            return response()->json([
                'ok' => false,
                'code' => 'client_auth_required',
                'message' => 'No autorizado para el area de atleta.',
            ], 403);
        }

        $access = app(ClientMembershipAccessService::class)->forUserApp($user);

        if (!$access->canAccessService) {
            return response()->json([
                'ok' => false,
                'code' => 'membership_expired',
                'message' => $access->message,
                'access_state' => $access->state,
            ], 403);
        }

        return $next($request);
    }
}
