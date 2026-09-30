<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TrainingAssignment;
use App\Models\TrainingLiftingSetLog;
use App\Models\TrainingSection;
use App\Models\TrainingSectionExerciseBlock;
use App\Models\TrainingSectionLiftingRow;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingAssignmentDetailVolumeSummaryTest extends TestCase
{
    private int $coachId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.training_detail_volume_memory' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ], 'cache.default' => 'array']);

        DB::setDefaultConnection('training_detail_volume_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_050926_create_coach_client_plans_table.php',
            '2026_01_05_051109_create_client_memberships_table.php',
            '2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_01_06_042508_create_training_sessions_table.php',
            '2026_01_06_042534_create_training_sections_table.php',
            '2026_01_06_044345_create_training_assignments_table.php',
            '2026_01_15_022853_create_training_section_results_table.php',
            '2026_01_15_025204_add_scheduled_for_to_training_assignments_table.php',
            '2026_01_18_050612_add_tag_color_to_training_sessions_table.php',
            '2026_01_18_062704_add_cover_image_to_training_sessions_table.php',
            '2026_01_19_011136_create_training_type_catalogs_table.php',
            '2026_01_21_023624_add_video_url_to_training_sections_table.php',
            '2026_02_07_210122_create_training_section_completions_table.php',
            '2026_02_13_232656_create_library_videos_table.php',
            '2026_02_13_233200_create_training_section_library_videos_table.php',
            '2026_02_16_120000_add_video_path_to_training_sections_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php',
            '2026_07_28_120200_create_training_section_lifting_rows_table.php',
            '2026_07_28_120300_create_training_lifting_set_logs_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Carbon::setTestNow('2026-09-29 10:00:00');
        $this->coachId = User::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('training_detail_volume_memory');
        parent::tearDown();
    }

    public function test_active_athlete_can_read_empty_lifting_volume_summary(): void
    {
        $athlete = $this->athlete();
        $assignment = $this->assignment($athlete->client_id);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/training-assignments/'.$assignment->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.lifting_volume_summary.sets_prescribed', 0)
            ->assertJsonPath('data.lifting_volume_summary.sets_executed', 0)
            ->assertJsonPath('data.lifting_volume_summary.sets_completed', 0)
            ->assertJsonPath('data.lifting_volume_summary.sets_failed', 0)
            ->assertJsonPath('data.lifting_volume_summary.sets_skipped', 0)
            ->assertJsonPath('data.lifting_volume_summary.sets_pending', 0)
            ->assertJsonPath('data.lifting_volume_summary.reps_prescribed', 0)
            ->assertJsonPath('data.lifting_volume_summary.reps_executed', 0)
            ->assertJsonPath('data.lifting_volume_summary.rep_adherence_pct', 0)
            ->assertJsonPath('data.lifting_volume_summary.relative_volume', 0)
            ->assertJsonPath('data.lifting_volume_summary.chart.reps.0.value', 0)
            ->assertJsonPath('data.lifting_volume_summary.chart.sets.3.label', 'Pendientes')
            ->assertJsonPath('data.lifting_volume_summary.zones.6.key', 'unknown')
            ->assertJsonPath('data.lifting_volume_summary.by_exercise', []);
    }

    public function test_active_athlete_can_read_lifting_volume_summary(): void
    {
        $athlete = $this->athlete();
        $assignment = $this->assignment($athlete->client_id);
        $section = $this->section($assignment->trainingSession);
        $block = TrainingSectionExerciseBlock::query()->create([
            'training_section_id' => $section->id,
            'exercise_name' => 'Back Squat',
            'order' => 1,
        ]);
        $percentageRow = TrainingSectionLiftingRow::query()->create([
            'exercise_block_id' => $block->id,
            'percentage' => 80,
            'reps' => 3,
            'sets' => 3,
            'order' => 1,
        ]);
        $noPercentageRow = TrainingSectionLiftingRow::query()->create([
            'exercise_block_id' => $block->id,
            'percentage' => null,
            'reps' => 5,
            'sets' => 2,
            'order' => 2,
        ]);

        $this->log($assignment, $percentageRow, 1, TrainingLiftingSetLog::STATUS_COMPLETED, null);
        $this->log($assignment, $percentageRow, 2, TrainingLiftingSetLog::STATUS_FAILED, 2);
        $this->log($assignment, $percentageRow, 3, TrainingLiftingSetLog::STATUS_SKIPPED, null);
        $this->log($assignment, $noPercentageRow, 1, TrainingLiftingSetLog::STATUS_COMPLETED, 4);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/training-assignments/'.$assignment->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.lifting_volume_summary.sets_prescribed', 5)
            ->assertJsonPath('data.lifting_volume_summary.sets_executed', 4)
            ->assertJsonPath('data.lifting_volume_summary.sets_completed', 2)
            ->assertJsonPath('data.lifting_volume_summary.sets_failed', 1)
            ->assertJsonPath('data.lifting_volume_summary.sets_skipped', 1)
            ->assertJsonPath('data.lifting_volume_summary.sets_pending', 1)
            ->assertJsonPath('data.lifting_volume_summary.reps_prescribed', 19)
            ->assertJsonPath('data.lifting_volume_summary.reps_executed', 9)
            ->assertJsonPath('data.lifting_volume_summary.rep_adherence_pct', 47)
            ->assertJsonPath('data.lifting_volume_summary.relative_volume', 400)
            ->assertJsonPath('data.lifting_volume_summary.chart.reps.0.label', 'Programadas')
            ->assertJsonPath('data.lifting_volume_summary.chart.reps.0.value', 19)
            ->assertJsonPath('data.lifting_volume_summary.chart.reps.1.value', 9)
            ->assertJsonPath('data.lifting_volume_summary.chart.sets.0.value', 2)
            ->assertJsonPath('data.lifting_volume_summary.chart.sets.1.value', 1)
            ->assertJsonPath('data.lifting_volume_summary.chart.sets.2.value', 1)
            ->assertJsonPath('data.lifting_volume_summary.chart.sets.3.value', 1)
            ->assertJsonPath('data.lifting_volume_summary.zones.3.key', '80_89')
            ->assertJsonPath('data.lifting_volume_summary.zones.3.prescribed_reps', 9)
            ->assertJsonPath('data.lifting_volume_summary.zones.3.executed_reps', 5)
            ->assertJsonPath('data.lifting_volume_summary.zones.3.adherence_pct', 56)
            ->assertJsonPath('data.lifting_volume_summary.zones.6.key', 'unknown')
            ->assertJsonPath('data.lifting_volume_summary.zones.6.prescribed_reps', 10)
            ->assertJsonPath('data.lifting_volume_summary.zones.6.executed_reps', 4)
            ->assertJsonPath('data.lifting_volume_summary.by_exercise.0.exercise_name', 'Back Squat')
            ->assertJsonPath('data.lifting_volume_summary.by_exercise.0.reps_prescribed', 19)
            ->assertJsonPath('data.lifting_volume_summary.by_exercise.0.reps_executed', 9)
            ->assertJsonPath('data.lifting_volume_summary.by_exercise.0.relative_volume', 400);
    }

    private function athlete(): UserApp
    {
        $client = Client::query()->create([
            'coach_id' => $this->coachId,
            'first_name' => 'Atleta',
            'last_name' => 'Volumen',
            'email' => 'volume-'.uniqid().'@example.test',
        ]);

        $athlete = UserApp::query()->create([
            'client_id' => $client->id,
            'email' => 'app-volume-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $planId = DB::table('coach_client_plans')->insertGetId([
            'coach_id' => $this->coachId,
            'name' => 'Mensual',
            'description' => null,
            'price' => 500,
            'billing_cycle_days' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_memberships')->insert([
            'coach_id' => $this->coachId,
            'client_id' => $client->id,
            'coach_client_plan_id' => $planId,
            'plan_name_snapshot' => 'Mensual',
            'price_snapshot' => 500,
            'billing_cycle_days_snapshot' => 30,
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'next_renewal_at' => null,
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'paid',
            'grace_until' => null,
            'paid_at' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $athlete;
    }

    private function assignment(int $clientId): TrainingAssignment
    {
        $session = TrainingSession::query()->create([
            'coach_id' => $this->coachId,
            'title' => 'Lifting volume endpoint test',
            'scheduled_at' => '2026-09-29',
            'duration_minutes' => 60,
            'level' => 'beginner',
            'goal' => 'strength',
            'type' => 'weightlifting',
            'visibility' => 'assigned',
            'notes' => null,
        ]);

        return TrainingAssignment::query()->create([
            'training_session_id' => $session->id,
            'client_id' => $clientId,
            'scheduled_for' => '2026-09-29',
            'status' => 'scheduled',
        ]);
    }

    private function section(TrainingSession $session): TrainingSection
    {
        return TrainingSection::query()->create([
            'training_session_id' => $session->id,
            'order' => 1,
            'name' => 'Strength',
            'description' => null,
            'accepts_results' => false,
            'result_type' => null,
        ]);
    }

    private function log(
        TrainingAssignment $assignment,
        TrainingSectionLiftingRow $row,
        int $setNumber,
        string $status,
        ?int $actualReps
    ): void {
        TrainingLiftingSetLog::query()->create([
            'training_assignment_id' => $assignment->id,
            'lifting_row_id' => $row->id,
            'set_number' => $setNumber,
            'status' => $status,
            'actual_reps' => $actualReps,
            'logged_at' => now(),
        ]);
    }
}
