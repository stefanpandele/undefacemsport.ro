<?php

namespace App\Enums;

/**
 * How you get into a space — the visitor's real question, and the axis every
 * listing groups by.
 *
 * Deliberately not about ownership: a free park court and a hotel pool with a
 * ticket are both `OpenAccess`, differing only on price. That is why the park
 * needs no special case anywhere in the app.
 *
 * A club's training programme is the third way in, but it is the club's offer
 * rather than a space's, so it lives on `organization_sport` and not here.
 */
enum SpaceAccessMode: string
{
    case OpenAccess = 'open_access';
    case ExclusiveRental = 'exclusive_rental';

    /**
     * What the operator picks in the form.
     *
     * "Acces liber" read as *free of charge* rather than *without booking*, so a
     * pool with a 25-lei ticket looked like the wrong option. The distinction
     * that actually matters is whether you buy a place for yourself or take the
     * whole space, and "individual" says that without colliding with "gratuit".
     */
    public function label(): string
    {
        return match ($this) {
            self::OpenAccess => 'Acces individual',
            self::ExclusiveRental => 'Închiriere exclusivă',
        };
    }

    /**
     * What the visitor is about to do, first person — the label on the chooser.
     */
    public function verb(): string
    {
        return match ($this) {
            self::OpenAccess => 'Vin și intru',
            self::ExclusiveRental => 'Închiriez spațiul',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OpenAccess => 'Plătești intrarea și intri, fără rezervare',
            self::ExclusiveRental => 'Rezervi tot spațiul pentru grupul tău',
        };
    }

    /**
     * The unit a space of this kind is normally priced in.
     */
    public function defaultPriceUnit(): PriceUnit
    {
        return match ($this) {
            self::OpenAccess => PriceUnit::Entry,
            self::ExclusiveRental => PriceUnit::Hour,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])
            ->all();
    }
}
