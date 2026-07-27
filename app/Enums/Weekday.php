<?php

namespace App\Enums;

use DateTimeInterface;

enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Luni',
            self::Tuesday => 'Marți',
            self::Wednesday => 'Miercuri',
            self::Thursday => 'Joi',
            self::Friday => 'Vineri',
            self::Saturday => 'Sâmbătă',
            self::Sunday => 'Duminică',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Monday => 'LUN',
            self::Tuesday => 'MAR',
            self::Wednesday => 'MIE',
            self::Thursday => 'JOI',
            self::Friday => 'VIN',
            self::Saturday => 'SÂM',
            self::Sunday => 'DUM',
        };
    }

    /**
     * The weekday a given moment falls on (ISO: Monday = 1).
     */
    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('N'));
    }

    /**
     * Value => label map for select inputs.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $day): array => [$day->value => $day->label()])
            ->all();
    }
}
