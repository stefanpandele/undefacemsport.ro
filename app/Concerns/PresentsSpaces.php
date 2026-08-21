<?php

namespace App\Concerns;

use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Models\Space;
use Illuminate\Support\Carbon;

/**
 * Shared shaping of a space for the public pages.
 *
 * The location page asks "what can I do at this address" and the organization
 * page asks "what does this company offer", but a pool answers both with the
 * same card — hours, price and whether it is open right now.
 */
trait PresentsSpaces
{
    /**
     * @return array<string, mixed>
     */
    protected function presentSpace(Space $space, SpaceAccessMode $mode): array
    {
        $today = Weekday::fromDate(Carbon::now())->value;

        return [
            'id' => $space->getKey(),
            'name' => $space->name,
            'operator' => $space->organizationLocation?->organization->name,
            'unmanaged' => ! $space->isManaged(),
            'price' => $space->priceFromLabel($mode),
            'priceNotes' => $space->priceNotesFor($mode),
            'isFree' => $space->isFree($mode),
            'capacity' => $space->capacity,
            'isIndoor' => $space->is_indoor,
            'hasFloodlights' => $space->has_floodlights,
            'surface' => $space->surface?->name,
            'openNow' => $space->openAt(null, $mode),
            'closesAt' => $space->closesAt(null, $mode),
            'lastVerified' => $space->last_verified_at?->diffForHumans(),
            // Nobody keeps a timetable for a park court. Saying so beats a week of
            // "închis", which is a claim nobody made.
            'hoursKnown' => $space->hasKnownHours($mode),
            'today' => $space->hoursByDay($mode)[$today] ?? [],
            'week' => $this->weekHours($space, $mode),
        ];
    }

    /**
     * Opening hours as a week, one row per day, closed days included so a visitor
     * can see the shape of the week rather than infer it from gaps. Empty when no
     * tariff names its hours — an unknown timetable is not seven closed days.
     *
     * @return list<array{day: string, hours: string}>
     */
    protected function weekHours(Space $space, SpaceAccessMode $mode): array
    {
        if (! $space->hasKnownHours($mode)) {
            return [];
        }

        $byDay = $space->hoursByDay($mode);

        return array_values(collect(Weekday::cases())
            ->map(function (Weekday $day) use ($byDay): array {
                $intervals = collect($byDay[$day->value] ?? [])
                    ->map(fn (array $interval): string => $interval['start'].'–'.$interval['end'])
                    ->implode(', ');

                return [
                    'day' => $day->label(),
                    'hours' => $intervals === '' ? 'închis' : $intervals,
                ];
            })
            ->all());
    }
}
