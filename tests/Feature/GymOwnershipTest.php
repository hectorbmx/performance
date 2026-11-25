<?php
namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Models\WorkoutExercise;
use App\Services\ClientMembershipAccessService;
use App\Services\WorkoutOwnershipTransition;
use Database\Seeders\WorkoutExerciseCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GymOwnershipTest extends TestCase
{
    use \Tests\Concerns\InstallsPersonalWorkoutSchema;
    private UserApp $athlete;
    private const ROOT='/api/v1/app';
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureCoachApiAccess::class,\Illuminate\Routing\Middleware\ThrottleRequests::class]);
        config(['database.connections.gym_memory'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
            'cache.default'=>'array','hashing.bcrypt.rounds'=>4,'workouts.generator_enabled'=>true,'workouts.executions_enabled'=>true]);
        DB::setDefaultConnection('gym_memory');$this->assertSame(':memory:',DB::connection()->getDatabaseName());
        foreach(['2014_10_12_000000_create_users_table.php','2026_01_05_044105_create_clients_table.php','2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php','2019_12_14_000001_create_personal_access_tokens_table.php',
            '2026_01_06_042508_create_training_sessions_table.php','2026_01_06_042534_create_training_sections_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php'] as $file)(require database_path('migrations/'.$file))->up();
        $this->installPersonalWorkoutSchema();(new WorkoutExerciseCatalogSeeder)->run();
        WorkoutExercise::whereIn('code',['lower_body_back_squat','lower_body_front_squat','lower_body_romanian_deadlift'])->update(['review_status'=>'reviewed']);
        Schema::create('user_devices',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('user_id')]);
        $this->athlete=UserApp::create(['email'=>'personal@example.test','password'=>Hash::make('Password123!'),'is_active'=>true,
            'self_registered_at'=>now(),'email_verified_at'=>now(),'activated_at'=>now()]);
        $this->athlete->athleteProfile()->create(['first_name'=>'Atleta']);Sanctum::actingAs($this->athlete);
        $this->mock(ClientMembershipAccessService::class,fn($m)=>$m->shouldNotReceive('forUserApp'));
        Mail::fake();
    }
    protected function tearDown(): void {DB::purge('gym_memory');parent::tearDown();}
    private function exercise(): WorkoutExercise {return WorkoutExercise::where('code','lower_body_back_squat')->firstOrFail();}
    private function body(): array
    {
        return ['client_execution_id'=>(string)Str::uuid(),'title'=>'Rutina personal','equipment'=>['barbell','rack'],
            'exercises'=>WorkoutExercise::where('review_status','reviewed')->orderBy('id')->get()->map(fn($e)=>[
                'exercise_id'=>$e->id,'sets'=>1,'reps_min'=>8,'reps_max'=>12,'target_rir'=>2,'rest_seconds'=>90,'chosen_load'=>null,'unit'=>'kg',
            ])->all()];
    }
    private function start(?array $body=null): array {return $this->postJson(self::ROOT.'/workout-generator/executions',$body??$this->body())->assertCreated()->json('data');}
    private function save(array $execution,?array $overrides=null): array
    {
        $set=$execution['prescription']['content']['sections'][0]['exercises'][0]['sets'][0]['uuid'];
        $body=array_replace(['operation_id'=>(string)Str::uuid(),'expected_revision'=>0,'status'=>'completed','type'=>'work','load_mode'=>'total_barbell',
            'actual_reps'=>10,'actual_load'=>'75','load_unit'=>'kg','actual_rir'=>'2','performed_at'=>now()->utc()->format('Y-m-d\TH:i:s\Z')],$overrides??[]);
        return [$set,$body,$this->putJson(self::ROOT.'/workout-executions/'.$execution['uuid'].'/sets/'.$set,$body)];
    }
    public function test_independent_pilot_from_signup_to_completed_session_and_progress_without_rm():void {
        $data=['first_name'=>'Piloto','email'=>'pilot-independent@example.test','password'=>'Password123!','password_confirmation'=>'Password123!','privacy_accepted'=>true];
        $this->postJson(self::ROOT.'/register',$data)->assertCreated()->assertJsonMissingPath('token');
        $mail=Mail::sent(\App\Mail\AthleteAccountCode::class,fn($mail)=>$mail->purpose==='verify')->last();$this->assertNotNull($mail);
        $this->postJson(self::ROOT.'/email/verify',['email'=>$data['email'],'code'=>$mail->code])->assertOk();
        $login=$this->postJson(self::ROOT.'/login',$data)->assertOk()->assertJsonPath('client',null)->assertJsonPath('context.coach_id',null);
        // Discard Sanctum's fixture guard: the remaining requests use the new real token.
        $this->app['auth']->forgetGuards();$this->withToken($login->json('token'));
        $owner=UserApp::where('email',$data['email'])->firstOrFail();
        $this->getJson(self::ROOT.'/me')->assertOk()->assertJsonPath('user.id',$owner->id)->assertJsonPath('access.access_state','no_coach');
        $discovery=$this->postJson(self::ROOT.'/workout-generator/recommendations',['equipment'=>[],'selected_ids'=>[],'discover_equipment'=>true])->assertOk();
        $this->assertNotEmpty($discovery->json('data.candidates'));
        $this->postJson(self::ROOT.'/workout-generator/prescription',['exercise_id'=>$this->exercise()->id,'equipment'=>[]])->assertUnprocessable();
        $this->postJson(self::ROOT.'/workout-generator/prescription',['exercise_id'=>$this->exercise()->id,'equipment'=>['barbell','rack']])->assertOk();
        $body=$this->body();$execution=$this->start($body);$revision=1;
        foreach($execution['prescription']['content']['sections'] as $section)foreach($section['exercises'] as $exercise)foreach($exercise['sets'] as $set) {
            $op=['operation_id'=>(string)Str::uuid(),'expected_revision'=>0,'status'=>'completed','type'=>'work','load_mode'=>'unknown',
                'actual_reps'=>10,'actual_load'=>null,'load_unit'=>null,'actual_rir'=>'2','performed_at'=>now()->utc()->format('Y-m-d\TH:i:s\Z')];
            $url=self::ROOT.'/workout-executions/'.$execution['uuid'].'/sets/'.$set['uuid'];
            $saved=$this->putJson($url,$op)->assertOk();$revision++;
            $this->putJson($url,$op)->assertOk()->assertExactJson($saved->json());
        }
        config(['workouts.generator_enabled'=>false,'workouts.executions_enabled'=>false]);
        $this->postJson(self::ROOT.'/workout-generator/executions',$body)->assertOk()->assertJsonPath('data.uuid',$execution['uuid']);
        $this->getJson(self::ROOT.'/workout-ownership')->assertOk()->assertJsonPath('data.user_app_id',$owner->id);
        $close=['operation_id'=>(string)Str::uuid(),'expected_execution_revision'=>$revision];
        $url=self::ROOT.'/workout-executions/'.$execution['uuid'].'/complete';$closed=$this->postJson($url,$close)->assertOk();
        $this->postJson($url,$close)->assertOk()->assertExactJson($closed->json());
        $this->getJson(self::ROOT.'/workout-executions/'.$execution['uuid'])->assertOk()->assertJsonPath('data.status','completed')
            ->assertJsonPath('data.summary.work_sets_completed',3)->assertJsonPath('data.summary.sets_without_load',3);
        $this->getJson(self::ROOT.'/workout-progress?exercise_id='.$this->exercise()->id)->assertOk()->assertJsonCount(0,'data.points');
        $this->assertSame(0,Client::count());$this->assertSame(0,DB::table('workout_max_records')->count());
        $this->assertSame(1,WorkoutExecution::where('owner_user_app_id',$owner->id)->count());
    }
    public function test_no_coach_membership_or_individual_permission_is_needed_and_owner_cannot_be_spoofed(): void
    {
        $settings=$this->getJson(self::ROOT.'/workout-generator/settings')->assertOk()->assertJsonPath('data.access_source','platform')->assertJsonPath('data.generator_enabled',true);
        $body=$this->body();$body['owner_user_app_id']=999;$body['coach_id']=999;$body['client_id']=999;
        $execution=$this->start($body);$row=WorkoutExecution::where('uuid',$execution['uuid'])->firstOrFail();
        $this->assertSame($this->athlete->id,(int)$row->owner_user_app_id);$this->assertNull($row->client_id);$this->assertNull($row->coach_id);
        $this->assertSame('personal_gym',$execution['ownership_scope']);$this->assertSame(0,Client::count());
        $this->patchJson(self::ROOT.'/workout-generator/settings',['owner_user_app_id'=>999,'generator_enabled'=>false,'blocked_exercise_codes'=>[],'blocked_demands'=>[]])
            ->assertOk()->assertJsonPath('data.generator_enabled',true);
        $this->assertSame($this->athlete->id,(int)DB::table('athlete_gym_preferences')->value('user_app_id'));
    }
    public function test_offline_migration_proof_is_issued_only_for_the_authenticated_owner():void {
        $execution=$this->start();
        DB::table('workout_ownership_claims')->insert(['user_app_id'=>$this->athlete->id,'resource_table'=>'workout_executions','resource_id'=>1,'legacy_client_id'=>50,'legacy_coach_id'=>70,'evidence'=>'test','created_at'=>now()]);
        $this->getJson(self::ROOT.'/workout-ownership?user_app_id=999')->assertOk()
            ->assertJsonPath('data.user_app_id',$this->athlete->id)->assertJsonPath('data.personal.0',$execution['uuid'])
            ->assertJsonPath('data.contexts.0',[50,70])->assertJsonPath('data.current_context',null)->assertJsonCount(0,'data.assignments');
        Sanctum::actingAs(UserApp::create(['email'=>'other-proof@example.test','is_active'=>true]));
        $this->getJson(self::ROOT.'/workout-ownership')->assertOk()->assertJsonCount(0,'data.personal')->assertJsonCount(0,'data.contexts');
        Sanctum::actingAs(User::factory()->create());$this->getJson(self::ROOT.'/workout-ownership')->assertForbidden();
    }
    public function test_history_exercise_selector_includes_only_own_frozen_private_history():void {
        $execution=$this->start();$row=WorkoutExecution::where('uuid',$execution['uuid'])->firstOrFail();
        $coach=User::factory()->create();$base=$this->exercise();$private=$base->replicate();$private->code='private_history_test';$private->coach_id=$coach->id;$private->names=['es'=>'Nombre actual privado'];$private->save();
        $content=$row->prescription->content;$content['sections'][0]['exercises'][0]['workout_exercise_id']=$private->id;
        $content['sections'][0]['exercises'][0]['name']='Nombre histórico congelado';
        DB::table('workout_execution_prescriptions')->where('workout_execution_id',$row->id)->update(['content'=>json_encode($content),'content_hash'=>hash('sha256',json_encode($content))]);
        $response=$this->getJson(self::ROOT.'/workout-progress/exercises')->assertOk();
        $item=collect($response->json('data'))->firstWhere('id',$private->id);$this->assertSame('Nombre histórico congelado',$item['names']['es']);
        Sanctum::actingAs(UserApp::create(['email'=>'other-history@example.test','is_active'=>true]));
        $other=$this->getJson(self::ROOT.'/workout-progress/exercises')->assertOk();$this->assertNotContains($private->id,array_column($other->json('data'),'id'));
    }
    public function test_personal_history_operations_and_goals_survive_coach_change_and_unlinking(): void
    {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Comercial','is_active'=>false]);
        $this->athlete->update(['client_id'=>$client->id]);$execution=$this->start();[$set,$body,$saved]=$this->save($execution);$saved->assertOk();
        $client->update(['coach_id'=>User::factory()->create()->id]);$this->athlete->update(['client_id'=>null]);
        $this->getJson(self::ROOT.'/workout-executions/'.$execution['uuid'])->assertOk()->assertJsonCount(1,'data.sets');
        $this->putJson(self::ROOT.'/workout-executions/'.$execution['uuid'].'/sets/'.$set,$body)->assertOk()->assertExactJson($saved->json());
        $this->postJson(self::ROOT.'/workout-executions/'.$execution['uuid'].'/complete',['operation_id'=>(string)Str::uuid(),'expected_execution_revision'=>2])->assertOk();
        $this->assertSame(1,DB::table('workout_execution_sets')->count());
    }
    public function test_other_athlete_and_current_coach_cannot_read_or_modify_personal_records(): void
    {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Comercial']);$this->athlete->update(['client_id'=>$client->id]);
        $execution=$this->start();$this->save($execution)[2]->assertOk();$id=$this->exercise()->id;
        $rm=$this->postJson(self::ROOT.'/exercise-maxes',['exercise_id'=>$id,'value'=>'100','unit'=>'kg','measured_at'=>now()->toIso8601String(),'single_rep_confirmed'=>true])->assertCreated()->json('data.id');
        Sanctum::actingAs(UserApp::create(['email'=>'other@example.test','is_active'=>true]));
        $this->getJson(self::ROOT.'/workout-executions/'.$execution['uuid'])->assertNotFound();
        $this->getJson(self::ROOT.'/exercise-maxes?exercise_id='.$id)->assertOk()->assertJsonCount(0,'data.data')->assertJsonPath('basis',null);
        $this->postJson(self::ROOT.'/exercise-maxes',['exercise_id'=>$id,'value'=>'120','unit'=>'kg','measured_at'=>now()->toIso8601String(),'single_rep_confirmed'=>true,'supersedes_id'=>$rm])->assertNotFound();
        Sanctum::actingAs($coach);
        $base='/api/v1/coach/clients/'.$client->id;
        $this->getJson($base.'/workout-executions')->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson($base.'/workout-executions/'.$execution['uuid'])->assertNotFound();
        $this->getJson($base.'/exercise-maxes?exercise_id='.$id)->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson($base.'/workout-progress?exercise_id='.$id)->assertOk()->assertJsonCount(0,'data.points');
    }
    public function test_coach_controls_are_retired_and_old_restrictions_are_not_imported(): void
    {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Comercial']);$this->athlete->update(['client_id'=>$client->id]);
        DB::table('workout_athlete_settings')->insert(['client_id'=>$client->id,'coach_id'=>$coach->id,'generator_enabled'=>false,'blocked_exercise_codes'=>json_encode(['lower_body_back_squat']),'blocked_demands'=>json_encode(['external_load'])]);
        $this->getJson(self::ROOT.'/workout-generator/settings')->assertOk()->assertJsonCount(0,'data.blocked_exercise_codes')->assertJsonPath('data.generator_enabled',true);
        $this->start();Sanctum::actingAs($coach);$base='/api/v1/coach/clients/'.$client->id.'/workout-generator';
        $this->getJson($base.'/settings')->assertGone();$this->putJson($base.'/settings',['generator_enabled'=>false])->assertGone();
        $this->postJson($base.'/preview',[])->assertGone();$this->postJson($base.'/prescription-preview',[])->assertGone();
        $this->assertSame(0,(int)DB::table('workout_athlete_settings')->value('generator_enabled'));
    }
    public function test_feature_shutdown_stops_new_creation_but_not_owned_history_or_replays(): void
    {
        $body=$this->body();$execution=$this->start($body);[$set,$op,$response]=$this->save($execution);$response->assertOk();
        config(['workouts.generator_enabled'=>false,'workouts.executions_enabled'=>false]);
        $this->postJson(self::ROOT.'/workout-generator/executions',$body)->assertOk();
        $this->postJson(self::ROOT.'/workout-generator/executions',$this->body())->assertForbidden();
        $this->putJson(self::ROOT.'/workout-executions/'.$execution['uuid'].'/sets/'.$set,$op)->assertOk()->assertExactJson($response->json());
        $this->getJson(self::ROOT.'/workout-executions/'.$execution['uuid'])->assertOk();
        $this->postJson(self::ROOT.'/workout-executions/'.$execution['uuid'].'/complete',['operation_id'=>(string)Str::uuid(),'expected_execution_revision'=>2])->assertOk();
    }
    public function test_deletion_erases_only_owned_personal_gym_and_its_dependencies(): void
    {
        $execution=$this->start();$this->save($execution)[2]->assertOk();
        $other=UserApp::create(['email'=>'keep@example.test','is_active'=>true]);
        Sanctum::actingAs($other);$otherExecution=$this->start();$this->save($otherExecution)[2]->assertOk();
        Sanctum::actingAs($this->athlete);
        $this->postJson(self::ROOT.'/exercise-maxes',['exercise_id'=>$this->exercise()->id,'value'=>'100','unit'=>'kg','measured_at'=>now()->toIso8601String(),'single_rep_confirmed'=>true])->assertCreated();
        $this->postJson(self::ROOT.'/workout-goals',['request_id'=>(string)Str::uuid(),'exercise_id'=>$this->exercise()->id,'scheduled_for'=>now()->addWeek()->toDateString(),'load'=>'80','unit'=>'kg','reps'=>8,'rir'=>2])->assertCreated();
        $this->postJson(self::ROOT.'/workout-generator/prescription',['exercise_id'=>$this->exercise()->id,'equipment'=>['barbell','rack']])->assertOk();
        $this->patchJson(self::ROOT.'/workout-generator/settings',['blocked_exercise_codes'=>[],'blocked_demands'=>[]])->assertOk();
        $this->athlete->createToken('delete');
        $id=$this->deleteJson(self::ROOT.'/account',['password'=>'Password123!','confirm'=>true])->assertAccepted()->json('request_id');
        $this->assertTrue(app(\App\Services\AthleteAccountDeletion::class)->completeUnlinked($id));
        $this->assertNull($this->athlete->fresh());$this->assertNotNull($other->fresh());
        foreach(['workout_executions','workout_execution_sets','workout_execution_prescriptions','workout_sync_operations','workout_set_revisions'] as $table)$this->assertSame(1,DB::table($table)->count(),$table);
        foreach(['workout_max_records','workout_exercise_goals','workout_load_suggestions','athlete_gym_preferences'] as $table)$this->assertSame(0,DB::table($table)->count(),$table);
        Sanctum::actingAs($other);$this->getJson(self::ROOT.'/workout-executions/'.$otherExecution['uuid'])->assertOk();
    }
    public function test_transition_claims_only_proven_owners_preserving_snapshots_and_operation_replay(): void
    {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Legado']);$this->athlete->update(['client_id'=>$client->id]);
        $body=$this->body();$execution=$this->start($body);[$set,$op,$saved]=$this->save($execution);$saved->assertOk();
        $row=WorkoutExecution::where('uuid',$execution['uuid'])->firstOrFail();
        $snapshot=DB::table('workout_execution_prescriptions')->where('workout_execution_id',$row->id)->first();
        $operation=DB::table('workout_sync_operations')->first();
        DB::table('workout_executions')->where('id',$row->id)->update(['owner_user_app_id'=>null,'data_scope'=>'legacy_coach','client_id'=>$client->id,'coach_id'=>$coach->id]);
        DB::table('workout_sync_operations')->where('id',$operation->id)->update(['client_id'=>$client->id]);
        $unknown=WorkoutExecution::create(['uuid'=>(string)Str::uuid(),'client_execution_id'=>(string)Str::uuid(),'client_id'=>$client->id,'coach_id'=>$coach->id,
            'start_fingerprint'=>hash('sha256','unknown'),'status'=>'in_progress','revision'=>1,'started_at'=>now()]);
        $unknown->prescription()->create(['schema_version'=>2,'content'=>json_decode($snapshot->content,true),'content_hash'=>$snapshot->content_hash,'created_at'=>now()]);
        $transition=app(WorkoutOwnershipTransition::class);$dry=$transition->report();
        $this->assertSame(1,$dry['counts']['workout_executions:personal_gym']);$this->assertSame('legacy_coach',$row->fresh()->data_scope);
        $transition->apply();$this->assertSame($this->athlete->id,(int)$row->fresh()->owner_user_app_id);
        $this->assertSame('legacy_coach',$unknown->fresh()->data_scope);$this->assertSame(1,DB::table('workout_ownership_claims')->count());
        $this->assertSame($snapshot->content,DB::table('workout_execution_prescriptions')->where('id',$snapshot->id)->value('content'));
        $this->assertSame($operation->payload_hash,DB::table('workout_sync_operations')->where('id',$operation->id)->value('payload_hash'));
        $this->assertSame([],$transition->apply()['planned']);
        $this->athlete->update(['client_id'=>null]);
        $this->putJson(self::ROOT.'/workout-executions/'.$execution['uuid'].'/sets/'.$set,$op)->assertOk()->assertExactJson($saved->json());
        $this->postJson(self::ROOT.'/workout-generator/executions',$body)->assertOk()->assertJsonPath('data.uuid',$execution['uuid']);
    }
    public function test_transition_preserves_legacy_goal_fingerprint_and_replays_after_unlinking(): void
    {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Legado']);$this->athlete->update(['client_id'=>$client->id]);
        $data=['request_id'=>(string)Str::uuid(),'exercise_id'=>$this->exercise()->id,'scheduled_for'=>now()->addWeek()->toDateString(),'load'=>'80','unit'=>'kg','reps'=>8,'rir'=>2];
        $id=$this->postJson(self::ROOT.'/workout-goals',$data)->assertCreated()->json('data.id');
        $legacy=['coach_id'=>$coach->id,'workout_exercise_id'=>$data['exercise_id'],'scheduled_for'=>$data['scheduled_for'],
            'load_original'=>'80.00','unit'=>'kg','load_kg'=>'80.000000','reps'=>8,'rir'=>'2.0','notes'=>null,'user_app_id'=>$this->athlete->id,'recorded_by_coach_id'=>null];
        $hash=hash('sha256',json_encode($legacy,JSON_THROW_ON_ERROR));
        DB::table('workout_exercise_goals')->where('id',$id)->update(['data_scope'=>'legacy_coach','owner_user_app_id'=>null,'client_id'=>$client->id,'coach_id'=>$coach->id,'fingerprint'=>$hash]);
        app(WorkoutOwnershipTransition::class)->apply();$this->athlete->update(['client_id'=>null]);
        $this->postJson(self::ROOT.'/workout-goals',$data)->assertOk()->assertJsonPath('data.id',$id);
        $this->assertSame($hash,DB::table('workout_exercise_goals')->where('id',$id)->value('fingerprint'));
        config(['workouts.generator_enabled'=>false]);$this->exercise()->update(['is_active'=>false,'review_status'=>'pending']);
        $this->travel(8)->days();
        $this->postJson(self::ROOT.'/workout-goals',$data)->assertOk()->assertJsonPath('data.id',$id);
        $this->travelBack();
        $data['load']='90';$this->postJson(self::ROOT.'/workout-goals',$data)->assertConflict();
    }
    public function test_transition_does_not_claim_rm_correction_chains_with_mixed_authorship(): void
    {
        $coach=User::factory()->create();$client=Client::create(['coach_id'=>$coach->id,'first_name'=>'Legado']);$this->athlete->update(['client_id'=>$client->id]);
        $data=['exercise_id'=>$this->exercise()->id,'value'=>'100','unit'=>'kg','measured_at'=>now()->toIso8601String(),'single_rep_confirmed'=>true];
        $a=$this->postJson(self::ROOT.'/exercise-maxes',$data)->assertCreated()->json('data.id');$data['supersedes_id']=$a;$data['value']='110';
        $b=$this->postJson(self::ROOT.'/exercise-maxes',$data)->assertCreated()->json('data.id');
        DB::table('workout_max_records')->whereIn('id',[$a,$b])->update(['data_scope'=>'legacy_coach','owner_user_app_id'=>null,'client_id'=>$client->id,'coach_id'=>$coach->id]);
        DB::table('workout_max_records')->where('id',$b)->update(['user_app_id'=>null,'recorded_by_coach_id'=>$coach->id,'source'=>'coach_reported']);
        $report=app(WorkoutOwnershipTransition::class)->apply();
        $this->assertSame([],$report['planned']);$this->assertCount(2,$report['exceptions']);
        $this->assertSame(2,DB::table('workout_max_records')->whereNull('owner_user_app_id')->count());
    }
}
