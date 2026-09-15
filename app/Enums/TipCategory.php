<?php

namespace App\Enums;

enum TipCategory: string
{
    case NUTRITION = 'nutrition';
    case TRAINING = 'training';
    case RECOVERY = 'recovery';
    case WELLBEING = 'wellbeing';
    case GENERAL = 'general';

    public static function labels(): array
    {
        return [
            self::NUTRITION->value => 'Nutrición',
            self::TRAINING->value => 'Entrenamiento',
            self::RECOVERY->value => 'Recuperación',
            self::WELLBEING->value => 'Hábitos y bienestar',
            self::GENERAL->value => 'General',
        ];
    }

    public static function values(): array
    {
        return array_map(fn (self $value) => $value->value, self::cases());
    }
}

