<?php

namespace App\Services;

use App\Models\CoachTrainingMetric;
use App\Models\SectionTypeCatalog;
use App\Models\TrainingGoalCatalog;
use App\Models\TrainingMetric;
use App\Models\TrainingTypeCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantBaseCatalogService
{
    private const TRAINING_TYPES = [
        ['name' => 'GYM', 'description' => 'Entrenamientos generales de gimnasio.'],
        ['name' => 'CROSSFIT', 'description' => 'Sesiones tipo CrossFit y acondicionamiento mixto.'],
        ['name' => 'FITNESS', 'description' => 'Entrenamientos de fitness general.'],
        ['name' => 'HIROX', 'description' => 'Preparacion orientada a HYROX.'],
        ['name' => 'WEIGHTLIFT', 'description' => 'Trabajo de halterofilia y levantamientos olimpicos.'],
    ];

    private const TRAINING_GOALS = [
        ['name' => 'FUERZA', 'description' => 'Desarrollo de fuerza maxima y fuerza base.'],
        ['name' => 'EXPLOSIVIDAD', 'description' => 'Potencia, velocidad y capacidad reactiva.'],
        ['name' => 'TECNICA', 'description' => 'Mejora tecnica y control del movimiento.'],
        ['name' => 'RESISTENCIA', 'description' => 'Capacidad aerobica, muscular y sostenimiento del esfuerzo.'],
        ['name' => 'AGILIDAD', 'description' => 'Coordinacion, cambios de direccion y respuesta motriz.'],
    ];

    private const SECTION_TYPES = [
        ['name' => 'calentamiento', 'description' => 'Preparacion inicial antes del bloque principal.'],
        ['name' => 'levantamientos', 'description' => 'Bloques de fuerza, tecnica o halterofilia.'],
        ['name' => 'wod', 'description' => 'Workout of the day o bloque principal de condicionamiento.'],
        ['name' => 'flexibilidad', 'description' => 'Movilidad, estiramientos y rango de movimiento.'],
        ['name' => 'cooldown', 'description' => 'Vuelta a la calma y recuperacion final.'],
    ];

    private const TRAINING_METRICS = [
        ['code' => 'back_squat_1rm', 'name' => 'BACK SQUAT 1RM', 'unit' => 'kg', 'type' => 'max'],
        ['code' => 'clean_1rm', 'name' => 'CLEAN 1RM', 'unit' => 'kg', 'type' => 'max'],
        ['code' => 'snatch_1rm', 'name' => 'SNATCH 1RM', 'unit' => 'kg', 'type' => 'max'],
        ['code' => 'jerk_1rm', 'name' => 'JERK 1RM', 'unit' => 'kg', 'type' => 'max'],
        ['code' => 'clean_jerk_1rm', 'name' => 'CLEAN & JERK 1RM', 'unit' => 'kg', 'type' => 'max'],
    ];

    public function seedForCoach(User|int $coach): void
    {
        $coachId = $coach instanceof User ? (int) $coach->id : (int) $coach;

        DB::transaction(function () use ($coachId) {
            $this->seedTrainingTypes($coachId);
            $this->seedTrainingGoals($coachId);
            $this->seedSectionTypes($coachId);
            $this->seedTrainingMetrics();
            $this->enableTrainingMetricsForCoach($coachId);
        });
    }

    public function seedForAllCoaches(): void
    {
        User::query()
            ->whereHas('coachProfile')
            ->orderBy('id')
            ->chunkById(100, function ($coaches) {
                foreach ($coaches as $coach) {
                    $this->seedForCoach($coach);
                }
            });
    }

    private function seedTrainingTypes(int $coachId): void
    {
        foreach (self::TRAINING_TYPES as $row) {
            $item = TrainingTypeCatalog::withTrashed()->updateOrCreate(
                ['coach_id' => $coachId, 'name' => $row['name']],
                ['description' => $row['description'], 'is_active' => true]
            );

            if ($item->trashed()) {
                $item->restore();
            }
        }
    }

    private function seedTrainingGoals(int $coachId): void
    {
        foreach (self::TRAINING_GOALS as $row) {
            $item = TrainingGoalCatalog::withTrashed()->updateOrCreate(
                ['coach_id' => $coachId, 'name' => $row['name']],
                ['description' => $row['description'], 'is_active' => true]
            );

            if ($item->trashed()) {
                $item->restore();
            }
        }
    }

    private function seedSectionTypes(int $coachId): void
    {
        foreach (self::SECTION_TYPES as $row) {
            $item = SectionTypeCatalog::withTrashed()->updateOrCreate(
                ['coach_id' => $coachId, 'name' => $row['name']],
                ['description' => $row['description'], 'is_active' => true]
            );

            if ($item->trashed()) {
                $item->restore();
            }
        }
    }

    private function seedTrainingMetrics(): void
    {
        foreach (self::TRAINING_METRICS as $row) {
            TrainingMetric::updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'unit' => $row['unit'], 'type' => $row['type'], 'is_active' => true]
            );
        }
    }

    private function enableTrainingMetricsForCoach(int $coachId): void
    {
        $codes = array_column(self::TRAINING_METRICS, 'code');
        $sortMap = array_flip($codes);

        $metrics = TrainingMetric::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (TrainingMetric $metric) => $sortMap[$metric->code] ?? 999);

        $order = 1;

        foreach ($metrics as $metric) {
            CoachTrainingMetric::updateOrCreate(
                ['coach_id' => $coachId, 'training_metric_id' => $metric->id],
                ['is_required' => false, 'sort_order' => $order++]
            );
        }
    }
}
