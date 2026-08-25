<?php

namespace App\Concerns;

use App\Enums\Weekday;
use App\Models\OrganizationSport;
use App\Models\Person;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use Illuminate\Support\Collection;

/**
 * Shared shaping of club data for the public pages: the club profile and the
 * location page present the same people, benefits and weekly schedule.
 */
trait PresentsOrganizations
{
    /**
     * Avatar gradients cycled through the people when they have no photo.
     *
     * @var list<string>
     */
    private const PERSON_GRADIENTS = [
        'linear-gradient(135deg,#0C7A4E,#15B877)',
        'linear-gradient(135deg,#5d2c8a,#a25dd1)',
        'linear-gradient(135deg,#0b3a5e,#1d7fb8)',
        'linear-gradient(135deg,#7a3410,#d2691e)',
    ];

    /**
     * @param  Collection<int, Person>  $people
     * @return array<int, array<string, mixed>>
     */
    protected function presentPeople(Collection $people): array
    {
        return $people
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
            ->values()
            ->map(function (Person $person, int $index): array {
                $sport = $person->sports->first();

                return [
                    'key' => (string) $person->id,
                    'name' => $person->name,
                    'role' => $person->role ?? '',
                    // Tied to the sport the card shows: the claim lives on the
                    // person-and-sport pair now, not on the person.
                    'solo' => $sport instanceof Sport && $person->offersPrivateSessionsIn($sport->getKey()),
                    'gradient' => self::PERSON_GRADIENTS[$index % count(self::PERSON_GRADIENTS)],
                    'photo' => $person->photo_url,
                    'bio' => $person->bio ?? '',
                    'sportIcon' => (string) $sport?->icon,
                    // `??` already short-circuits a null $sport, so `?->` adds nothing.
                    'sportLabel' => $sport->translated_name ?? '',
                ];
            })
            ->all();
    }

    /**
     * Benefit chips of a sport, plus the 1:1 chip when somebody here offers it.
     *
     * @return array<int, array{label: string, solo: bool}>
     */
    protected function presentTrustChips(?OrganizationSport $organizationSport, bool $offersPrivateSessions = false): array
    {
        if (! $organizationSport instanceof OrganizationSport) {
            return [];
        }

        $chips = $organizationSport->benefits
            ->sortBy('sort_order')
            ->map(fn ($benefit): array => [
                'label' => trim(($benefit->icon ?? '').' '.$benefit->label),
                'solo' => false,
            ])
            ->values()
            ->all();

        if ($offersPrivateSessions) {
            $chips[] = ['label' => '🎯 Antrenament 1:1 disponibil', 'solo' => true];
        }

        return $chips;
    }

    /**
     * Lay slots out on a Monday–Sunday grid.
     *
     * When `$occupancy` is given (the location page), the grid also carries who
     * else trains in the same hall: every slot lists the other clubs sharing its
     * interval, and intervals only other clubs use are added as anonymous slots
     * so a visitor sees how busy the venue really is.
     *
     * @param  Collection<int, ScheduleSlot>  $slots
     * @param  array<int, array<string, list<array{name: string, key: string}>>>  $occupancy  day value => interval => other clubs
     *                                                                                        `coach` stays the key the Vue schedule grid reads: the person running a
     *                                                                                        training session is a coach, whatever else an organization's people do.
     * @return list<array{day: string, slots: list<array{time: string, group: string, level: string, coach: string, foreign: bool, otherClubs: list<array{name: string, key: string}>}>}>
     */
    protected function presentWeek(Collection $slots, array $occupancy = []): array
    {
        $byDay = $slots->groupBy(fn (ScheduleSlot $slot): int => $slot->day_of_week->value);

        return array_values(collect(Weekday::cases())
            ->map(function (Weekday $day) use ($byDay, $occupancy): array {
                $others = $occupancy[$day->value] ?? [];

                $own = ($byDay->get($day->value) ?? collect())
                    ->map(fn (ScheduleSlot $slot): array => [
                        'time' => $this->interval($slot),
                        'group' => $slot->ageGroup->name ?? '',
                        'level' => $slot->level->name ?? '',
                        'coach' => (string) $slot->person_id,
                        'foreign' => false,
                        'otherClubs' => $others[$this->interval($slot)] ?? [],
                    ])
                    ->values();

                // Intervals this club does not train in, but the hall is taken.
                $foreign = collect($others)
                    ->except($own->pluck('time')->all())
                    ->map(fn (array $clubs, string $time): array => [
                        'time' => $time,
                        'group' => '',
                        'level' => '',
                        'coach' => '',
                        'foreign' => true,
                        'otherClubs' => $clubs,
                    ])
                    ->values();

                return [
                    'day' => $day->short(),
                    // Zero-padded 24h times, so a plain string sort is chronological.
                    'slots' => array_values($own->concat($foreign)->sortBy('time')->all()),
                ];
            })
            ->all());
    }

    /**
     * A slot's interval as shown to visitors, e.g. "17:00–18:30". Doubles as the
     * key clubs are matched on when working out who shares a hall.
     */
    protected function interval(ScheduleSlot $slot): string
    {
        return substr((string) $slot->start_time, 0, 5).'–'.substr((string) $slot->end_time, 0, 5);
    }
}
