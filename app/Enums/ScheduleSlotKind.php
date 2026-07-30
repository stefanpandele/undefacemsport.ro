<?php

namespace App\Enums;

/**
 * Which kind of weekly interval a schedule slot is.
 *
 * The two share a table because they share a shape — a weekday and a time range
 * — which is what lets one query answer "what is happening near me right now"
 * across both training sessions and open courts. They differ in meaning: a
 * training happens then, whereas an access interval means you may turn up then.
 */
enum ScheduleSlotKind: string
{
    case Training = 'training';
    case Access = 'access';

    public function label(): string
    {
        return match ($this) {
            self::Training => 'Antrenament',
            self::Access => 'Program de acces',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $kind): array => [$kind->value => $kind->label()])
            ->all();
    }
}
