<?php

namespace App\Enums;

/**
 * How a visitor wants to get in, as a filter on the explore page.
 *
 * Three values rather than the two of `SpaceAccessMode`, because a club's
 * training programme is the third way in and it is not a space at all. The
 * public URL carries the Romanian words a visitor would recognise.
 */
enum LocationWay: string
{
    case Organised = 'organizat';
    case OpenAccess = 'liber';
    case Rental = 'inchiriere';

    public function label(): string
    {
        return match ($this) {
            self::Organised => 'Program organizat',
            self::OpenAccess => 'Acces liber',
            self::Rental => 'Închiriere',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Organised => 'Te înscrii la un club',
            self::OpenAccess => 'Vii și intri',
            self::Rental => 'Rezervi tot spațiul',
        };
    }

    /**
     * The space access mode this way corresponds to, or null for the club
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
