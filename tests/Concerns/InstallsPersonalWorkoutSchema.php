<?php
namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InstallsPersonalWorkoutSchema
{
    protected function installPersonalWorkoutSchema(): void
    {
        if (DB::getDriverName()!=='sqlite' || DB::connection()->getDatabaseName()!==':memory:') throw new \LogicException('Pruebas GYM solo en SQLite aislado.');
        if (!Schema::hasColumn('user_apps','self_registered_at')) (require database_path('migrations/2026_11_16_000001_decouple_athlete_accounts_from_clients.php'))->up();
        foreach ([
            'training_assignments'=>'2026_01_06_044345_create_training_assignments_table.php',
            'workout_executions'=>'2026_11_13_000001_create_workout_executions_tables.php',
            'workout_execution_sets'=>'2026_11_13_000002_create_workout_execution_set_records.php',
            'workout_exercises'=>'2026_11_14_000001_create_workout_exercise_catalog.php',
            'workout_athlete_settings'=>'2026_11_14_000002_create_workout_athlete_settings.php',
            'workout_max_records'=>'2026_11_14_000003_create_workout_max_records.php',
            'workout_load_suggestions'=>'2026_11_14_000004_create_workout_load_suggestions.php',
            'workout_prescription_revisions'=>'2026_11_15_000001_create_workout_prescription_revisions.php',
            'workout_exercise_goals'=>'2026_11_15_000002_create_workout_exercise_goals.php',
            'workout_ownership_claims'=>'2026_11_16_000002_add_personal_gym_ownership.php',
        ] as $table=>$file) if (!Schema::hasTable($table)) (require database_path('migrations/'.$file))->up();
    }
}
