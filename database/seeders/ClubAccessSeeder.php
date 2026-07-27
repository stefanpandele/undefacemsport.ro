<?php

namespace Database\Seeders;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Enums\FacilityStatus;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocationSport;
use App\Models\Facility;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClubAccessSeeder extends Seeder
{
    /**
     * A known club representative (club@email.com / password) who owns a demo
     * Pro club, for easy /club panel login in local dev. Parallels the admin
     * seeders (super admin via allowlist, adminl via AdminAccessSeeder).
     */
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'club@email.com'],
            [
                'name' => 'Reprezentant Club Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $club = Club::firstWhere('slug', 'clubul-demo')
            ?? Club::factory()->pro()->create([
                'name' => 'Clubul Demo',
                'slug' => 'clubul-demo',
            ]);

        $club->addMember($owner);
        $this->seedShowcaseSport($club);

        $owner = User::updateOrCreate(
            ['email' => 'club2@email.com'],
            [
                'name' => 'Reprezentant Club 2 Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $club = Club::firstWhere('slug', 'clubul-2-demo')
            ?? Club::factory()->pro()->create([
                'name' => 'Clubul 2 Demo',
                'slug' => 'clubul-2-demo',
            ]);

        $club->addMember($owner);
    }

    /**
     * Give the demo club one fully fleshed-out sport so the public profile
     * (/cluburi/clubul-demo) shows every highlight group at once: the 1:1
     * session-format chip, the "cui se adresează" chips (beginners, prenatal,
     * accessibility), and a non-sport service (massage) — the exact pilates
     * + massage case this was designed around. Idempotent, safe to re-run.
     */
    private function seedShowcaseSport(Club $club): void
    {
        $sport = Sport::updateOrCreate(
            ['slug' => 'pilates'],
            ['name' => 'Pilates', 'icon' => '🧘', 'color' => '#A25DD1'],
        );

        $clubSport = $club->clubSports()->updateOrCreate(
            ['sport_id' => $sport->id],
            ['offers_private_sessions' => true, 'sort_order' => 0],
        );

        foreach ([
            ['icon' => '🏅', 'label' => 'Instructor certificat', 'sort_order' => 0],
            ['icon' => '🌱', 'label' => 'Grupe pentru începători', 'sort_order' => 1],
            ['icon' => '🤰', 'label' => 'Clase prenatal', 'sort_order' => 2],
            ['icon' => '💆', 'label' => 'Masaj terapeutic după antrenament', 'sort_order' => 3],
            ['icon' => '🧖', 'label' => 'Saună inclusă', 'sort_order' => 4],
        ] as $benefit) {
            $clubSport->benefits()->updateOrCreate(
                ['label' => $benefit['label']],
                ['icon' => $benefit['icon'], 'sort_order' => $benefit['sort_order']],
            );
        }

        $ageGroup = AgeGroup::firstOrCreate(['name' => 'Adulți'], ['sort_order' => 99]);
        $clubSport->ageGroups()->syncWithoutDetaching([$ageGroup->id]);

        $coach = $club->coaches()->updateOrCreate(
            ['name' => 'Ioana Marinescu'],
            [
                'role' => 'Instructor Pilates',
                'bio' => 'Certificată internațional, specializată în recuperare și antrenament individual.',
                'offers_private_sessions' => true,
                'is_primary' => true,
                'sort_order' => 0,
            ],
        );
        $coach->sports()->syncWithoutDetaching([$sport->id]);

        $clubLocation = $club->syncLocation(
            [
                'county' => 'București',
                'city' => 'Sectorul 1',
                'address' => 'Str. Studioului 12',
                'name' => 'Studioul Demo',
                // Without coordinates the location has no pin on the explore map.
                'latitude' => 44.4396,
                'longitude' => 26.0963,
            ],
            [$sport->id],
        );

        $accessible = Facility::firstOrCreate(
            ['name' => 'Acces persoane cu dizabilități'],
            ['icon' => '♿', 'sort_order' => 99],
        );
        $clubLocation->location->facilities()->syncWithoutDetaching([$accessible->id]);

        // One suggestion left unreviewed, so /admin has something in the
        // approval queue and the public pages have a case that stays hidden.
        $sauna = Facility::updateOrCreate(
            ['name' => 'Saună'],
            [
                'icon' => '🧖',
                'status' => FacilityStatus::Pending,
                'suggested_by_club_id' => $club->getKey(),
                'sort_order' => 100,
            ],
        );
        $clubLocation->location->facilities()->syncWithoutDetaching([$sauna->id]);

        $clubLocationSport = ClubLocationSport::query()
            ->where('club_location_id', $clubLocation->id)
            ->where('sport_id', $sport->id)
            ->firstOrFail();

        ScheduleSlot::updateOrCreate(
            [
                'club_location_sport_id' => $clubLocationSport->id,
                'day_of_week' => Weekday::Monday,
                'start_time' => '18:00',
            ],
            [
                'club_id' => $club->id,
                'end_time' => '19:00',
                'age_group_id' => $ageGroup->id,
                'coach_id' => $coach->id,
            ],
        );

        $club->contacts()->updateOrCreate(
            ['type' => ContactType::Phone],
            ['role' => ContactRole::General, 'value' => '0722123456', 'sort_order' => 0],
        );
    }
}
