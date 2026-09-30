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
use App\Services\LiftingVolumeSummaryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiftingVolumeSummaryServiceTest extends TestCase
{
    private User $coach;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.lifting_volume_memory' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::setDefaultConnection('lifting_volume_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_06_042508_create_training_sessions_table.php',
            '2026_01_06_042534_create_training_sections_table.php',
            '2026_01_06_044345_create_training_assignments_table.php',
            '2026_07_28_120100_create_training_section_exercise_blocks_table.php',
            '2026_07_28_120200_create_training_section_lifting_rows_table.php',
            '2026_07_28_120300_create_training_lifting_set_logs_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        $this->coach = User::factory()->create();
        $this->client = Client::query()->create([
            'coach_id' => $this->coach->id,
            'first_name' => 'Atleta',
            'last_name' => 'Volumen',
            'email' => 'athlete-volume@example.test',
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('lifting_volume_memory');
        parent::tearDown();
    }

    public function test_assignment_without_lifting_returns_empty_summary(): void
    {
        $assignment = $this->assignmentWithSession();

        $summary = app(LiftingVolumeSummaryService::class)->forAssignment($assignment);

        $this->assertSame([
            'sets_prescribed' => 0,
            'sets_executed' => 0,
            'sets_completed' => 0,
            'sets_failed' => 0,
            'sets_skipped' => 0,
            'sets_pending' => 0,
            'reps_prescribed' => 0,
            'reps_executed' => 0,
            'rep_adherence_pct' => 0,
            'relative_volume' => 0.0,
            'chart' => [
                'reps' => [
                    ['label' => 'Programadas', 'value' => 0],
                    ['label' => 'Realizadas', 'value' => 0],
                ],
                'sets' => [
                    ['label' => 'Completados', 'value' => 0],
                    ['label' => 'Fallados', 'value' => 0],
                    ['label' => 'Saltados', 'value' => 0],
                    ['label' => 'Pendientes', 'value' => 0],
                ],
            ],
            'zones' => [
                ['key' => 'lt_60', 'label' => '<60%', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
                ['key' => '60_69', 'label' => '60-69%', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
                ['key' => '70_79', 'label' => '70-79%', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
                ['key' => '80_89', 'label' => '80-89%', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
                ['key' => '90_94', 'label' => '90-94%', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
                ['key' => '95_plus', 'label' => '95%+', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
                ['key' => 'unknown', 'label' => 'Sin %', 'prescribed_reps' => 0, 'executed_reps' => 0, 'adherence_pct' => 0],
            ],
            'by_exercise' => [],
        ], $summary);
    }

    public function test_it_calculates_chart_zones_pending_sets_and_exercise_groups(): void
    {
        $assignment = $this->assignmentWithSession();
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

        $summary = app(LiftingVolumeSummaryService::class)->forAssignment($assignment);

        $this->assertSame(5, $summary['sets_prescribed']);
        $this->assertSame(4, $summary['sets_executed']);
        $this->assertSame(2, $summary['sets_completed']);
        $this->assertSame(1, $summary['sets_failed']);
        $this->assertSame(1, $summary['sets_skipped']);
        $this->assertSame(1, $summary['sets_pending']);
        $this->assertSame(19, $summary['reps_prescribed']);
        $this->assertSame(9, $summary['reps_executed']);
        $this->assertSame(47, $summary['rep_adherence_pct']);
        $this->assertSame(400.0, $summary['relative_volume']);
        $this->assertSame([
            'reps' => [
                ['label' => 'Programadas', 'value' => 19],
                ['label' => 'Realizadas', 'value' => 9],
            ],
            'sets' => [
                ['label' => 'Completados', 'value' => 2],
                ['label' => 'Fallados', 'value' => 1],
                ['label' => 'Saltados', 'value' => 1],
                ['label' => 'Pendientes', 'value' => 1],
            ],
        ], $summary['chart']);

        $zones = collect($summary['zones'])->keyBy('key');

        $this->assertSame(9, $zones['80_89']['prescribed_reps']);
        $this->assertSame(5, $zones['80_89']['executed_reps']);
        $this->assertSame(56, $zones['80_89']['adherence_pct']);
        $this->assertSame(10, $zones['unknown']['prescribed_reps']);
        $this->assertSame(4, $zones['unknown']['executed_reps']);
        $this->assertSame(40, $zones['unknown']['adherence_pct']);
        $this->assertSame($summary['reps_prescribed'], collect($summary['zones'])->sum('prescribed_reps'));
        $this->assertSame($summary['reps_executed'], collect($summary['zones'])->sum('executed_reps'));

        $this->assertSame([
            [
                'exercise_name' => 'Back Squat',
                'sets_prescribed' => 5,
                'sets_executed' => 4,
                'reps_prescribed' => 19,
                'reps_executed' => 9,
                'rep_adherence_pct' => 47,
                'relative_volume' => 400.0,
            ],
        ], $summary['by_exercise']);
    }

    private function assignmentWithSession(): TrainingAssignment
    {
        $session = TrainingSession::query()->create([
            'coach_id' => $this->coach->id,
            'title' => 'Lifting volume test',
            'scheduled_at' => '2026-09-29',
            'duration_minutes' => 60,
            'level' => 'beginner',
            'goal' => 'strength',
            'type' => 'weightlifting',
            'visibility' => 'assigned',
        ]);

        return TrainingAssignment::query()->create([
            'training_session_id' => $session->id,
            'client_id' => $this->client->id,
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
