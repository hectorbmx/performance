<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainingGoalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'strength',  'name' => 'Fuerza'],
            ['key' => 'cardio',    'name' => 'Cardio'],
            ['key' => 'technique', 'name' => 'Técnica'],
            ['key' => 'mobility',  'name' => 'Movilidad'],
            ['key' => 'mixed',     'name' => 'Mixto'],
        ];

        foreach ($rows as $r) {
            DB::table('training_goal_catalogs')->updateOrInsert(
                ['key' => $r['key']],
                [
                    'name' => $r['name'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
