<?php

namespace App\Enums;

enum TipType: string
{
    case TIP = 'tip';
    case NOTE = 'note';
    case NEWS = 'news';

    public static function labels(): array
    {
        return [
            self::TIP->value => 'Consejo',
            self::NOTE->value => 'Nota',
            self::NEWS->value => 'Noticia',
        ];
    }

    public static function values(): array
    {
        return array_map(fn (self $value) => $value->value, self::cases());
    }
}

