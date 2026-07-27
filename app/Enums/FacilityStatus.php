<?php

namespace App\Enums;

enum FacilityStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'În așteptare',
            self::Approved => 'Aprobată',
        };
    }
}
