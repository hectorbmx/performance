<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    

public function run(): void
{
    $units = [
        // WEIGHT
        [
            'coach_id'   => null,
            'code'       => 'kg',
            'name'       => 'Kilogramos',
            'symbol'     => 'kg',
            'result_type'=> 'weight',
            'is_active'  => true,
        ],
        [
            'coach_id'   => null,
            'code'       => 'lb',
            'name'       => 'Libras',
            'symbol'     => 'lb',
            'result_type'=> 'weight',
            'is_active'  => true,
        ],

        // TIME
        [
            'coach_id'   => null,
            'code'       => 's',
            'name'       => 'Segundos',
            'symbol'     => 's',
            'result_type'=> 'time',
            'is_active'  => true,
        ],
        [
            'coach_id'   => null,
            'code'       => 'min',
            'name'       => 'Minutos',
            'symbol'     => 'min',
            'result_type'=> 'time',
            'is_active'  => true,
        ],

        // DISTANCE
        [
            'coach_id'   => null,
            'code'       => 'm',
            'name'       => 'Metros',
            'symbol'     => 'm',
            'result_type'=> 'distance',
            'is_active'  => true,
        ],
        [
            'coach_id'   => null,
            'code'       => 'km',
            'name'       => 'Kilómetros',
            'symbol'     => 'km',
            'result_type'=> 'distance',
            'is_active'  => true,
        ],

        // REPS / ROUNDS / SETS
        [
            'coach_id'   => null,
            'code'       => 'reps',
            'name'       => 'Repeticiones',
            'symbol'     => 'reps',
            'result_type'=> 'reps',
            'is_active'  => true,
        ],
        [
            'coach_id'   => null,
            'code'       => 'rounds',
            'name'       => 'Rondas',
            'symbol'     => 'rds',
            'result_type'=> 'rounds',
            'is_active'  => true,
        ],
        [
            'coach_id'   => null,
            'code'       => 'sets',
            'name'       => 'Series',
            'symbol'     => 'sets',
            'result_type'=> 'sets',
            'is_active'  => true,
        ],

        // CALORIES / POINTS
        [
            'coach_id'   => null,
            'code'       => 'kcal',
            'name'       => 'Calorías',
            'symbol'     => 'kcal',
            'result_type'=> 'calories',
            'is_active'  => true,
        ],
        [
            'coach_id'   => null,
            'code'       => 'pts',
            'name'       => 'Puntos',
            'symbol'     => 'pts',
            'result_type'=> 'points',
            'is_active'  => true,
        ],
    ];

    foreach ($units as $unit) {
        Unit::firstOrCreate(
            [
                'coach_id'    => $unit['coach_id'],
                'code'        => $unit['code'],
                'result_type' => $unit['result_type'],
            ],
            $unit
        );
    }
}

}
