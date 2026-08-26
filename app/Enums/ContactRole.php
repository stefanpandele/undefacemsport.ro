<?php

namespace App\Enums;

enum ContactRole: string
{
    case General = 'general';
    case Person = 'person';
    case LegalRepresentative = 'legal_representative';
    case Accounting = 'accounting';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Contact general',
            self::Person => 'Persoană de contact',
            self::LegalRepresentative => 'Reprezentant legal',
            self::Accounting => 'Contabilitate',
        };
    }
}
