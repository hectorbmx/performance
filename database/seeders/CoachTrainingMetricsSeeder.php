<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CoachTrainingMetricsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1) Obtener TODOS los coaches reales
        $coachUserIds = DB::table('coach_profiles')->pluck('user_id');

        if ($coachUserIds->isEmpty()) {
            $this->command->warn('No hay coaches en coach_profiles. Seeder cancelado.');
            return;
        }

        // 2) Métricas base
        $metrics = DB::table('training_metrics')
            ->whereIn('code', [
                'back_squat_1rm',
                'clean_1rm',
                'snatch_1rm',
            ])
            ->orderBy('id')
            ->get();

        foreach ($coachUserIds as $coachUserId) {
            $order = 1;

            foreach ($metrics as $metric) {
                DB::table('coach_training_metrics')->updateOrInsert(
                    [
                        'coach_id' => $coachUserId,
                        'training_metric_id' => $metric->id,
                    ],
                    [
                        'is_required' => false,
                        'sort_order'  => $order++,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]
                );
            }
        }
    }
}
