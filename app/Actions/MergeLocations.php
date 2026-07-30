<?php

namespace App\Actions;

use App\Models\Location;
use App\Models\LocationRedirect;
use App\Models\OrganizationLocation;
use App\Models\OrganizationLocationSport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fold one duplicate location into another.
 *
 * Exact-string matching means "Calea Dorobanților 5A" and "Calea Dorobantilor
 * nr. 5A" can both exist, and every public count on the platform derives from
 * locations — so a duplicate pair quietly halves a venue's schedule rather than
 * failing loudly. This is the repair.
 *
 * Three things a naive implementation breaks, all of them guarded here:
 *
 *  - UNIQUE(organization_id, location_id) collides when one organization is
 *    present at both places, and UNIQUE(organization_location_id, sport_id)
 *    collides one level below it. Those rows have to be *merged*, not repointed.
 *  - `location_id` is cascadeOnDelete all the way down to schedule slots, so
 *    deleting the loser before repointing silently destroys schedules. Nothing
 *    is deleted until everything has moved.
 *  - the loser's slug is indexed and shared, so it is kept as a redirect.
 */
class MergeLocations
{
    /**
     * Move everything from `$loser` onto `$winner` and delete the loser.
     *
     * @return array<string, int> what moved, for the confirmation and the log
     */
    public function handle(Location $winner, Location $loser): array
    {
        if ($winner->is($loser)) {
            throw new InvalidArgumentException('A location cannot be merged into itself.');
        }

        return DB::transaction(function () use ($winner, $loser): array {
            $moved = [
                'presences' => $this->movePresences($winner, $loser),
                'spaces' => $this->moveSpaces($winner, $loser),
                'facilities' => $this->moveFacilities($winner, $loser),
                'corrections' => $loser->corrections()->update(['location_id' => $winner->getKey()]),
            ];

            // Only now, with nothing left pointing at it, and with its slug kept
            // so the URL survives.
            $this->keepSlug($winner, $loser);
            $loser->delete();

            return $moved;
        });
    }

    /**
     * Move each organization's presence across, merging rather than repointing
     * where the organization is already present at the winner.
     */
    private function movePresences(Location $winner, Location $loser): int
    {
        $moved = 0;

        foreach ($loser->organizationLocations as $presence) {
            $existing = OrganizationLocation::query()
                ->where('organization_id', $presence->organization_id)
                ->where('location_id', $winner->getKey())
                ->first();

            if ($existing === null) {
                $presence->update(['location_id' => $winner->getKey()]);
                $moved++;

                continue;
            }

            $this->mergePresence($existing, $presence);
            $moved++;
        }

        return $moved;
    }

    /**
     * Fold one presence into another: the union of the sports, with the schedule
     * of each following its sport.
     */
    private function mergePresence(OrganizationLocation $keep, OrganizationLocation $drop): void
    {
        foreach ($drop->organizationLocationSports as $sport) {
            $existing = OrganizationLocationSport::query()
                ->where('organization_location_id', $keep->getKey())
                ->where('sport_id', $sport->sport_id)
                ->first();

            if ($existing === null) {
                $sport->update(['organization_location_id' => $keep->getKey()]);

                continue;
            }

            // Same club, same sport, twice at what turns out to be one place: the
            // slots join the surviving row, and the empty husk goes.
            $sport->scheduleSlots()->update(['organization_location_sport_id' => $existing->getKey()]);
            $sport->delete();
        }

        // Spaces the organization operated through the dropped presence follow it.
        $drop->spaces()->update(['organization_location_id' => $keep->getKey()]);

        $drop->delete();
    }

    private function moveSpaces(Location $winner, Location $loser): int
    {
        return $loser->spaces()->update(['location_id' => $winner->getKey()]);
    }

    /**
     * Amenities are only ever added, never removed on somebody else's behalf, and
     * a photo already at the winner is not replaced by one from the loser.
     */
    private function moveFacilities(Location $winner, Location $loser): int
    {
        // The pivot is read from the join table rather than through the relation:
        // `photo_path` is what has to survive, and a hydrated Facility model does
        // not carry it in a way static analysis can follow.
        $winnerPhotos = DB::table('facility_location')
            ->where('location_id', $winner->getKey())
            ->pluck('photo_path', 'facility_id');

        $loserPhotos = DB::table('facility_location')
            ->where('location_id', $loser->getKey())
            ->pluck('photo_path', 'facility_id');

        $moved = 0;

        foreach ($loserPhotos as $facilityId => $photo) {
            if (! $winnerPhotos->has($facilityId)) {
                $winner->facilities()->attach($facilityId, ['photo_path' => $photo]);
                $moved++;

                continue;
            }

            // The loser's proof photo fills a gap, but never overwrites one.
            if (blank($winnerPhotos->get($facilityId)) && filled($photo)) {
                $winner->facilities()->updateExistingPivot($facilityId, ['photo_path' => $photo]);
            }
        }

        return $moved;
    }

    /**
     * Keep the loser's slug pointing at the winner, and carry over any redirect
     * the loser had itself collected — a slug merged twice must not lose its way.
     */
    private function keepSlug(Location $winner, Location $loser): void
    {
        LocationRedirect::query()
            ->where('location_id', $loser->getKey())
            ->update(['location_id' => $winner->getKey()]);

        // The winner's own slug is never a redirect: it would shadow the real page.
        if ($loser->slug === $winner->slug) {
            return;
        }

        LocationRedirect::updateOrCreate(
            ['slug' => $loser->slug],
            ['location_id' => $winner->getKey()],
        );
    }

    /**
     * What a merge would move, without moving it — for the confirmation dialog.
     *
     * @return array<string, int>
     */
    public function preview(Location $winner, Location $loser): array
    {
        return [
            'presences' => $loser->organizationLocations()->count(),
            'sports' => OrganizationLocationSport::query()
                ->whereIn('organization_location_id', $loser->organizationLocations()->pluck('id'))
                ->count(),
            'slots' => $loser->organizationLocations()
                ->join('organization_location_sport', 'organization_location_sport.organization_location_id', '=', 'organization_location.id')
                ->join('schedule_slots', 'schedule_slots.organization_location_sport_id', '=', 'organization_location_sport.id')
                ->count(),
            'spaces' => $loser->spaces()->count(),
            'facilities' => $loser->facilities()->count(),
            'corrections' => $loser->corrections()->count(),
        ];
    }
}
