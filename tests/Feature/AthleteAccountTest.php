<?php
namespace Tests\Feature;

use App\Mail\AthleteAccountCode;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Services\AppNotificationService;
use App\Services\ClientMembershipAccessService;
use App\Services\ClientMembershipAccessResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AthleteAccountTest extends TestCase
{
    private const ROOT = '/api/v1/app';
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['database.connections.accounts_memory'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
            'cache.default'=>'array','hashing.bcrypt.rounds'=>4]);
        DB::setDefaultConnection('accounts_memory');
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->assertSame('sqlite',DB::getDriverName());
        foreach (['2014_10_12_000000_create_users_table.php','2026_01_05_044105_create_clients_table.php',
            '2026_01_05_230635_create_users_app.php','2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_11_16_000001_decouple_athlete_accounts_from_clients.php'] as $file) (require database_path('migrations/'.$file))->up();
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();$t->morphs('tokenable');$t->string('name');$t->string('token',64)->unique();$t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();
        });
        Schema::create('user_devices',function (Blueprint $t) {$t->id();$t->unsignedBigInteger('user_id');$t->timestamps();});
        Mail::fake();
        $this->mock(ClientMembershipAccessService::class,function ($mock) {
            $mock->shouldReceive('forUserApp')->andReturn(new ClientMembershipAccessResult('expired',false,null,'Vencida.'));
        });
        $this->mock(AppNotificationService::class,fn($m)=>$m->shouldReceive('forUserApp')->andReturn([]));
    }
    protected function tearDown(): void {DB::purge('accounts_memory');parent::tearDown();}
    private function data(): array {return ['first_name'=>'Ana','last_name'=>'Prueba','email'=>'ana@example.test',
        'password'=>'Password123!','password_confirmation'=>'Password123!','privacy_accepted'=>true];}
    private function register(): UserApp {
        $this->postJson(self::ROOT.'/register',$this->data())->assertCreated()->assertJsonPath('verification_required',true)->assertJsonMissingPath('token');
        return UserApp::firstOrFail();
    }
    private function code(string $purpose): string {
        $sent = Mail::sent(AthleteAccountCode::class,fn($mail)=>$mail->purpose===$purpose)->last();
        $this->assertNotNull($sent);return $sent->code;
    }
    private function verify(): void {$this->postJson(self::ROOT.'/email/verify',['email'=>'ana@example.test','code'=>$this->code('verify')])->assertOk();}
    public function test_registration_verification_and_login_do_not_create_client_or_membership(): void {
        $user=$this->register();$this->assertNull($user->client_id);$this->assertSame(0,Client::count());
        $this->assertSame('Ana',$user->athleteProfile->first_name);
        $this->postJson(self::ROOT.'/login',$this->data())->assertForbidden()->assertJsonPath('code','email_verification_required');
        $this->verify();
        $login=$this->postJson(self::ROOT.'/login',$this->data())->assertOk()->assertJsonPath('client',null)
            ->assertJsonPath('context.coach_id',null)->assertJsonPath('athlete_profile.first_name','Ana');
        $this->assertNotEmpty($login->json('token'));
        $this->withToken($login->json('token'))->getJson(self::ROOT.'/me')->assertOk()->assertJsonPath('access.access_state','no_coach');
    }
    public function test_privacy_required_and_existing_account_not_claimed_by_email(): void {
        $data=$this->data();$data['privacy_accepted']=false;
        $this->postJson(self::ROOT.'/register',$data)->assertUnprocessable();
        $user=$this->register();$hash=$user->password;
        $data=$this->data();$data['email']='ANA@example.test';
        $this->postJson(self::ROOT.'/register',$data)->assertUnprocessable();
        $this->assertSame(1,UserApp::count());$this->assertSame($hash,$user->fresh()->password);
    }
    public function test_verification_code_is_hashed_one_use_expiring_and_limited(): void {
        $this->register();$code=$this->code('verify');
        $this->assertNotSame($code,DB::table('athlete_email_actions')->value('token_hash'));
        for ($i=0;$i<5;$i++) $this->postJson(self::ROOT.'/email/verify',['email'=>'ana@example.test','code'=>$code==='000000'?'111111':'000000'])->assertUnprocessable();
        $this->postJson(self::ROOT.'/email/verify',['email'=>'ana@example.test','code'=>$code])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson(self::ROOT.'/email/resend',['email'=>'ana@example.test'])->assertOk();$code=$this->code('verify');
        $this->travel(16)->minutes();
        $this->postJson(self::ROOT.'/email/verify',['email'=>'ana@example.test','code'=>$code])->assertUnprocessable();
    }
    public function test_reset_is_separate_from_coach_tokens_and_revokes_sessions(): void {
        $user=$this->register();$this->verify();$user->createToken('old');
        Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
        DB::table('password_reset_tokens')->insert(['email'=>$user->email,'token'=>'coach-token']);
        $this->postJson(self::ROOT.'/password/forgot',['email'=>$user->email])->assertOk();$code=$this->code('reset');
        $this->postJson(self::ROOT.'/email/verify',['email'=>$user->email,'code'=>$code])->assertUnprocessable();
        $data=['email'=>$user->email,'code'=>$code,'password'=>'NewPassword123!','password_confirmation'=>'NewPassword123!'];
        $this->postJson(self::ROOT.'/password/reset',$data)->assertOk();
        $this->assertSame(0,$user->tokens()->count());$this->assertTrue(Hash::check($data['password'],$user->fresh()->password));
        $this->assertSame('coach-token',DB::table('password_reset_tokens')->value('token'));
        $this->postJson(self::ROOT.'/password/reset',$data)->assertUnprocessable();
    }
    public function test_reset_request_does_not_disclose_unknown_or_inactive_email(): void {
        $a=$this->postJson(self::ROOT.'/password/forgot',['email'=>'missing@example.test'])->assertOk()->json();
        $user=$this->register();$user->update(['is_active'=>false]);
        $b=$this->postJson(self::ROOT.'/password/forgot',['email'=>$user->email])->assertOk()->json();
        $this->assertSame($a,$b);$this->assertCount(0,Mail::sent(AthleteAccountCode::class,fn($m)=>$m->purpose==='reset'));
    }
    public function test_profile_is_owned_by_token_and_does_not_edit_client(): void {
        $user=$this->register();$this->verify();Sanctum::actingAs($user->fresh());
        $this->patchJson(self::ROOT.'/account/profile',['first_name'=>'Nuevo','last_name'=>'Nombre','user_app_id'=>999])
            ->assertOk()->assertJsonPath('data.first_name','Nuevo');
        $this->assertSame('Nuevo',$user->fresh()->athleteProfile->first_name);
        $this->assertSame(0,Client::count());
    }
    public function test_existing_account_login_and_identity_survive_client_deactivation_and_physical_delete(): void {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Legado','is_active'=>false]);
        $user=UserApp::create(['client_id'=>$client->id,'email'=>'old@example.test','password'=>Hash::make('Password123!'),'is_active'=>true]);
        $this->postJson(self::ROOT.'/login',['email'=>$user->email,'password'=>'Password123!'])->assertOk()->assertJsonPath('client.id',$client->id);
        $client->forceDelete();$this->assertNull($user->fresh()->client_id);$this->assertSame($user->id,$user->fresh()->id);
        $this->postJson(self::ROOT.'/login',['email'=>$user->email,'password'=>'Password123!'])->assertOk()->assertJsonPath('client',null);
    }
    public function test_inactive_account_and_coach_cannot_use_athlete_me(): void {
        $user=$this->register();$this->verify();$user->update(['is_active'=>false]);
        $this->postJson(self::ROOT.'/login',$this->data())->assertUnprocessable();
        Sanctum::actingAs($user);$this->getJson(self::ROOT.'/me')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());$this->getJson(self::ROOT.'/me')->assertForbidden();
    }
    public function test_deletion_requires_password_revokes_tokens_and_creates_tracked_request(): void {
        $user=$this->register();$this->verify();$user=$user->fresh();$user->createToken('one');$user->createToken('two');
        Sanctum::actingAs($user);
        $this->deleteJson(self::ROOT.'/account',['password'=>'wrong','confirm'=>true])->assertUnprocessable();
        $this->assertTrue($user->fresh()->is_active);
        $this->deleteJson(self::ROOT.'/account',['password'=>'Password123!','confirm'=>true])->assertAccepted()->assertJsonPath('status','requested');
        $this->assertFalse($user->fresh()->is_active);$this->assertSame(0,$user->tokens()->count());
        $this->assertSame(1,DB::table('athlete_account_deletions')->where('user_app_id',$user->id)->count());
        $this->getJson(self::ROOT.'/me')->assertUnauthorized();
    }
    public function test_deletion_processor_removes_independent_account_and_profile_without_touching_other_accounts(): void {
        $user=$this->register();$this->verify();$other=UserApp::create(['email'=>'other@example.test','is_active'=>true]);
        Sanctum::actingAs($user->fresh());
        $id=$this->deleteJson(self::ROOT.'/account',['password'=>'Password123!','confirm'=>true])->assertAccepted()->json('request_id');
        $service=app(\App\Services\AthleteAccountDeletion::class);
        $this->assertTrue($service->completeUnlinked($id));
        $this->assertNull(UserApp::withTrashed()->find($user->id));$this->assertNotNull($other->fresh());
        $this->assertSame(0,DB::table('athlete_profiles')->where('user_app_id',$user->id)->count());
        $request=DB::table('athlete_account_deletions')->where('id',$id)->first();
        $this->assertSame('completed',$request->status);$this->assertNull($request->user_app_id);
        $this->assertNotSame($user->email,$request->notification_email);
        $this->assertSame($user->email,\Illuminate\Support\Facades\Crypt::decryptString($request->notification_email));
        $this->assertTrue($service->completeUnlinked($id));
    }
    public function test_deletion_processor_holds_linked_records_for_platform_review(): void {
        $user=$this->register();$this->verify();$coach=User::factory()->create();
        $client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Comercial','is_active'=>true]);$user->update(['client_id'=>$client->id]);
        Sanctum::actingAs($user->fresh());
        $id=$this->deleteJson(self::ROOT.'/account',['password'=>'Password123!','confirm'=>true])->assertAccepted()->json('request_id');
        $this->assertFalse(app(\App\Services\AthleteAccountDeletion::class)->completeUnlinked($id));
        $this->assertNotNull($user->fresh());$this->assertNotNull($client->fresh());
        $this->assertSame('requested',DB::table('athlete_account_deletions')->where('id',$id)->value('status'));
    }
    public function test_migration_preserves_existing_accounts_and_copies_only_unambiguous_setup_tokens(): void {
        config(['database.connections.accounts_upgrade'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::setDefaultConnection('accounts_upgrade');
        try {
            $this->assertSame(':memory:',DB::connection()->getDatabaseName());
            foreach (['2014_10_12_000000_create_users_table.php','2026_01_05_044105_create_clients_table.php',
                '2026_01_05_230635_create_users_app.php','2026_01_06_022715_rename_users_app_to_user_apps_table.php',
                '2014_10_12_100000_create_password_reset_tokens_table.php'] as $file) (require database_path('migrations/'.$file))->up();
            $coach=User::factory()->create(['email'=>'collision@example.test']);
            $client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Anterior','is_active'=>true]);
            $a=UserApp::create(['client_id'=>$client->id,'email'=>'pending@example.test','is_active'=>false]);
            $other=Client::create(['coach_id'=>$coach->id,'first_name'=>'Otro','is_active'=>true]);
            UserApp::create(['client_id'=>$other->id,'email'=>$coach->email,'is_active'=>true]);
            foreach ([$a->email,$coach->email] as $email) DB::table('password_reset_tokens')->insert(['email'=>$email,'token'=>Hash::make('old-token'),'created_at'=>now()]);
            $before=DB::table('user_apps')->orderBy('id')->get()->toArray();$fields=array_keys((array)$before[0]);
            (require database_path('migrations/2026_11_16_000001_decouple_athlete_accounts_from_clients.php'))->up();
            $this->assertEquals($before,DB::table('user_apps')->select($fields)->orderBy('id')->get()->toArray());
            $this->assertSame('Anterior',$a->fresh()->athleteProfile->first_name);$this->assertFalse($a->fresh()->is_active);
            $this->assertSame(2,DB::table('athlete_profiles')->count());
            $this->assertSame([$a->email],DB::table('athlete_setup_tokens')->pluck('email')->all());
            $this->assertSame(2,DB::table('password_reset_tokens')->count());
        } finally {DB::purge('accounts_upgrade');DB::setDefaultConnection('accounts_memory');}
    }
}
