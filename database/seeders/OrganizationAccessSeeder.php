<?php

namespace Database\Seeders;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Enums\FacilityStatus;
use App\Enums\Weekday;
use App\Models\AgeGroup;
use App\Models\Facility;
use App\Models\Organization;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationAccessSeeder extends Seeder
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
                'name' => 'Reprezentant Organization Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $organization = Organization::firstWhere('slug', 'clubul-demo')
            ?? Organization::factory()->pro()->create([
                'name' => 'Clubul Demo',
                'slug' => 'clubul-demo',
            ]);

        $organization->addMember($owner);
        $this->seedShowcaseSport($organization);

        $owner = User::updateOrCreate(
            ['email' => 'club2@email.com'],
            [
                'name' => 'Reprezentant Organization 2 Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $organization = Organization::firstWhere('slug', 'clubul-2-demo')
            ?? Organization::factory()->pro()->create([
                'name' => 'Clubul 2 Demo',
                'slug' => 'clubul-2-demo',
            ]);

        $organization->addMember($owner);
    }

    /**
     * Give the demo club one fully fleshed-out sport so the public profile
     * (/cluburi/clubul-demo) shows every highlight group at once: the 1:1
     * session-format chip, the "cui se adresează" chips (beginners, prenatal,
     * accessibility), and a non-sport service (massage) — the exact pilates
     * + massage case this was designed around. Idempotent, safe to re-run.
     */
    private function seedShowcaseSport(Organization $organization): void
    {
        $sport = Sport::updateOrCreate(
            ['slug' => 'pilates'],
            ['name' => 'Pilates', 'icon' => '🧘', 'color' => '#A25DD1'],
        );

        $organizationSport = $organization->organizationSports()->updateOrCreate(
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
            $organizationSport->benefits()->updateOrCreate(
                ['label' => $benefit['label']],
                ['icon' => $benefit['icon'], 'sort_order' => $benefit['sort_order']],
            );
        }

        $ageGroup = AgeGroup::firstOrCreate(['name' => 'Adulți'], ['sort_order' => 99]);
        $organizationSport->ageGroups()->syncWithoutDetaching([$ageGroup->id]);

        $person = $organization->people()->updateOrCreate(
            ['name' => 'Ioana Marinescu'],
            [
                'role' => 'Instructor Pilates',
                'bio' => 'Certificată internațional, specializată în recuperare și antrenament individual.',
                'offers_private_sessions' => true,
                'is_primary' => true,
                'sort_order' => 0,
            ],
        );
        $person->sports()->syncWithoutDetaching([$sport->id]);

        $organizationLocation = $organization->syncLocation(
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
        $organizationLocation->location->facilities()->syncWithoutDetaching([$accessible->id]);

        // One suggestion left unreviewed, so /admin has something in the
        // approval queue and the public pages have a case that stays hidden.
        $sauna = Facility::updateOrCreate(
            ['name' => 'Saună'],
            [
                'icon' => '🧖',
                'status' => FacilityStatus::Pending,
                'suggested_by_organization_id' => $organization->getKey(),
                'sort_order' => 100,
            ],
        );
        $organizationLocation->location->facilities()->syncWithoutDetaching([$sauna->id]);

        $organizationLocationSport = OrganizationLocationSport::query()
            ->where('organization_location_id', $organizationLocation->id)
            ->where('sport_id', $sport->id)
            ->firstOrFail();

        ScheduleSlot::updateOrCreate(
            [
                'organization_location_sport_id' => $organizationLocationSport->id,
                'day_of_week' => Weekday::Monday,
                'start_time' => '18:00',
            ],
            [
                'organization_id' => $organization->id,
                'end_time' => '19:00',
                'age_group_id' => $ageGroup->id,
                'person_id' => $person->id,
            ],
        );

        $organization->contacts()->updateOrCreate(
            ['type' => ContactType::Phone],
            ['role' => ContactRole::General, 'value' => '0722123456', 'sort_order' => 0],
        );
    }
}
