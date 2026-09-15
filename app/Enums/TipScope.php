<?php

namespace App\Enums;

enum TipScope: string
{
    case GLOBAL = 'global';
    case TENANT = 'tenant';

    public static function labels(): array
    {
        return [
            self::GLOBAL->value => 'Global',
            self::TENANT->value => 'Atletas del coach',
        ];
    }

    public static function values(): array
    {
        return array_map(fn (self $value) => $value->value, self::cases());
    }
}

