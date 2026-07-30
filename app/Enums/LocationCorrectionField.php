<?php

namespace App\Enums;

/**
 * The fields of a shared location a club may ask to have fixed. Deliberately a
 * closed list: coordinates and the slug are not in it, because moving a pin or a
 * public URL is a merge or a claim, not a correction.
 */
enum LocationCorrectionField: string
{
    case Name = 'name';
    case Address = 'address';
    case City = 'city';
    case County = 'county';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Nume locație',
            self::Address => 'Stradă și număr',
            self::City => 'Oraș / Localitate',
            self::County => 'Județ',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $field): array => [$field->value => $field->label()])
            ->all();
    }
}
