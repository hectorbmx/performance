<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndAdminSeeder::class,      // 1️⃣ roles + admin
            TrainingMetricsSeeder::class,    // 2️⃣ catálogo base
            CoachTrainingMetricsSeeder::class, // 3️⃣ override por coach
        ]);
    }
}
