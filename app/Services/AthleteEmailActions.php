<?php
namespace App\Services;

use App\Mail\AthleteAccountCode;
use App\Models\UserApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AthleteEmailActions
{
    public function issue(UserApp $user, string $purpose): void
    {
        $email = mb_strtolower($user->email);
        $code = str_pad((string) random_int(0,999999),6,'0',STR_PAD_LEFT);
        $issued = DB::transaction(function () use ($email,$purpose,$code) {
            $existing = DB::table('athlete_email_actions')->where(compact('email','purpose'))->lockForUpdate()->first();
            if ($existing && now()->diffInSeconds($existing->created_at) < 60) return false;
            DB::table('athlete_email_actions')->updateOrInsert(compact('email','purpose'),[
                'token_hash'=>Hash::make($code),'attempts'=>0,'created_at'=>now(),'expires_at'=>now()->addMinutes(15),
            ]);
            return true;
        });
        if ($issued) Mail::to($user->email)->send(new AthleteAccountCode($code,$purpose));
    }

    // Returns failure after committing the attempt count, so invalid attempts cannot roll back.
    public function consume(string $email, string $purpose, string $code, callable $action): void
    {
        $valid = DB::transaction(function () use ($email,$purpose,$code,$action) {
            $query = DB::table('athlete_email_actions')->where(compact('email','purpose'));
            $token = (clone $query)->lockForUpdate()->first();
            if (!$token || now()->greaterThanOrEqualTo($token->expires_at) || $token->attempts >= 5) return false;
            if (!Hash::check($code,$token->token_hash)) {
                $query->increment('attempts');
                return false;
            }
            $action();
            $query->delete();
            return true;
        });
        if (!$valid) throw ValidationException::withMessages(['code'=>'El código no es válido o venció. Solicita uno nuevo.']);
    }
}
