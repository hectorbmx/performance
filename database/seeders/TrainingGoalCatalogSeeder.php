<?php

namespace Database\Seeders;

use App\Models\TrainingGoalCatalog;
use Illuminate\Database\Seeder;

class TrainingGoalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $coachId = 1;

        $rows = [
            [
                'name' => 'Fuerza',
                'description' => 'Entrenamientos enfocados en fuerza',
            ],
            [
                'name' => 'Cardio',
                'description' => 'Entrenamientos cardiovasculares',
            ],
            [
                'name' => 'Técnica',
                'description' => 'Trabajo técnico y habilidades',
            ],
            [
                'name' => 'Movilidad',
                'description' => 'Movilidad y recuperación',
            ],
            [
                'name' => 'Mixto',
                'description' => 'Entrenamiento combinado',
            ],
        ];

        foreach ($rows as $row) {
            TrainingGoalCatalog::updateOrCreate(
                [
                    'coach_id' => $coachId,
                    'name' => $row['name'],
                ],
                [
                    'description' => $row['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}