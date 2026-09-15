<?php

namespace App\Enums;

enum TipStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public static function labels(): array
    {
        return [
            self::DRAFT->value => 'Borrador',
            self::PENDING_APPROVAL->value => 'Pendiente de aprobación',
            self::PUBLISHED->value => 'Publicado',
            self::REJECTED->value => 'Rechazado',
            self::ARCHIVED->value => 'Archivado',
        ];
    }

    public static function values(): array
    {
        return array_map(fn (self $value) => $value->value, self::cases());
    }
}

