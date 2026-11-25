<?php
namespace App\Http\Middleware;

use App\Models\UserApp;
use Closure;
use Illuminate\Http\Request;

class EnsureAthleteAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user instanceof UserApp,403,'Se requiere una cuenta de atleta.');
        $user->refresh();
        abort_unless($user->is_active,401,'Sesión de atleta no válida.');
        abort_if($user->self_registered_at && !$user->email_verified_at,403,'Confirma tu correo para continuar.');
        return $next($request);
    }
}
