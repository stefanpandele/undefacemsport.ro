<?php

namespace App\Enums;

/**
 * How a visitor gets in — the one axis every listing groups by.
 *
 * Three values rather than the two of `SpaceAccessMode`, because a training
 * programme is the third way in and it is not a space at all.
 *
 * These are the words, everywhere: the badge on a location card, the filter on
 * the explore page, the tab on an organization's page and the fragment that
 * opens it. One question asked in one vocabulary, or a visitor has to translate
 * between screens.
 */
enum LocationWay: string
{
    case Organised = 'cursuri';
    case OpenAccess = 'agrement';
    case Rental = 'inchiriere';

    public function label(): string
    {
        return match ($this) {
            self::Organised => 'Cursuri',
            self::OpenAccess => 'Agrement',
            self::Rental => 'Închiriere',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Organised => 'Te înscrii, pe grupe, cu antrenor',
            self::OpenAccess => 'Plătești intrarea și intri',
            self::Rental => 'Rezervi tot spațiul pentru grupul tău',
        };
    }

    /**
     * The space access mode this way corresponds to, or null for the training
     * programmes, which are not a space at all.
     */
    public function accessMode(): ?SpaceAccessMode
    {
        return match ($this) {
            self::Organised => null,
            self::OpenAccess => SpaceAccessMode::OpenAccess,
            self::Rental => SpaceAccessMode::ExclusiveRental,
        };
    }

    /**
     * The way in that a space of this access mode is reached through.
     */
    public static function forAccessMode(SpaceAccessMode $mode): self
    {
        return match ($mode) {
            SpaceAccessMode::OpenAccess => self::OpenAccess,
            SpaceAccessMode::ExclusiveRental => self::Rental,
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $way): array => [
            'value' => $way->value,
            'label' => $way->label(),
            'description' => $way->description(),
        ], self::cases());
    }
}
