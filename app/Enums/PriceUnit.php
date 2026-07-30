<?php

namespace App\Enums;

/**
 * What a space's price is per. Kept apart from the amount so the page can say
 * "45 lei / intrare" and "120 lei / oră" from the same two columns.
 */
enum PriceUnit: string
{
    case Entry = 'entry';
    case Hour = 'hour';
    case PersonHour = 'person_hour';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'intrare',
            self::Hour => 'oră',
            self::PersonHour => 'persoană / oră',
            self::Month => 'lună',
        };
    }

    /**
     * The price as a visitor reads it. Whole amounts lose their decimals, because
     * "45 lei" is what is written on the door, not "45,00 lei".
     */
    public function format(float|string|null $amount): string
    {
        if ($amount === null) {
            return '';
        }

        $amount = (float) $amount;

        $formatted = $amount === floor($amount)
            ? number_format($amount, 0, ',', '.')
            : number_format($amount, 2, ',', '.');

        return $formatted.' lei / '.$this->label();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->label()])
            ->all();
    }
}
