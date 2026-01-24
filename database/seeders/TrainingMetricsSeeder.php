<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TrainingMetricsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $metrics = [
            [
                'code' => 'back_squat_1rm',
                'name' => 'Back Squat 1RM',
                'unit' => 'kg',
                'type' => 'max',
            ],
            [
                'code' => 'clean_1rm',
                'name' => 'Clean 1RM',
                'unit' => 'kg',
                'type' => 'max',
            ],
            [
                'code' => 'snatch_1rm',
                'name' => 'Snatch 1RM',
                'unit' => 'kg',
                'type' => 'max',
            ],
        ];

        foreach ($metrics as $metric) {
            DB::table('training_metrics')->updateOrInsert(
                ['code' => $metric['code']],
                [
                    'name'       => $metric['name'],
                    'unit'       => $metric['unit'],
                    'type'       => $metric['type'],
                    'is_active'  => true,
                    'updated_at'=> $now,
                    'created_at'=> $now,
                ]
            );
        }
    }
}
