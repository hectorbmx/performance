<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TrainingAssignment;
use App\Models\TrainingSection;
use App\Models\TrainingSectionExerciseBlock;
use App\Models\TrainingSectionLiftingRow;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserApp;
use App\Models\WorkoutExecution;
use App\Models\WorkoutExecutionPrescription;
use App\Services\ClientMembershipAccessResult;
use App\Services\ClientMembershipAccessService;
use App\Services\WorkoutPrescriptionSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class WorkoutExecutionApiTest extends TestCase
{
    use \Tests\Concerns\InstallsPersonalWorkoutSchema;
    private UserApp $athlete;
    private TrainingAssignment $assignment;
    private TrainingSectionLiftingRow $row;
    private const URL = '/api/v1/app/workout-executions';
    private const MIGRATION = '2026_11_13_000001_create_workout_executions_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise authorization, not the shared API rate-limit bucket.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['database.connections.workouts_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array', 'workouts.executions_enabled' => true]);
        DB::setDefaultConnection('workouts_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_01_06_042508_create_training_sessions_table.php',
            '2026_01_06_042534_create_training_sections_table.php',
            '2026_01_06_044345_create_training_assignments_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php',
            '2026_07_28_120200_create_training_section_lifting_rows_table.php',
            self::MIGRATION,
            '2026_11_13_000002_create_workout_execution_set_records.php',
            '2026_11_15_000001_create_workout_prescription_revisions.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->installPersonalWorkoutSchema();
        $coach = User::factory()->create();
        $client = Client::create(['coach_id' => $coach->id, 'first_name' => 'Atleta', 'is_active' => true]);
        $this->athlete = UserApp::create(['client_id' => $client->id, 'email' => 'athlete@example.test', 'is_active' => true]);
        $training = TrainingSession::create([
            'coach_id' => $coach->id, 'title' => 'Fuerza original', 'scheduled_at' => '2026-11-13',
            'level' => 'intermediate', 'goal' => 'strength', 'type' => 'weightlifting', 'visibility' => 'assigned',
        ]);
        $section = TrainingSection::create(['training_session_id' => $training->id, 'name' => 'Piernas', 'order' => 1]);
        $block = TrainingSectionExerciseBlock::create(['training_section_id' => $section->id, 'exercise_name' => 'Back Squat', 'order' => 1]);
        $this->row = TrainingSectionLiftingRow::create(['exercise_block_id' => $block->id, 'sets' => 3, 'reps' => 10, 'percentage' => 75, 'rest_seconds' => 90, 'order' => 1]);
        $this->assignment = TrainingAssignment::create(['client_id' => $client->id, 'training_session_id' => $training->id, 'status' => 'scheduled']);
        $this->membership(true);
        Sanctum::actingAs($this->athlete);
    }

    protected function tearDown(): void
    {
        DB::purge('workouts_memory');
        parent::tearDown();
    }

    private function membership(bool $allowed): void
    {
        $service = Mockery::mock(ClientMembershipAccessService::class);
        $service->shouldReceive('forUserApp')->andReturn(new ClientMembershipAccessResult(
            $allowed ? 'active' : 'expired', $allowed, null, $allowed ? null : 'Membresía vencida.'
        ));
        $this->app->instance(ClientMembershipAccessService::class, $service);
    }

    private function start(?string $uuid = null): array
    {
        return $this->postJson(self::URL, [
            'client_execution_id' => $uuid ?? (string) Str::uuid(),
            'training_assignment_id' => $this->assignment->id,
        ])->assertCreated()->json('data');
    }

    public function test_snapshot_is_complete_stable_and_survives_template_deletion(): void
    {
        $execution = $this->start();
        $snapshot = $execution['prescription'];
        $this->assertCount(3, $snapshot['content']['sections'][0]['exercises'][0]['sets']);
        $set = $snapshot['content']['sections'][0]['exercises'][0]['sets'][0];
        $this->assertSame(10, $set['prescribed']['reps_min']);
        $this->assertNull($set['prescribed']['load']);
        $this->assertNull($set['recommended']);
        $this->assertTrue(Str::isUuid($set['uuid']));
        $this->row->update(['reps' => 5]);
        $this->assignment->trainingSession->update(['title' => 'Nueva plantilla']);
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk()->assertJsonPath('data.prescription', $snapshot);
        $this->assignment->trainingSession->forceDelete();
        $this->assertNull(WorkoutExecution::first()->training_session_id);
        $this->assertNull(WorkoutExecution::first()->training_assignment_id);
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk()->assertJsonPath('data.prescription', $snapshot);
        // An original start retry remains valid even when its source is gone.
        $this->postJson(self::URL, ['client_execution_id' => $execution['client_execution_id'], 'training_assignment_id' => $this->assignment->id])
            ->assertOk()->assertJsonPath('data.uuid', $execution['uuid']);
    }

    public function test_idempotent_start_and_independent_same_day_executions(): void
    {
        $uuid = (string) Str::uuid();
        $first = $this->start($uuid);
        $this->postJson(self::URL, ['client_execution_id' => strtoupper($uuid), 'training_assignment_id' => $this->assignment->id])
            ->assertOk()->assertJsonPath('data', $first);
        $second = $this->start();
        $this->assertNotSame($first['uuid'], $second['uuid']);
        $this->assertSame(2, WorkoutExecution::count());
        $this->assertSame(2, WorkoutExecutionPrescription::count());
        $this->getJson(self::URL.'?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_reusing_start_uuid_for_another_assignment_is_conflict(): void
    {
        $execution = $this->start();
        $this->postJson(self::URL, ['client_execution_id' => $execution['client_execution_id'], 'training_assignment_id' => 9999])->assertStatus(409);
        $this->assertSame(1, WorkoutExecution::count());
    }

    public function test_expired_membership_blocks_new_start_but_preserves_history_and_retry(): void
    {
        $execution = $this->start();
        $this->membership(false);
        $this->postJson(self::URL, ['client_execution_id' => (string) Str::uuid(), 'training_assignment_id' => $this->assignment->id])->assertForbidden();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk();
        $this->postJson(self::URL, ['client_execution_id' => $execution['client_execution_id'], 'training_assignment_id' => $this->assignment->id])->assertOk();
    }

    public function test_disabling_flag_blocks_only_new_starts(): void
    {
        $execution = $this->start();
        config(['workouts.executions_enabled' => false]);
        $this->postJson(self::URL, ['client_execution_id' => (string) Str::uuid(), 'training_assignment_id' => $this->assignment->id])->assertForbidden();
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk();
        $this->postJson(self::URL, ['client_execution_id' => $execution['client_execution_id'], 'training_assignment_id' => $this->assignment->id])->assertOk();
    }

    public function test_other_athlete_cannot_read_or_start_foreign_assignment(): void
    {
        $execution = $this->start();
        $otherClient = Client::create(['coach_id' => $this->athlete->client->coach_id, 'first_name' => 'Otro']);
        $other = UserApp::create(['client_id' => $otherClient->id, 'email' => 'other@example.test', 'is_active' => true]);
        Sanctum::actingAs($other);
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertNotFound();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson(self::URL, ['client_execution_id' => (string) Str::uuid(), 'training_assignment_id' => $this->assignment->id])->assertNotFound();
    }

    public function test_coach_change_revokes_access_to_old_execution(): void
    {
        $execution = $this->start();
        $this->athlete->client->update(['coach_id' => User::factory()->create()->id]);
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertNotFound();
        $this->postJson(self::URL, ['client_execution_id' => $execution['client_execution_id'], 'training_assignment_id' => $this->assignment->id])->assertNotFound();
    }

    public function test_inactive_athlete_and_coach_identity_are_rejected(): void
    {
        $execution = $this->start();
        $this->athlete->update(['is_active' => false]);
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_invalid_requests_and_cancelled_assignments_create_nothing(): void
    {
        $this->postJson(self::URL, ['client_execution_id' => 'invalid'])->assertUnprocessable();
        $this->assignment->update(['status' => 'cancelled']);
        $this->postJson(self::URL, ['client_execution_id' => (string) Str::uuid(), 'training_assignment_id' => $this->assignment->id])->assertUnprocessable();
        $this->assertSame(0, WorkoutExecution::count());
    }

    public function test_snapshot_failure_rolls_back_execution(): void
    {
        $service = Mockery::mock(WorkoutPrescriptionSnapshot::class);
        $service->shouldReceive('capture')->once()->andThrow(new \RuntimeException('capture failed'));
        $this->app->instance(WorkoutPrescriptionSnapshot::class, $service);
        $this->postJson(self::URL, ['client_execution_id' => (string) Str::uuid(), 'training_assignment_id' => $this->assignment->id])->assertStatus(500);
        $this->assertSame(0, WorkoutExecution::count());
    }

    public function test_prescription_insert_failure_rolls_back_and_can_retry(): void
    {
        DB::unprepared("CREATE TRIGGER fail_workout_snapshot BEFORE INSERT ON workout_execution_prescriptions BEGIN SELECT RAISE(ABORT, 'snapshot insert failed'); END");
        $uuid = (string) Str::uuid();
        $body = ['client_execution_id' => $uuid, 'training_assignment_id' => $this->assignment->id];
        $this->postJson(self::URL, $body)->assertStatus(500);
        $this->assertSame(0, WorkoutExecution::count());
        $this->assertSame(0, WorkoutExecutionPrescription::count());
        DB::unprepared('DROP TRIGGER fail_workout_snapshot');
        $this->postJson(self::URL, $body)->assertCreated();
    }

    public function test_prescription_model_rejects_mutation(): void
    {
        $this->start();
        $prescription = WorkoutExecutionPrescription::first();
        $this->expectException(\LogicException::class);
        $prescription->update(['content' => ['title' => 'Changed']]);
    }

    public function test_assignment_from_another_coach_is_not_startable(): void
    {
        $this->assignment->trainingSession->update(['coach_id' => User::factory()->create()->id]);
        $this->postJson(self::URL, ['client_execution_id' => (string) Str::uuid(), 'training_assignment_id' => $this->assignment->id])->assertNotFound();
        $this->assertSame(0, WorkoutExecution::count());
    }

    public function test_inactive_client_cannot_read_history(): void
    {
        $this->start();
        $this->athlete->client->update(['is_active' => false]);
        $this->getJson(self::URL)->assertOk()->assertJsonCount(0,'data');
    }
    public function test_coach_keeps_assigned_history_after_verifiable_legacy_classification(): void
    {
        $execution=$this->start();$row=WorkoutExecution::where('uuid',$execution['uuid'])->firstOrFail();
        $this->assertSame('coach_assigned',$row->data_scope);
        DB::table('workout_executions')->where('id',$row->id)->update(['data_scope'=>'legacy_coach']);
        $report=app(\App\Services\WorkoutOwnershipTransition::class)->apply();
        $this->assertSame(1,$report['counts']['workout_executions:coach_assigned']);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureCoachApiAccess::class);
        Sanctum::actingAs(User::find($this->athlete->client->coach_id));
        $base='/api/v1/coach/clients/'.$this->athlete->client_id.'/workout-executions';
        $this->getJson($base)->assertOk()->assertJsonCount(1,'data.data');
        $this->getJson($base.'/'.$execution['uuid'])->assertOk()->assertJsonPath('data.ownership_scope','coach_assigned');
    }

    public function test_prescription_model_rejects_deletion(): void
    {
        $this->start();
        $this->expectException(\LogicException::class);
        WorkoutExecutionPrescription::first()->delete();
    }

    private function setBody(array $overrides = []): array
    {
        return array_replace([
            'operation_id' => (string) Str::uuid(), 'expected_revision' => 0,
            'status' => 'completed', 'type' => 'work', 'load_mode' => 'total_barbell',
            'actual_reps' => 10, 'actual_load' => '75.00', 'load_unit' => 'kg',
            'actual_rir' => '2.0', 'notes' => null, 'performed_at' => '2026-11-13T10:00:00-06:00',
        ], $overrides);
    }

    private function setUrl(array $execution, int $index = 0): string
    {
        return self::URL.'/'.$execution['uuid'].'/sets/'.$execution['prescription']['content']['sections'][0]['exercises'][0]['sets'][$index]['uuid'];
    }

    public function test_records_explicit_values_and_reads_them_without_changing_prescription(): void
    {
        $execution = $this->start();
        $body = $this->setBody();
        $url = $this->setUrl($execution);
        $this->putJson($url, $body)->assertOk()
            ->assertJsonPath('data.set.actual_load', '75.00')
            ->assertJsonPath('data.set.load_kg', '75.000000')
            ->assertJsonPath('data.set.actual_rir', '2.0')
            ->assertJsonPath('data.set.performed_at', '2026-11-13T16:00:00+00:00')
            ->assertJsonPath('data.set.revision', 1)->assertJsonPath('data.execution_revision', 2);
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk()->assertJsonCount(1, 'data.sets')
            ->assertJsonPath('data.prescription', $execution['prescription']);
        $this->getJson($url.'/revisions')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.before', null)->assertJsonPath('data.0.after.actual_reps', 10);
    }

    public function test_pounds_are_converted_exactly_and_original_unit_is_kept(): void
    {
        $execution = $this->start();
        $this->putJson($this->setUrl($execution), $this->setBody(['actual_load' => '100', 'load_unit' => 'lb']))
            ->assertOk()->assertJsonPath('data.set.actual_load', '100.00')
            ->assertJsonPath('data.set.load_unit', 'lb')->assertJsonPath('data.set.load_kg', '45.359237');
        $this->putJson($this->setUrl($execution, 1), $this->setBody(['actual_load' => '22.5', 'load_unit' => 'lb']))
            ->assertOk()->assertJsonPath('data.set.load_kg', '10.205828');
    }

    public function test_absent_load_and_rir_are_not_replaced_with_zero(): void
    {
        $execution = $this->start();
        $this->putJson($this->setUrl($execution), $this->setBody([
            'load_mode' => 'unknown', 'actual_load' => null, 'load_unit' => null, 'actual_rir' => null,
        ]))->assertOk()->assertJsonPath('data.set.load_kg', null)->assertJsonPath('data.set.actual_load', null)
            ->assertJsonPath('data.set.actual_rir', null);
        $this->putJson($this->setUrl($execution, 1), $this->setBody(['actual_load' => '0', 'actual_rir' => '0']))
            ->assertOk()->assertJsonPath('data.set.load_kg', '0.000000')->assertJsonPath('data.set.actual_rir', '0.0');
    }

    public function test_correcting_set_keeps_before_after_and_replay_is_stable(): void
    {
        $execution = $this->start();
        $url = $this->setUrl($execution);
        $body = $this->setBody();
        $first = $this->putJson($url, $body)->assertOk()->json();
        $this->putJson($url, $this->setBody(['expected_revision' => 1, 'actual_reps' => 9, 'actual_load' => '72.50']))
            ->assertOk()->assertJsonPath('data.set.revision', 2)->assertJsonPath('data.execution_revision', 3);
        $this->putJson($url, $body)->assertOk()->assertExactJson($first);
        $this->assertSame(1, DB::table('workout_execution_sets')->count());
        $this->assertSame(2, DB::table('workout_sync_operations')->count());
        $this->getJson($url.'/revisions')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.before.actual_load', '75.00')->assertJsonPath('data.1.after.actual_load', '72.50');
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk()->assertJsonPath('data.sets.0.revision', 2);
    }

    public function test_stale_revision_returns_remote_values_without_overwriting(): void
    {
        $execution = $this->start();
        $url = $this->setUrl($execution);
        $this->putJson($url, $this->setBody())->assertOk();
        $this->putJson($url, $this->setBody(['actual_reps' => 5]))->assertStatus(409)
            ->assertJsonPath('code', 'revision_conflict')->assertJsonPath('data.current_revision', 1)
            ->assertJsonPath('data.current.actual_reps', 10);
        $this->assertSame(1, DB::table('workout_set_revisions')->count());
        $this->assertSame(1, DB::table('workout_sync_operations')->count());
    }

    public function test_operation_id_cannot_be_reused_for_different_body_or_set(): void
    {
        $execution = $this->start();
        $body = $this->setBody();
        $url = $this->setUrl($execution);
        $this->putJson($url, $body)->assertOk();
        $this->putJson($url, array_replace($body, ['actual_reps' => 9]))->assertStatus(409)->assertJsonPath('code', 'operation_reused');
        $this->putJson($this->setUrl($execution, 1), $body)->assertStatus(409)->assertJsonPath('code', 'operation_reused');
        $this->assertSame(1, DB::table('workout_execution_sets')->count());
    }

    public function test_set_uuid_must_belong_to_snapshot_even_after_source_changes(): void
    {
        $execution = $this->start();
        $second = $this->start();
        $otherSet = $second['prescription']['content']['sections'][0]['exercises'][0]['sets'][0]['uuid'];
        $this->putJson(self::URL.'/'.$execution['uuid'].'/sets/'.$otherSet, $this->setBody())->assertNotFound();
        $this->assignment->trainingSession->forceDelete();
        $this->putJson($this->setUrl($execution), $this->setBody())->assertOk();
    }

    public function test_other_athletes_cannot_write_or_read_set_history(): void
    {
        $execution = $this->start();
        $url = $this->setUrl($execution);
        $this->putJson($url, $this->setBody())->assertOk();
        $client = Client::create(['coach_id' => $this->athlete->client->coach_id, 'first_name' => 'Otro']);
        Sanctum::actingAs(UserApp::create(['client_id' => $client->id, 'email' => 'other-sets@example.test', 'is_active' => true]));
        $this->putJson($url, $this->setBody(['expected_revision' => 1]))->assertNotFound();
        $this->getJson($url.'/revisions')->assertNotFound();
    }

    public function test_expiry_and_flag_allow_existing_session_but_revocation_blocks_it(): void
    {
        $execution = $this->start();
        $url = $this->setUrl($execution);
        $this->membership(false);
        config(['workouts.executions_enabled' => false]);
        $body = $this->setBody();
        $this->putJson($url, $body)->assertOk();
        $this->athlete->client->update(['coach_id' => User::factory()->create()->id]);
        $this->putJson($url, $body)->assertNotFound();
        $this->getJson($url.'/revisions')->assertNotFound();
    }

    public function test_closed_execution_rejects_new_writes_but_returns_original_replay(): void
    {
        $execution = $this->start();
        $body = $this->setBody();
        $url = $this->setUrl($execution);
        $first = $this->putJson($url, $body)->assertOk()->json();
        WorkoutExecution::first()->update(['status' => 'completed']);
        $this->putJson($url, $this->setBody(['expected_revision' => 1]))->assertUnprocessable();
        $this->putJson($url, $body)->assertOk()->assertExactJson($first);
    }

    public function test_failed_and_skipped_sets_do_not_fabricate_repetitions(): void
    {
        $execution = $this->start();
        $this->putJson($this->setUrl($execution), $this->setBody(['status' => 'failed', 'actual_reps' => 0]))
            ->assertOk()->assertJsonPath('data.set.actual_reps', 0);
        $this->putJson($this->setUrl($execution, 1), $this->setBody([
            'status' => 'skipped', 'actual_reps' => null, 'actual_load' => null, 'load_unit' => null, 'actual_rir' => null,
        ]))->assertOk()->assertJsonPath('data.set.actual_reps', null)->assertJsonPath('data.set.load_kg', null);
    }

    public function test_invalid_set_values_are_rejected_without_writes(): void
    {
        $execution = $this->start();
        $url = $this->setUrl($execution);
        foreach ([
            ['actual_load' => '-1'], ['actual_load' => '10000.01'], ['actual_load' => '1.001'],
            ['actual_load' => '1e2'], ['load_unit' => 'oz'], ['load_unit' => null],
            ['load_mode' => 'unknown'], ['load_mode' => 'per_dumbbell'],
            ['actual_rir' => '10.1'], ['actual_rir' => '-1'], ['actual_rir' => '2.25'],
            ['actual_reps' => null], ['actual_reps' => 0], ['actual_reps' => 1001],
            ['status' => 'skipped'], ['type' => 'effective'], ['expected_revision' => -1],
            ['performed_at' => '2026-11-13 10:00:00'], ['operation_id' => 'not-uuid'],
        ] as $invalid) {
            $this->putJson($url, $this->setBody($invalid))->assertUnprocessable();
        }
        $body = $this->setBody();
        unset($body['actual_reps']);
        $this->putJson($url, $body)->assertUnprocessable();
        $this->assertSame(0, DB::table('workout_execution_sets')->count());
    }

    public function test_audit_failure_rolls_back_set_operation_and_execution_revision(): void
    {
        $execution = $this->start();
        DB::unprepared("CREATE TRIGGER fail_set_audit BEFORE INSERT ON workout_set_revisions BEGIN SELECT RAISE(ABORT, 'audit failed'); END");
        $body = $this->setBody();
        $url = $this->setUrl($execution);
        $this->putJson($url, $body)->assertStatus(500);
        $this->assertSame(0, DB::table('workout_execution_sets')->count());
        $this->assertSame(0, DB::table('workout_sync_operations')->count());
        $this->assertSame(1, WorkoutExecution::first()->revision);
        DB::unprepared('DROP TRIGGER fail_set_audit');
        $this->putJson($url, $body)->assertOk()->assertJsonPath('data.set.revision', 1);
    }

    public function test_equivalent_retry_is_canonical_and_client_cannot_forge_server_fields(): void
    {
        $execution = $this->start();
        $url = $this->setUrl($execution);
        $body = $this->setBody(['load_kg' => '999', 'revision' => 999, 'client_id' => 999]);
        $first = $this->putJson($url, $body)->assertOk()->assertJsonPath('data.set.load_kg', '75.000000')->json();
        $this->putJson($url, array_replace($body, [
            'operation_id' => strtoupper($body['operation_id']), 'actual_load' => 75,
            'actual_rir' => 2, 'performed_at' => '2026-11-13T16:00:00Z',
        ]))->assertOk()->assertExactJson($first);
        $this->assertSame(1, DB::table('workout_sync_operations')->count());
    }

    public function test_point_two_migration_rollback_preserves_execution_and_snapshot(): void
    {
        $execution = $this->start();
        $this->putJson($this->setUrl($execution), $this->setBody())->assertOk();
        $migration = require database_path('migrations/2026_11_13_000002_create_workout_execution_set_records.php');
        $migration->down();
        $this->assertSame(1, WorkoutExecution::count());
        $this->assertSame(1, WorkoutExecutionPrescription::count());
        $migration->up();
        $this->assertSame(0, DB::table('workout_execution_sets')->count());
    }

    public function test_voluntary_completion_keeps_missing_series_missing_and_is_idempotent(): void
    {
        $execution = $this->start();
        $url = self::URL.'/'.$execution['uuid'].'/complete';
        $body = ['operation_id' => (string) Str::uuid(), 'expected_execution_revision' => 1];
        $first = $this->postJson($url, $body)->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.execution_revision', 2)->json();
        $this->postJson($url, $body)->assertOk()->assertExactJson($first);
        $this->assertSame(0, DB::table('workout_execution_sets')->count());
        $this->assertSame(1, DB::table('workout_sync_operations')->count());
        $this->getJson(self::URL.'/'.$execution['uuid'])->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonCount(0, 'data.sets');
        $this->putJson($this->setUrl($execution), $this->setBody())->assertUnprocessable();
    }

    public function test_completion_requires_revision_after_acknowledged_series(): void
    {
        $execution = $this->start();
        $this->putJson($this->setUrl($execution), $this->setBody())->assertOk();
        $url = self::URL.'/'.$execution['uuid'].'/complete';
        $this->postJson($url, ['operation_id' => (string) Str::uuid(), 'expected_execution_revision' => 1])
            ->assertStatus(409)->assertJsonPath('code', 'execution_conflict')->assertJsonPath('data.revision', 2);
        $this->assertSame('in_progress', WorkoutExecution::first()->status);
        $this->postJson($url, ['operation_id' => (string) Str::uuid(), 'expected_execution_revision' => 2])->assertOk();
        $this->assertSame(1, DB::table('workout_execution_sets')->count());
    }

    public function test_completion_operational_failure_rolls_back_and_can_retry(): void
    {
        $execution = $this->start();
        DB::unprepared("CREATE TRIGGER fail_close BEFORE INSERT ON workout_sync_operations BEGIN SELECT RAISE(ABORT, 'close failed'); END");
        $url = self::URL.'/'.$execution['uuid'].'/complete';
        $body = ['operation_id' => (string) Str::uuid(), 'expected_execution_revision' => 1];
        $this->postJson($url, $body)->assertStatus(500);
        $this->assertSame('in_progress', WorkoutExecution::first()->status);
        $this->assertSame(1, WorkoutExecution::first()->revision);
        DB::unprepared('DROP TRIGGER fail_close');
        $this->postJson($url, $body)->assertOk();
    }

    public function test_completion_uses_existing_authorization_and_survives_commercial_expiry(): void
    {
        $execution = $this->start(); $this->membership(false); config(['workouts.executions_enabled' => false]);
        $url = self::URL.'/'.$execution['uuid'].'/complete';
        $body = ['operation_id' => (string) Str::uuid(), 'expected_execution_revision' => 1];
        $this->postJson($url, $body)->assertOk();
        $this->athlete->client->update(['coach_id' => User::factory()->create()->id]);
        $this->postJson($url, $body)->assertNotFound();
    }

    public function test_series_operation_cannot_be_reused_for_completion(): void
    {
        $execution = $this->start(); $body = $this->setBody();
        $this->putJson($this->setUrl($execution), $body)->assertOk();
        $this->postJson(self::URL.'/'.$execution['uuid'].'/complete', ['operation_id' => $body['operation_id'], 'expected_execution_revision' => 2])
            ->assertStatus(409)->assertJsonPath('code', 'operation_reused');
        $this->assertSame('in_progress', WorkoutExecution::first()->status);
    }

    public function test_additive_migration_can_rollback_without_touching_legacy(): void
    {
        $this->start();
        $migration = require database_path('migrations/'.self::MIGRATION);
        $setsMigration = require database_path('migrations/2026_11_13_000002_create_workout_execution_set_records.php');
        $prescriptionRevisions = require database_path('migrations/2026_11_15_000001_create_workout_prescription_revisions.php');
        $prescriptionRevisions->down();
        $setsMigration->down();
        $migration->down();
        $this->assertSame(1, TrainingAssignment::count());
        $this->assertSame(1, TrainingSectionLiftingRow::count());
        $migration->up();
        $setsMigration->up();
        $prescriptionRevisions->up();
        $this->assertSame(0, WorkoutExecution::count());
    }
}
