<?php

namespace App\Enums;

/**
 * What kind of thing an organization is.
 *
 * This is the **primary identity** only: it decides the public URL and the shape
 * of the public page. It does not decide what may be published — offers are
 * additive, so a club that owns its hall may also rent it out, without needing a
 * second account for the same company.
 */
enum OrganizationType: string
{
    case Club = 'club';
    case Venue = 'venue';
    case Practice = 'practice';

    public function label(): string
    {
        return match ($this) {
            self::Club => 'Club sportiv',
            self::Venue => 'Bază sportivă',
            self::Practice => 'Cabinet / clinică',
        };
    }

    /**
     * How visitors are told about this kind. The umbrella term "organizație" is
     * internal — a padel court operator does not want to be listed as a club, and
     * neither does a physiotherapist.
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Club => 'Cluburi sportive',
            self::Venue => 'Baze sportive',
            self::Practice => 'Cabinete și clinici',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
