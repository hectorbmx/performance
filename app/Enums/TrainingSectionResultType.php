<?php

namespace App\Enums;

enum TrainingSectionResultType: string
{
    case NONE      = 'none';
    case REPS      = 'reps';
    case TIME      = 'time';
    case WEIGHT    = 'weight';
    case DISTANCE  = 'distance';
    case ROUNDS    = 'rounds';
    case SETS      = 'sets';
    case CALORIES  = 'calories';
    case POINTS    = 'points';
    case NOTE      = 'note';
    case BOOLEAN   = 'boolean';

    public static function labels(): array
    {
        return [
            self::NONE->value     => 'Sin resultados',
            self::REPS->value     => 'Repeticiones',
            self::TIME->value     => 'Tiempo',
            self::WEIGHT->value   => 'Peso',
            self::DISTANCE->value => 'Distancia',
            self::ROUNDS->value   => 'Rondas',
            self::SETS->value     => 'Series',
            self::CALORIES->value => 'Calorías',
            self::POINTS->value   => 'Puntos',
            self::NOTE->value     => 'Nota / Texto',
            self::BOOLEAN->value  => 'Sí / No',
        ];
    }

    public static function values(): array
    {
        return array_map(fn($e) => $e->value, self::cases());
    }
}
