<?php

namespace App\Concerns;

use App\Enums\Weekday;
use App\Models\ClubSport;
use App\Models\Coach;
use App\Models\ScheduleSlot;
use Illuminate\Support\Collection;

/**
 * Shared shaping of club data for the public pages: the club profile and the
 * location page present the same coaches, benefits and weekly schedule.
 */
trait PresentsClubs
{
    /**
     * Avatar gradients cycled through the coaches when they have no photo.
     *
     * @var list<string>
     */
    private const COACH_GRADIENTS = [
        'linear-gradient(135deg,#0C7A4E,#15B877)',
        'linear-gradient(135deg,#5d2c8a,#a25dd1)',
        'linear-gradient(135deg,#0b3a5e,#1d7fb8)',
        'linear-gradient(135deg,#7a3410,#d2691e)',
    ];

    /**
     * @param  Collection<int, Coach>  $coaches
     * @return array<int, array<string, mixed>>
     */
    protected function presentCoaches(Collection $coaches): array
    {
        return $coaches
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
            ->values()
            ->map(function (Coach $coach, int $index): array {
                $sport = $coach->sports->first();

                return [
                    'key' => (string) $coach->id,
                    'name' => $coach->name,
                    'role' => $coach->role ?? '',
                    'solo' => $coach->offers_private_sessions,
                    'gradient' => self::COACH_GRADIENTS[$index % count(self::COACH_GRADIENTS)],
                    'photo' => $coach->photo_url,
                    'bio' => $coach->bio ?? '',
                    'sportIcon' => (string) $sport?->icon,
                    'sportLabel' => $sport?->translated_name ?? '',
                ];
            })
            ->all();
    }

    /**
     * Benefit chips of a sport, plus the 1:1 chip when it is offered.
     *
     * @return array<int, array{label: string, solo: bool}>
     */
    protected function presentTrustChips(?ClubSport $clubSport): array
    {
        if (! $clubSport instanceof ClubSport) {
            return [];
        }

        $chips = $clubSport->benefits
            ->sortBy('sort_order')
            ->map(fn ($benefit): array => [
                'label' => trim(($benefit->icon ?? '').' '.$benefit->label),
                'solo' => false,
            ])
            ->values()
            ->all();

        if ($clubSport->offers_private_sessions) {
            $chips[] = ['label' => '🎯 Antrenament 1:1 disponibil', 'solo' => true];
        }

        return $chips;
    }

    /**
     * Lay slots out on a Monday–Sunday grid.
     *
     * @param  Collection<int, ScheduleSlot>  $slots
     * @return list<array{day: string, slots: list<array{time: string, group: string, coach: string}>}>
     */
    protected function presentWeek(Collection $slots): array
    {
        $byDay = $slots->groupBy(fn (ScheduleSlot $slot): int => $slot->day_of_week->value);

        return collect(Weekday::cases())
            ->map(fn (Weekday $day): array => [
                'day' => $day->short(),
                'slots' => ($byDay->get($day->value) ?? collect())
                    ->sortBy('start_time')
                    ->map(fn (ScheduleSlot $slot): array => [
                        'time' => substr((string) $slot->start_time, 0, 5).'–'.substr((string) $slot->end_time, 0, 5),
                        'group' => $slot->ageGroup?->name ?? '',
                        'coach' => (string) $slot->coach_id,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
