<?php

namespace Database\Seeders;

use App\Enums\LocationCorrectionField;
use App\Enums\LocationCorrectionStatus;
use App\Models\Club;
use App\Models\Location;
use App\Models\LocationCorrection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LocationCorrectionSeeder extends Seeder
{
    /**
     * The kinds of thing clubs actually report about a shared place they cannot
     * edit: a misspelled name, a street number that was never entered, diacritics
     * dropped by whoever typed it first, a suburb filed under the wrong city.
     *
     * @var list<array{field: string, transform: string}>
     */
    private const COMPLAINTS = [
        ['field' => 'name', 'transform' => 'suffix_hall'],
        ['field' => 'name', 'transform' => 'strip_diacritics'],
        ['field' => 'address', 'transform' => 'add_number'],
        ['field' => 'address', 'transform' => 'expand_street'],
        ['field' => 'city', 'transform' => 'same'],
        ['field' => 'county', 'transform' => 'same'],
    ];

    /**
     * Seed a review queue with enough volume and variety that the admin screen is
     * worth looking at: pending requests to act on, plus a history of applied and
     * rejected ones so the tabs are not empty.
     *
     * Idempotent: a club never files the same proposal for the same field twice.
     */
    public function run(): void
    {
        // Deliberately ordered, not random: which club complains about which
        // field of which location has to come out the same on every run, or
        // re-seeding files a fresh batch of proposals beside the old ones.
        $locations = Location::query()->orderBy('id')->limit(30)->get();
        $clubs = Club::query()->orderBy('id')->limit(12)->get();
        $reviewers = User::query()->where('is_admin', true)->orderBy('id')->get();

        if ($locations->isEmpty() || $clubs->isEmpty()) {
            return;
        }

        $index = 0;

        foreach ($locations as $position => $location) {
            // One to three complaints per location, so some places look contested
            // and others merely nudged.
            foreach (range(0, $position % 3) as $ignored) {
                $complaint = self::COMPLAINTS[$index % count(self::COMPLAINTS)];
                $club = $clubs[$index % $clubs->count()];
                $field = LocationCorrectionField::from($complaint['field']);

                $suggested = $this->suggestedValue($location, $field, $complaint['transform']);

                if (blank($suggested) || $suggested === $location->{$field->value}) {
                    $index++;

                    continue;
                }

                $this->fileCorrection($location, $club, $field, $suggested, $index, $reviewers);

                $index++;
            }
        }
    }

    /**
     * @param  Collection<int, User>  $reviewers
     */
    private function fileCorrection(
        Location $location,
        Club $club,
        LocationCorrectionField $field,
        string $suggested,
        int $index,
        Collection $reviewers,
    ): void {
        $correction = LocationCorrection::firstOrCreate(
            [
                'location_id' => $location->getKey(),
                'club_id' => $club->getKey(),
                'field' => $field,
            ],
            [
                'suggested_value' => $suggested,
                'note' => fake()->boolean(70) ? fake()->sentence() : null,
            ],
        );

        if (! $correction->wasRecentlyCreated) {
            return;
        }

        // Roughly half stay pending, so the queue has work in it; the rest are
        // history, which is what makes the applied/rejected tabs meaningful.
        $status = match ($index % 4) {
            0 => LocationCorrectionStatus::Approved,
            1 => LocationCorrectionStatus::Rejected,
            default => LocationCorrectionStatus::Pending,
        };

        if ($status === LocationCorrectionStatus::Pending || $reviewers->isEmpty()) {
            return;
        }

        // Applied history is written directly rather than through apply(): the
        // seeder is recording that a review happened, not re-running it, and
        // rewriting 30 locations' names would undo the curated seed data.
        $correction->forceFill([
            'status' => $status,
            'reviewed_at' => now()->subDays(fake()->numberBetween(1, 60)),
            'reviewed_by' => $reviewers->random()->getKey(),
        ])->save();
    }

    /**
     * A plausible corrected value for the given field.
     */
    private function suggestedValue(Location $location, LocationCorrectionField $field, string $transform): ?string
    {
        $current = (string) $location->{$field->value};

        if (blank($current)) {
            return null;
        }

        return match ($transform) {
            'suffix_hall' => $current.' — Sala 2',
            'strip_diacritics' => str_replace(
                ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
                ['a', 'a', 'i', 's', 't', 'A', 'A', 'I', 'S', 'T'],
                $current,
            ),
            'add_number' => $current.', corp B',
            'expand_street' => str_replace(['Str. ', 'Bd. '], ['Strada ', 'Bulevardul '], $current),
            default => $current,
        };
    }
}
