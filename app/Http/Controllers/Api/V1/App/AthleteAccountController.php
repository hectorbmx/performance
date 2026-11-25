<?php
namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use App\Models\UserApp;
use App\Services\AthleteEmailActions;
use App\Services\AppNotificationService;
use App\Services\ClientMembershipAccessService;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AthleteAccountController extends Controller
{
    public const PRIVACY_VERSION = '2026-11-15';

    private function email(Request $request): string
    {
        $data = $request->validate(['email'=>['required','email','max:255']]);
        return mb_strtolower(trim($data['email']));
    }

    private function find(string $email): ?UserApp
    {
        return UserApp::whereRaw('LOWER(email) = ?',[$email])->first();
    }

    public function register(Request $request, AthleteEmailActions $actions)
    {
        $email = $this->email($request);
        $data = $request->validate([
            'first_name'=>['required','string','max:100'], 'last_name'=>['nullable','string','max:100'],
            'password'=>['required','string','min:8','max:128','confirmed'], 'privacy_accepted'=>['required','accepted'],
        ]);
        // Include soft-deleted identities; registration must never claim an existing account.
        if (UserApp::withTrashed()->whereRaw('LOWER(email) = ?',[$email])->exists()) {
            throw ValidationException::withMessages(['email'=>'No se pudo crear la cuenta con este correo. Intenta iniciar sesión o recuperar el acceso.']);
        }
        try {
            $user = DB::transaction(function () use ($data,$email) {
                $user = UserApp::create(['client_id'=>null,'email'=>$email,'password'=>Hash::make($data['password']),
                    'is_active'=>true,'self_registered_at'=>now()]);
                $user->athleteProfile()->create(['first_name'=>trim($data['first_name']),'last_name'=>$data['last_name'] ?? null,
                    'privacy_accepted_at'=>now(),'privacy_version'=>self::PRIVACY_VERSION]);
                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email'=>'No se pudo crear la cuenta con este correo. Intenta iniciar sesión o recuperar el acceso.']);
        }
        $actions->issue($user,'verify');
        return response()->json(['ok'=>true,'verification_required'=>true,'message'=>'Cuenta creada. Confirma tu correo con el código para iniciar sesión.'],201);
    }

    public function requestCode(Request $request, AthleteEmailActions $actions, string $purpose)
    {
        $email = $this->email($request);
        $user = $this->find($email);
        if ($user?->is_active && ($purpose !== 'verify' || ($user->self_registered_at && !$user->email_verified_at))) {
            $actions->issue($user,$purpose);
        }
        return response()->json(['ok'=>true,'message'=>'Si el correo corresponde a una cuenta válida, recibirás un código. Revisa también spam.']);
    }

    public function verify(Request $request, AthleteEmailActions $actions)
    {
        $email = $this->email($request);
        $data = $request->validate(['code'=>['required','digits:6']]);
        $actions->consume($email,'verify',$data['code'],function () use ($email) {
            $user = $this->find($email);
            abort_unless($user?->is_active && $user->self_registered_at,422,'Cuenta no disponible.');
            $user->forceFill(['email_verified_at'=>now(),'activated_at'=>$user->activated_at ?: now()])->save();
        });
        return response()->json(['ok'=>true,'message'=>'Correo confirmado. Ya puedes iniciar sesión.']);
    }

    public function resetPassword(Request $request, AthleteEmailActions $actions)
    {
        $email = $this->email($request);
        $data = $request->validate(['code'=>['required','digits:6'],'password'=>['required','string','min:8','max:128','confirmed']]);
        $actions->consume($email,'reset',$data['code'],function () use ($email,$data) {
            $user = $this->find($email);
            abort_unless($user?->is_active,422,'Cuenta no disponible.');
            $user->forceFill(['password'=>Hash::make($data['password']),'activated_at'=>$user->activated_at ?: now(),
                'email_verified_at'=>now(),'activation_code'=>null,'activation_expires_at'=>null])->save();
            $user->tokens()->delete();
            DB::table('athlete_email_actions')->where('email',$email)->where('purpose','verify')->delete();
            DB::table('athlete_setup_tokens')->where('email',$user->email)->delete();
        });
        return response()->json(['ok'=>true,'message'=>'Contraseña actualizada. Inicia sesión de nuevo en tus dispositivos.']);
    }

    public function login(Request $request)
    {
        $email = $this->email($request);
        $data = $request->validate(['password'=>['required','string','max:128']]);
        $user = $this->find($email);
        if (!$user?->is_active || !$user->password || !Hash::check($data['password'],$user->password)) {
            // Always hash-check a dummy value for unknown accounts; do not disclose activation state.
            if (!$user?->password) Hash::check($data['password'],'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
            throw ValidationException::withMessages(['email'=>'Credenciales inválidas.']);
        }
        if ($user->self_registered_at && !$user->email_verified_at) {
            return response()->json(['ok'=>false,'code'=>'email_verification_required','message'=>'Confirma tu correo para iniciar sesión.'],403);
        }
        $user->forceFill(['last_login_at'=>now()])->save();
        $payload = $this->payload($user);
        $payload['token'] = $user->createToken('app')->plainTextToken;
        return response()->json($payload);
    }

    public function me(Request $request)
    {
        return response()->json($this->payload($this->actor($request)));
    }

    private function actor(Request $request): UserApp
    {
        $user = $request->user();
        abort_unless($user instanceof UserApp && $user->is_active,401,'Sesión no válida.');
        abort_if($user->self_registered_at && !$user->email_verified_at,403,'Confirma tu correo.');
        return $user;
    }

    public function profile(Request $request)
    {
        $user = $this->actor($request);
        if ($request->isMethod('patch')) {
            $data = $request->validate(['first_name'=>['required','string','max:100'],'last_name'=>['nullable','string','max:100']]);
            $user->athleteProfile()->updateOrCreate(['user_app_id'=>$user->id],$data);
            $user->unsetRelation('athleteProfile');
        }
        return response()->json(['ok'=>true,'data'=>$this->personalProfile($user)]);
    }

    private function personalProfile(UserApp $user): array
    {
        $profile = $user->athleteProfile;
        return ['first_name'=>$profile?->first_name ?? $user->client?->first_name ?? '',
            'last_name'=>$profile?->last_name ?? $user->client?->last_name ?? '',
            'email'=>$user->email,'email_verified'=>(bool)$user->email_verified_at,
            'privacy_version'=>$profile?->privacy_version];
    }

    private function payload(UserApp $user): array
    {
        $user->loadMissing(['client','athleteProfile']);
        $client = $user->client;
        $access = $client ? app(ClientMembershipAccessService::class)->forUserApp($user) : null;
        $membership = $access?->membership;
        return ['ok'=>true,'actor_type'=>'client',
            'context'=>['user_app_id'=>$user->id,'client_id'=>$client?->id,'coach_id'=>$client?->coach_id],
            'user'=>['id'=>$user->id,'email'=>$user->email,'client_id'=>$client?->id,'is_active'=>(bool)$user->is_active],
            'athlete_profile'=>$this->personalProfile($user),
            'gym_access'=>['source'=>'platform','generator_enabled'=>(bool)config('workouts.generator_enabled'),
                'executions_enabled'=>(bool)config('workouts.executions_enabled')],
            'client'=>$client ? ['id'=>$client->id,'first_name'=>$client->first_name,'last_name'=>$client->last_name,
                'coach_id'=>$client->coach_id,'is_active'=>(bool)$client->is_active,
                'avatar_url'=>$client->avatar ? url(Storage::disk('public')->url($client->avatar)) : null] : null,
            'membership'=>$membership ? ['id'=>$membership->id,'plan_id'=>$membership->coach_client_plan_id,
                'plan_name'=>$membership->plan_name_snapshot ?: $membership->coachClientPlan?->name,
                'price'=>(float)$membership->price_snapshot,'currency'=>strtoupper($membership->coachClientPlan?->currency ?: 'MXN'),
                'status'=>$membership->status,'billing_status'=>$membership->billing_status,
                'starts_at'=>$membership->starts_at,'ends_at'=>$membership->ends_at,'next_renewal_at'=>$membership->next_renewal_at,
                'grace_until'=>$membership->grace_until,'paid_at'=>$membership->paid_at,'is_stripe'=>(bool)$membership->stripe_subscription_id] : null,
            'access'=>$access?->toArray() ?? ['access_state'=>'no_coach','can_access_service'=>false,'message'=>null],
            'notifications'=>$client?->is_active ? app(AppNotificationService::class)->forUserApp($user,$membership) : [],
        ];
    }

    public function deleteAccount(Request $request)
    {
        $user = $this->actor($request);
        $data = $request->validate(['password'=>['required','string'],'confirm'=>['required','accepted']]);
        if (!Hash::check($data['password'],$user->password)) throw ValidationException::withMessages(['password'=>'La contraseña no coincide.']);
        $id = DB::transaction(function () use ($user) {
            $user = UserApp::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $id = (string) Str::uuid();
            DB::table('athlete_account_deletions')->insert(['id'=>$id,'user_app_id'=>$user->id,
                'status'=>'requested','requested_at'=>now(),'due_at'=>now()->addDays(7)]);
            $user->forceFill(['is_active'=>false])->save();
            $user->tokens()->delete();
            $user->devices()->delete();
            DB::table('athlete_email_actions')->where('email',mb_strtolower($user->email))->delete();
            return $id;
        });
        return response()->json(['ok'=>true,'request_id'=>$id,'status'=>'requested',
            'message'=>'Recibimos tu solicitud de eliminación de cuenta y datos personales. Tu acceso quedó cerrado. La completaremos en un máximo de 7 días y te confirmaremos por correo.'],202);
    }
}
