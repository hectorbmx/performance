<?php

namespace App\Http\Controllers;

use App\Models\UserApp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AppPasswordSetupController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('app-password-setup.show', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): View
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (!$reset || !Hash::check($data['token'], $reset->token)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'El enlace no es valido o ya fue usado.']);
        }

        if (Carbon::parse($reset->created_at)->lt(now()->subHours(24))) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'El enlace expiro. Solicita uno nuevo a tu coach.']);
        }

        $userApp = UserApp::query()
            ->where('email', $data['email'])
            ->where('is_active', true)
            ->first();

        if (!$userApp) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Cuenta no encontrada o inactiva.']);
        }

        $userApp->update([
            'password' => Hash::make($data['password']),
            'activated_at' => $userApp->activated_at ?: now(),
            'activation_code' => null,
            'activation_expires_at' => null,
        ]);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return view('app-password-setup.success', [
            'email' => $userApp->email,
        ]);
    }
}
