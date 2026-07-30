<?php

namespace App\Enums;

/**
 * What kind of professional a person is. Separate from `people.role`, which holds
 * the free-text job title shown publicly ("Antrenor principal", "Coordonator").
 */
enum PersonProfession: string
{
    case Coach = 'coach';
    case Doctor = 'doctor';
    case Physiotherapist = 'physiotherapist';
    case Nutritionist = 'nutritionist';

    public function label(): string
    {
        return match ($this) {
            self::Coach => 'Antrenor',
            self::Doctor => 'Medic',
            self::Physiotherapist => 'Fizioterapeut',
            self::Nutritionist => 'Nutriționist',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $profession): array => [$profession->value => $profession->label()])
            ->all();
    }
}
