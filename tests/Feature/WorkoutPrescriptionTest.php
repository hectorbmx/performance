<?php
namespace Tests\Feature;

use App\Http\Middleware\EnsureClientMembershipIsActive;
use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutAthleteSetting;
use App\Models\WorkoutExercise;
use App\Models\WorkoutExecution;
use App\Models\WorkoutMaxRecord;
use App\Services\WorkoutMaxes;
use Database\Seeders\WorkoutExerciseCatalogSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutPrescriptionTest extends TestCase
{
    use \Tests\Concerns\InstallsPersonalWorkoutSchema;
    private UserApp $athlete; private Client $client; private WorkoutExercise $exercise;
    private WorkoutExecution $execution; private array $sets;
    protected function setUp(): void {
        parent::setUp(); $this->withoutMiddleware([\App\Http\Middleware\EnsureCoachApiAccess::class, EnsureClientMembershipIsActive::class, \Illuminate\Routing\Middleware\ThrottleRequests::class]);
        config(['database.connections.prescription_memory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'array', 'workouts.generator_enabled' => true]);
        DB::setDefaultConnection('prescription_memory'); $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['2014_10_12_000000_create_users_table.php','2026_01_05_044105_create_clients_table.php','2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php','2026_01_06_042508_create_training_sessions_table.php',
            '2026_01_06_042534_create_training_sections_table.php','2026_01_06_044345_create_training_assignments_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php','2026_07_28_120200_create_training_section_lifting_rows_table.php',
            '2026_11_13_000001_create_workout_executions_tables.php','2026_11_13_000002_create_workout_execution_set_records.php',
            '2026_11_14_000001_create_workout_exercise_catalog.php','2026_11_14_000002_create_workout_athlete_settings.php',
            '2026_11_14_000003_create_workout_max_records.php','2026_11_14_000004_create_workout_load_suggestions.php','2026_11_15_000001_create_workout_prescription_revisions.php','2026_11_15_000002_create_workout_exercise_goals.php'] as $file) (require database_path('migrations/'.$file))->up();
        $this->installPersonalWorkoutSchema();
        Carbon::setTestNow('2026-11-14 10:00:00'); (new WorkoutExerciseCatalogSeeder)->run();
        $coach = User::factory()->create(); $this->client = Client::create(['coach_id' => $coach->id, 'first_name' => 'Atleta']);
        $this->athlete = UserApp::create(['client_id' => $this->client->id, 'email' => 'max@example.test', 'is_active' => true]);
        $this->exercise = WorkoutExercise::where('code','lower_body_back_squat')->first(); $this->exercise->update(['review_status' => 'reviewed']);
        WorkoutAthleteSetting::create(['client_id' => $this->client->id, 'coach_id' => $coach->id, 'generator_enabled' => true, 'blocked_exercise_codes' => [], 'blocked_demands' => []]);
        $this->execution = WorkoutExecution::create(['uuid' => (string) Str::uuid(), 'client_execution_id' => (string) Str::uuid(),
            'owner_user_app_id'=>$this->athlete->id,'data_scope'=>'personal_gym',
            'client_id' => $this->client->id, 'coach_id' => $coach->id, 'start_fingerprint' => hash('sha256','test'), 'status' => 'in_progress', 'revision' => 1, 'started_at' => now()]);
        $this->sets = [(string) Str::uuid(), (string) Str::uuid()];
        $content = ['title' => 'Sentadilla', 'sections' => [['exercises' => [['uuid' => (string) Str::uuid(), 'name' => 'Back Squat',
            'workout_exercise_id' => $this->exercise->id, 'sets' => array_map(fn ($uuid) => ['uuid' => $uuid], $this->sets)]]]]];
        $this->execution->prescription()->create(['schema_version' => 1, 'content' => $content, 'content_hash' => hash('sha256',json_encode($content)), 'created_at' => now()]);
        Sanctum::actingAs($this->athlete);
    }
    protected function tearDown(): void { Carbon::setTestNow(); DB::purge('prescription_memory'); parent::tearDown(); }
    private function tested(array $overrides = []) {
        return $this->postJson('/api/v1/app/exercise-maxes', array_replace(['exercise_id' => $this->exercise->id, 'value' => '100', 'unit' => 'kg',
            'measured_at' => now()->subDay()->toIso8601String(), 'single_rep_confirmed' => true], $overrides));
    }
    private function prescription(array $overrides = []) {
        return $this->postJson('/api/v1/app/workout-generator/prescription', array_replace(['exercise_id' => $this->exercise->id, 'equipment' => ['barbell','rack']], $overrides));
    }
    private function record(int $index, array $overrides = []) {
        return $this->putJson('/api/v1/app/workout-executions/'.$this->execution->uuid.'/sets/'.$this->sets[$index], array_replace([
            'operation_id' => (string) Str::uuid(), 'expected_revision' => 0, 'status' => 'completed', 'type' => 'work', 'load_mode' => 'total_barbell',
            'actual_reps' => 8, 'actual_load' => '75', 'load_unit' => 'kg', 'actual_rir' => '2', 'performed_at' => '2026-11-14T09:00:00Z',
        ], $overrides));
    }
    public function test_progress_keeps_declared_estimated_and_explicit_future_goals_separate(): void {
        $this->tested()->assertCreated(); $this->record(0)->assertOk();
        $url='/api/v1/app/workout-progress?exercise_id='.$this->exercise->id;
        $response=$this->getJson($url)->assertOk()->assertJsonCount(2,'data.points')->assertJsonCount(0,'data.goals');
        $this->assertSame(['tested','estimated'],array_column($response->json('data.points'),'kind'));
        $body=['request_id'=>(string)Str::uuid(),'exercise_id'=>$this->exercise->id,'scheduled_for'=>'2026-12-01','load'=>'80','unit'=>'kg','reps'=>8,'rir'=>'2'];
        $this->postJson('/api/v1/app/workout-goals',$body)->assertCreated();
        $this->postJson('/api/v1/app/workout-goals',$body)->assertOk();
        $this->postJson('/api/v1/app/workout-goals',array_replace($body,['load'=>'85']))->assertConflict();
        $this->getJson($url)->assertOk()->assertJsonCount(2,'data.points')->assertJsonCount(1,'data.goals')
            ->assertJsonPath('data.goals.0.value_kg','106.666667')->assertJsonPath('data.goals.0.confidence','planned');
        $this->assertSame(1,WorkoutMaxRecord::count());
    }
    public function test_goals_validate_dates_method_limits_and_ownership(): void {
        $body=['request_id'=>(string)Str::uuid(),'exercise_id'=>$this->exercise->id,'scheduled_for'=>'2026-12-01','load'=>'80','unit'=>'kg','reps'=>8,'rir'=>'2'];
        foreach([['scheduled_for'=>'2026-11-13'],['reps'=>10,'rir'=>'3'],['load'=>'0']] as $change)
            $this->postJson('/api/v1/app/workout-goals',array_replace($body,$change))->assertUnprocessable();
        $other=User::factory()->create(); Sanctum::actingAs($other);
        $this->getJson('/api/v1/coach/clients/'.$this->client->id.'/workout-progress?exercise_id='.$this->exercise->id)->assertNotFound();
        $this->getJson('/api/v1/coach/clients/'.$this->client->id.'/workout-executions')->assertNotFound();
        $this->postJson('/api/v1/coach/clients/'.$this->client->id.'/workout-goals',$body)->assertNotFound();
    }
    public function test_progress_discards_stale_derived_revision_and_coach_cannot_inspect_personal_session(): void {
        $this->record(0)->assertOk();
        $this->postJson('/api/v1/app/exercise-maxes/estimate',['exercise_id'=>$this->exercise->id,'execution_uuid'=>$this->execution->uuid,'set_uuid'=>$this->sets[0]])->assertCreated();
        $this->record(0,['expected_revision'=>1,'actual_load'=>'60'])->assertOk();
        $this->getJson('/api/v1/app/workout-progress?exercise_id='.$this->exercise->id)->assertOk()->assertJsonCount(1,'data.points')->assertJsonPath('data.points.0.value_kg','80.000000');
        Sanctum::actingAs(User::find($this->client->coach_id));
        $this->getJson('/api/v1/coach/clients/'.$this->client->id.'/workout-executions')->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson('/api/v1/coach/clients/'.$this->client->id.'/workout-executions/'.$this->execution->uuid)->assertNotFound();
    }
    public function test_no_evidence_means_no_suggestion_and_prescription_remains_editable(): void {
        $this->prescription()->assertOk()->assertJsonPath('data.suggested_load', null)->assertJsonPath('data.prescribed.reps_min', 8)->assertJsonPath('data.editable', true);
    }
    public function test_fresh_reported_rm_gives_rounded_optional_initial_load(): void {
        $this->tested()->assertCreated()->assertJsonPath('data.kind','tested')->assertJsonPath('data.confidence','declared');
        $this->prescription()->assertOk()->assertJsonPath('data.suggested_load.value','75.00')->assertJsonPath('data.adjustment','initial');
        $response = $this->prescription(['unit' => 'lb','increment' => '5.00'])->assertOk();
        $this->assertSame((new WorkoutMaxes)->kg($response->json('data.suggested_load.value'),'lb'), $response->json('data.suggested_load.value_kg'));
    }
    public function test_stale_or_different_exercise_rm_does_not_transfer(): void {
        $this->tested(['measured_at' => now()->subDays(100)->toIso8601String()])->assertCreated();
        $this->prescription()->assertOk()->assertJsonPath('data.suggested_load', null);
        $other = WorkoutExercise::where('code','lower_body_front_squat')->first();
        $other->update(['review_status'=>'reviewed']);
        $this->tested(['exercise_id' => $other->id])->assertCreated();
        $this->prescription()->assertOk()->assertJsonPath('data.suggested_load', null);
    }
    public function test_tested_correction_is_append_only_and_estimated_never_replaces_tested(): void {
        $first = $this->tested()->assertCreated()->json('data.id');
        $this->tested(['value' => '90', 'supersedes_id' => $first])->assertCreated();
        $this->assertSame(2, WorkoutMaxRecord::count()); $this->assertSame('100.000000',WorkoutMaxRecord::find($first)->value_kg);
        $this->record(0)->assertOk();
        $body = ['exercise_id' => $this->exercise->id,'execution_uuid' => $this->execution->uuid,'set_uuid' => $this->sets[0]];
        $this->postJson('/api/v1/app/exercise-maxes/estimate',$body)->assertCreated()->assertJsonPath('data.kind','estimated');
        $this->postJson('/api/v1/app/exercise-maxes/estimate',$body)->assertOk();
        $this->getJson('/api/v1/app/exercise-maxes?exercise_id='.$this->exercise->id)->assertOk()->assertJsonPath('basis.kind','tested')->assertJsonPath('basis.value_kg','90.000000');
        $this->assertSame(3,WorkoutMaxRecord::count());
    }
    public function test_recent_real_history_can_support_estimate_without_inventing_tested_rm(): void {
        $this->record(0)->assertOk();
        $this->prescription()->assertOk()->assertJsonPath('data.basis.kind','estimated')->assertJsonPath('data.basis.value_kg','100.000000')
            ->assertJsonPath('data.adjustment','maintain')->assertJsonPath('data.suggested_load.value','75.00');
        $this->assertSame(0,WorkoutMaxRecord::count());
    }
    public function test_low_reps_or_low_rir_recommends_reduction_never_forces_it(): void {
        $this->tested()->assertCreated(); $this->record(0,['status'=>'failed','actual_reps'=>5,'actual_rir'=>'0'])->assertOk();
        $this->prescription()->assertOk()->assertJsonPath('data.adjustment','reduce')->assertJsonPath('data.suggested_load.value','70.00')->assertJsonPath('data.editable',true);
    }
    public function test_increase_requires_two_recent_work_sets_and_respects_equipment_increment(): void {
        $this->tested()->assertCreated(); $this->record(0,['actual_reps'=>12,'actual_rir'=>'3'])->assertOk();
        $this->prescription()->assertOk()->assertJsonPath('data.adjustment','maintain');
        $this->record(1,['actual_reps'=>12,'actual_rir'=>'3'])->assertOk();
        $this->prescription()->assertOk()->assertJsonPath('data.adjustment','increase')->assertJsonPath('data.suggested_load.value','77.50');
    }
    public function test_reported_pain_or_technique_issue_suppresses_suggestions(): void {
        $this->tested()->assertCreated();
        $this->prescription(['pain_reported'=>true])->assertOk()->assertJsonPath('data.suggested_load',null)->assertJsonPath('data.adjustment','none');
        $this->prescription(['technique_issue'=>true])->assertOk()->assertJsonPath('data.suggested_load',null);
    }
    public function test_estimation_rejects_warmup_unknown_rir_and_zero_repetitions(): void {
        $this->record(0,['type'=>'warmup'])->assertOk();
        $body=['exercise_id'=>$this->exercise->id,'execution_uuid'=>$this->execution->uuid,'set_uuid'=>$this->sets[0]];
        $this->postJson('/api/v1/app/exercise-maxes/estimate',$body)->assertUnprocessable();
        $this->record(1,['actual_rir'=>null])->assertOk(); $body['set_uuid']=$this->sets[1];
        $this->postJson('/api/v1/app/exercise-maxes/estimate',$body)->assertUnprocessable();
    }
    public function test_edited_source_invalidates_old_estimate_without_erasing_it(): void {
        $this->record(0)->assertOk();
        $body=['exercise_id'=>$this->exercise->id,'execution_uuid'=>$this->execution->uuid,'set_uuid'=>$this->sets[0]];
        $this->postJson('/api/v1/app/exercise-maxes/estimate',$body)->assertCreated();
        $this->record(0,['expected_revision'=>1,'actual_rir'=>null])->assertOk();
        $this->prescription()->assertOk()->assertJsonPath('data.suggested_load',null); $this->assertSame(1,WorkoutMaxRecord::count());
    }
    public function test_suggestions_including_no_load_keep_rule_and_basis_trace_without_forcing_acceptance(): void {
        $response = $this->prescription()->assertOk();
        $id = $response->json('data.suggestion_id');
        $row = DB::table('workout_load_suggestions')->where('uuid',$id)->first();
        $this->assertNotNull($row); $this->assertSame(config('workout_prescription.version'),$row->rule_version);
        $this->assertNull(json_decode($row->result,true)['suggested_load']);
        $this->assertSame(0,DB::table('workout_execution_sets')->count());
    }
    public function test_non_barbell_catalog_cannot_be_recorded_as_barbell_or_generate_rm(): void {
        $body=$this->generatedBody();
        $bodyweight=WorkoutExercise::where('code','lower_body_bodyweight_squat')->first();$bodyweight->update(['review_status'=>'reviewed']);
        $body['exercises'][0]['exercise_id']=$bodyweight->id;$body['exercises'][0]['chosen_load']=null;
        $generated=$this->postJson('/api/v1/app/workout-generator/executions',$body)->assertCreated()->json('data');
        $set=$generated['prescription']['content']['sections'][0]['exercises'][0]['sets'][0]['uuid'];
        $record=['operation_id'=>(string)Str::uuid(),'expected_revision'=>0,'status'=>'completed','type'=>'work','load_mode'=>'total_barbell',
            'actual_reps'=>8,'actual_load'=>'75','load_unit'=>'kg','actual_rir'=>'2','performed_at'=>'2026-11-14T09:00:00Z'];
        $url='/api/v1/app/workout-executions/'.$generated['uuid'].'/sets/'.$set;
        $this->putJson($url,$record)->assertUnprocessable();
        $this->putJson($url,array_replace($record,['load_mode'=>'unknown','actual_load'=>null,'load_unit'=>null]))->assertOk();
        $this->postJson('/api/v1/app/exercise-maxes/estimate',['exercise_id'=>$bodyweight->id,'execution_uuid'=>$generated['uuid'],'set_uuid'=>$set])->assertUnprocessable();
        $this->assertSame(0,WorkoutMaxRecord::count());
    }
    public function test_feature_shutdown_blocks_new_generation_but_preserves_records_completion_and_history(): void {
        $body=$this->generatedBody();$generated=$this->postJson('/api/v1/app/workout-generator/executions',$body)->assertCreated()->json('data');
        config(['workouts.executions_enabled'=>false,'workouts.generator_enabled'=>false]);
        $body['client_execution_id']=(string)Str::uuid();$this->postJson('/api/v1/app/workout-generator/executions',$body)->assertForbidden();
        $set=$generated['prescription']['content']['sections'][0]['exercises'][0]['sets'][0]['uuid'];
        $record=['operation_id'=>(string)Str::uuid(),'expected_revision'=>0,'status'=>'completed','type'=>'work','load_mode'=>'total_barbell',
            'actual_reps'=>8,'actual_load'=>'75','load_unit'=>'kg','actual_rir'=>'2','performed_at'=>'2026-11-14T09:00:00Z'];
        $this->putJson('/api/v1/app/workout-executions/'.$generated['uuid'].'/sets/'.$set,$record)->assertOk();
        $this->postJson('/api/v1/app/workout-executions/'.$generated['uuid'].'/complete',['operation_id'=>(string)Str::uuid(),'expected_execution_revision'=>2])->assertOk();
        $this->getJson('/api/v1/app/workout-executions/'.$generated['uuid'])->assertOk()->assertJsonPath('data.summary.sets_unrecorded',2)->assertJsonPath('data.summary.series_adherence_percent',33.3);
    }
    public function test_additive_schema_roundtrip_is_isolated_and_preserves_legacy_rows(): void {
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $files=['2026_11_13_000001_create_workout_executions_tables.php','2026_11_13_000002_create_workout_execution_set_records.php',
            '2026_11_14_000001_create_workout_exercise_catalog.php','2026_11_14_000002_create_workout_athlete_settings.php',
            '2026_11_14_000003_create_workout_max_records.php','2026_11_14_000004_create_workout_load_suggestions.php',
            '2026_11_15_000001_create_workout_prescription_revisions.php','2026_11_15_000002_create_workout_exercise_goals.php'];
        foreach(array_reverse($files) as $file)(require database_path('migrations/'.$file))->down();
        $this->assertSame(1,Client::count());$this->assertSame(1,UserApp::count());
        foreach($files as $file)(require database_path('migrations/'.$file))->up();
        $this->assertSame(0,WorkoutExecution::count());$this->assertSame(1,Client::count());
    }
    private function generatedBody(): array {
        config(['workouts.executions_enabled'=>true]);
        $access=\Mockery::mock(\App\Services\ClientMembershipAccessService::class);
        $access->shouldReceive('forUserApp')->andReturn(new \App\Services\ClientMembershipAccessResult('active',true,null));
        $this->app->instance(\App\Services\ClientMembershipAccessService::class,$access);
        return ['client_execution_id'=>(string)Str::uuid(),'title'=>'Mi rutina privada','equipment'=>['barbell','rack'],'acknowledge_short_session'=>true,
            'exercises'=>[['exercise_id'=>$this->exercise->id,'sets'=>3,'reps_min'=>8,'reps_max'=>12,'target_rir'=>2,'rest_seconds'=>90,
                'chosen_load'=>'70.00','unit'=>'kg','notes'=>'Mi ajuste','suggestion_id'=>null]]];
    }
    public function test_generated_session_keeps_recommendation_and_user_prescription_separate(): void {
        $body=$this->generatedBody(); $this->tested()->assertCreated();
        $suggestion=$this->prescription()->assertOk()->json('data.suggestion_id'); $body['exercises'][0]['suggestion_id']=$suggestion;
        $response=$this->postJson('/api/v1/app/workout-generator/executions',$body)->assertCreated();
        $this->assertSame('athlete_generated',$response->json('data.prescription.content.source'));
        $exercise=$response->json('data.prescription.content.sections.0.exercises.0');
        $this->assertSame('70.00',$exercise['sets'][0]['prescribed']['load']['value']);
        $this->assertSame('75.00',$exercise['sets'][0]['recommended']['suggested_load']['value']);
        $this->assertSame(3,count($exercise['sets'])); $this->assertSame(0,DB::table('workout_execution_sets')->count());
        $this->assertSame(0,DB::table('training_sessions')->count());
        $this->assertNull(WorkoutExecution::where('uuid',$response->json('data.uuid'))->first()->training_assignment_id);
    }
    public function test_generated_start_replay_does_not_duplicate_or_require_new_start_permission(): void {
        $body=$this->generatedBody(); $first=$this->postJson('/api/v1/app/workout-generator/executions',$body)->assertCreated()->json();
        config(['workouts.executions_enabled'=>false,'workouts.generator_enabled'=>false]);
        $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertOk()->assertExactJson($first);
        $this->assertSame(2,WorkoutExecution::count()); // fixture + exactly one generated execution
        $body['title']='Otro contenido'; $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertStatus(409);
    }
    public function test_generation_rechecks_review_restrictions_and_minimum_exercises(): void {
        $body=$this->generatedBody();
        WorkoutExercise::where('code','lower_body_front_squat')->first()->update(['review_status'=>'reviewed']);
        WorkoutExercise::where('code','lower_body_romanian_deadlift')->first()->update(['review_status'=>'reviewed']);
        $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertUnprocessable();
        $second=$body['exercises'][0]; $second['exercise_id']=WorkoutExercise::where('code','lower_body_front_squat')->value('id');
        $third=$second; $third['exercise_id']=WorkoutExercise::where('code','lower_body_romanian_deadlift')->value('id');
        $body['exercises'][]=$second; $body['exercises'][]=$third;
        \App\Models\AthleteGymPreference::updateOrCreate(['user_app_id'=>$this->athlete->id],['blocked_exercise_codes'=>[$this->exercise->code],'blocked_demands'=>[]]);
        $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertUnprocessable();
        \App\Models\AthleteGymPreference::where('user_app_id',$this->athlete->id)->update(['blocked_exercise_codes'=>[]]);
        $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertCreated();
    }
    public function test_generated_session_does_not_accept_foreign_suggestion_or_unreviewed_exercise(): void {
        $body=$this->generatedBody(); $body['exercises'][0]['suggestion_id']=(string)Str::uuid();
        $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertUnprocessable();
        $body['exercises'][0]['suggestion_id']=null; $this->exercise->update(['review_status'=>'pending']);
        $this->postJson('/api/v1/app/workout-generator/executions',$body)->assertConflict();
    }

    public function test_next_exercise_substitution_keeps_original_and_recorded_sets():void {
        $body=$this->generatedBody();
        foreach(['lower_body_front_squat','lower_body_romanian_deadlift','lower_body_bodyweight_squat'] as $code)WorkoutExercise::where('code',$code)->first()->update(['review_status'=>'reviewed']);
        $front=$body['exercises'][0];$front['exercise_id']=WorkoutExercise::where('code','lower_body_front_squat')->value('id');
        $rdl=$front;$rdl['exercise_id']=WorkoutExercise::where('code','lower_body_romanian_deadlift')->value('id');
        $body['exercises'][]=$front;$body['exercises'][]=$rdl;
        $generated=$this->postJson('/api/v1/app/workout-generator/executions',$body)->assertCreated()->json('data');
        $first=$generated['prescription']['content']['sections'][0]['exercises'][0];
        $target=$generated['prescription']['content']['sections'][0]['exercises'][1];
        $setBody=['operation_id'=>(string)Str::uuid(),'expected_revision'=>0,'status'=>'completed','type'=>'work','load_mode'=>'total_barbell',
            'actual_reps'=>8,'actual_load'=>'75','load_unit'=>'kg','actual_rir'=>'2','performed_at'=>'2026-11-14T09:00:00Z'];
        $this->putJson('/api/v1/app/workout-executions/'.$generated['uuid'].'/sets/'.$first['sets'][0]['uuid'],$setBody)->assertOk();
        $replacement=$front;$replacement['exercise_id']=WorkoutExercise::where('code','lower_body_bodyweight_squat')->value('id');$replacement['chosen_load']=null;
        $replace=['operation_id'=>(string)Str::uuid(),'expected_execution_revision'=>2,'target_exercise_uuid'=>$target['uuid'],'replacement'=>$replacement];
        $url='/api/v1/app/workout-executions/'.$generated['uuid'].'/replace-exercise';
        $response=$this->postJson($url,$replace)->assertOk()->assertJsonPath('data.revision',3)->json();
        $this->postJson($url,$replace)->assertOk()->assertExactJson($response);
        $current=$response['data']['prescription']['content']['sections'][0]['exercises'];
        $this->assertSame($first['sets'][0]['uuid'],$current[0]['sets'][0]['uuid']);
        $this->assertNotSame($target['uuid'],$current[1]['uuid']);
        $stored=WorkoutExecution::where('uuid',$generated['uuid'])->first();
        $this->assertSame($target['uuid'],$stored->prescription->content['sections'][0]['exercises'][1]['uuid']);
        $this->assertSame(1,DB::table('workout_prescription_revisions')->where('workout_execution_id',$stored->id)->count());
        $this->assertSame(1,DB::table('workout_execution_sets')->where('workout_execution_id',$stored->id)->count());
        $replace['operation_id']=(string)Str::uuid();$replace['expected_execution_revision']=3;$replace['target_exercise_uuid']=$first['uuid'];
        $this->postJson($url,$replace)->assertUnprocessable();
        $newSet=$setBody;$newSet['operation_id']=(string)Str::uuid();$newSet['load_mode']='unknown';$newSet['actual_load']=null;$newSet['load_unit']=null;
        $this->putJson('/api/v1/app/workout-executions/'.$generated['uuid'].'/sets/'.$current[1]['sets'][0]['uuid'],$newSet)->assertOk();
        $this->postJson('/api/v1/app/workout-executions/'.$generated['uuid'].'/complete',['operation_id'=>(string)Str::uuid(),'expected_execution_revision'=>4])->assertOk();
    }

    public function test_invalid_rm_profile_and_identity_are_rejected(): void {
        $this->tested(['value'=>'0'])->assertUnprocessable(); $this->tested(['unit'=>'oz'])->assertUnprocessable();
        $this->tested(['measured_at'=>now()->addDay()->toIso8601String()])->assertUnprocessable();
        $this->tested(['single_rep_confirmed'=>false])->assertUnprocessable();
        $this->prescription(['reps_min'=>15,'reps_max'=>8])->assertUnprocessable();
        $this->prescription(['increment'=>'0.001'])->assertUnprocessable();
        $otherClient=Client::create(['coach_id'=>$this->client->coach_id,'first_name'=>'Otro']);
        Sanctum::actingAs(UserApp::create(['client_id'=>$otherClient->id,'email'=>'other-max@example.test','is_active'=>true]));
        $body=['exercise_id'=>$this->exercise->id,'execution_uuid'=>$this->execution->uuid,'set_uuid'=>$this->sets[0]];
        $this->postJson('/api/v1/app/exercise-maxes/estimate',$body)->assertNotFound();
    }
}
