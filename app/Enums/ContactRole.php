<?php

namespace App\Enums;

enum ContactRole: string
{
    case General = 'general';
    case Coach = 'coach';
    case LegalRepresentative = 'legal_representative';
    case Accounting = 'accounting';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Contact general',
            self::Coach => 'Antrenor',
            self::LegalRepresentative => 'Reprezentant legal',
            self::Accounting => 'Contabilitate',
        };
    }
}
