<?php

namespace App\Http\Controllers;

use App\Concerns\PresentsOrganizations;
use App\Enums\ContactType;
use App\Enums\OrganizationType;
use App\Models\Facility;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\OrganizationSport;
use App\Models\OrganizationSportBenefit;
use App\Models\Person;
use App\Models\ScheduleSlot;
use App\Models\Service;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    use PresentsOrganizations;

    /**
     * Social contact types and the short badge shown for each on the profile.
     */
    private const SOCIAL_LABELS = [
        'instagram' => 'ig',
        'facebook' => 'fb',
        'tiktok' => 'tt',
        'youtube' => 'yt',
        'website' => '🌐',
    ];

    /**
     * Benefit labels mentioning these (diacritics-insensitive) describe who a
     * sport is well suited for, not the sport itself — shown in their own
     * "cui se adresează" group instead of getting lost among generic chips.
     *
     * @var list<string>
     */
    private const AUDIENCE_KEYWORDS = [
        'dizabilit', 'senior', 'copil', 'prenatal', 'sarcin', 'incepator',
    ];

    /**
     * Benefit labels mentioning these describe the format a session comes
     * in, alongside the explicit 1:1 flag.
     *
     * @var list<string>
     */
    private const SESSION_FORMAT_KEYWORDS = [
        'grup mic', 'online', 'hibrid', 'individual',
    ];

    /**
     * Show a public club profile with its sports, people and per-location schedule.
     */
    public function show(string $slug): Response
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            // Clubs only. A venue or a practice has its own page, and serving one
            // here would present it as something it is not — with a club's sports
            // and schedule sections, both of which it has none of.
            ->where('type', OrganizationType::Club)
            ->with([
                'contacts',
                'organizationSports.sport',
                'organizationSports.benefits',
                'organizationSports.ageGroups',
                'organizationSports.levels',
                'organizationSports.galleryImages',
                'people.sports',
                'services.specialty',
                'organizationLocations.location.facilities',
                'organizationLocations.organizationLocationSports',
                'scheduleSlots.organizationLocationSport',
                'scheduleSlots.ageGroup',
            ])
            ->firstOrFail();

        return Inertia::render('public/clubs/Show', [
            'club' => $this->presentClub($organization),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentClub(Organization $organization): array
    {
        return [
            'slug' => $organization->slug,
            'name' => $organization->name,
            'representative' => $this->representative($organization),
            'about' => $organization->description ?? '',
            'phone' => $this->phone($organization),
            'socials' => $this->socials($organization),
            'sports' => $this->sports($organization),
            'sportDetails' => $this->sportDetails($organization),
            'people' => $this->presentPeople($organization->people),
            'locationsBySport' => $this->locationsBySport($organization),
            // What the club sells one appointment at a time — the massage at a
            // pilates studio. An extra alongside the programme, never a second
            // reason to come, and never part of discovery.
            'extras' => $this->extras($organization),
        ];
    }

    /**
     * Paid extras that are not the sport: the studio's massage, the club's
     * recovery session. Real offers with a price and a duration, rather than a
     * chip somebody happened to word a certain way.
     *
     * @return list<array<string, mixed>>
     */
    private function extras(Organization $organization): array
    {
        return array_values($organization->services
            ->sortBy('sort_order')
            ->map(function (Service $service): array {
                $specialty = $service->specialty;

                return [
                    'key' => (string) $service->getKey(),
                    'icon' => $specialty === null ? '💆' : (string) $specialty->icon,
                    'name' => $service->name,
                    'specialty' => $specialty === null ? null : $specialty->translated_name,
                    'price' => $service->priceLabel(),
                    'duration' => $service->durationLabel(),
                    'description' => $service->description ?? '',
                ];
            })
            ->values()
            ->all());
    }

    private function representative(Organization $organization): string
    {
        $primary = $organization->people->firstWhere('is_primary', true) ?? $organization->people->first();

        if (! $primary instanceof Person) {
            return '';
        }

        return $primary->role ? "{$primary->name}, {$primary->role}" : $primary->name;
    }

    private function phone(Organization $organization): ?string
    {
        return $organization->contacts->firstWhere('type', ContactType::Phone)?->value;
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function socials(Organization $organization): array
    {
        return $organization->contacts
            ->map(fn ($contact): ?array => isset(self::SOCIAL_LABELS[$contact->type->value])
                ? ['label' => self::SOCIAL_LABELS[$contact->type->value], 'url' => (string) $contact->value]
                : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string, color: string|null, locationCount: int}>
     */
    private function sports(Organization $organization): array
    {
        return $organization->organizationSports
            ->sortBy('sort_order')
            ->map(fn (OrganizationSport $organizationSport): array => [
                'key' => $organizationSport->sport->slug,
                'label' => $organizationSport->sport->translated_name,
                'icon' => (string) $organizationSport->sport->icon,
                'color' => $organizationSport->sport->color,
                'locationCount' => $this->locationCountForSport($organization, $organizationSport->sport_id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sportDetails(Organization $organization): array
    {
        return $organization->organizationSports
            ->sortBy('sort_order')
            ->mapWithKeys(function (OrganizationSport $organizationSport) use ($organization): array {
                $highlights = $this->presentHighlights(
                    $organizationSport,
                    $this->sportHasAccessibleLocation($organization, $organizationSport->sport_id),
                );

                return [
                    $organizationSport->sport->slug => [
                        'icon' => (string) $organizationSport->sport->icon,
                        'title' => 'Despre '.mb_strtolower($organizationSport->sport->translated_name).' la '.$organization->name,
                        'trustChips' => $highlights['general'],
                        'sessionFormat' => $highlights['sessionFormat'],
                        'audience' => $highlights['audience'],
                        'ages' => $organizationSport->ageGroups->sortBy('sort_order')->pluck('name')->values()->all(),
                        'levels' => $organizationSport->levels->sortBy('sort_order')->pluck('name')->values()->all(),
                        'gallery' => $organizationSport->galleryImages->map(fn ($image): string => $image->url)->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Sort a sport's benefit chips into the groups the club page highlights
     * separately, so an accessibility note doesn't disappear into a wall of
     * generic chips.
     *
     * Non-sport extras used to be guessed here from keywords in a free-text label,
     * which had no price, no duration, and broke on the first wording nobody
     * anticipated. They are now real offers — see `extras()`.
     *
     * @return array{general: list<string>, sessionFormat: list<string>, audience: list<string>}
     */
    private function presentHighlights(OrganizationSport $organizationSport, bool $locationIsAccessible): array
    {
        $general = [];
        $sessionFormat = [];
        $audience = [];

        foreach ($organizationSport->benefits->sortBy('sort_order') as $benefit) {
            /** @var OrganizationSportBenefit $benefit */
            $label = trim(($benefit->icon ?? '').' '.$benefit->label);
            $normalized = Str::of($benefit->label)->lower()->ascii()->toString();

            if (Str::contains($normalized, self::AUDIENCE_KEYWORDS)) {
                $audience[] = $label;
            } elseif (Str::contains($normalized, self::SESSION_FORMAT_KEYWORDS)) {
                $sessionFormat[] = $label;
            } else {
                $general[] = $label;
            }
        }

        if ($organizationSport->offers_private_sessions) {
            $sessionFormat[] = '🎯 Antrenament 1:1 disponibil';
        }

        if ($locationIsAccessible && ! collect($audience)->contains(fn (string $a): bool => Str::contains(Str::of($a)->lower()->ascii()->toString(), 'dizabilit'))) {
            $audience[] = '♿ Acces persoane cu dizabilități';
        }

        return [
            'general' => $general,
            'sessionFormat' => $sessionFormat,
            'audience' => $audience,
        ];
    }

    /**
     * Whether any of the club's locations teaching this sport has marked
     * itself wheelchair/disability accessible.
     */
    private function sportHasAccessibleLocation(Organization $organization, int $sportId): bool
    {
        return $organization->organizationLocations
            ->filter(fn (OrganizationLocation $organizationLocation): bool => $organizationLocation->organizationLocationSports->contains('sport_id', $sportId))
            ->contains(fn (OrganizationLocation $organizationLocation): bool => $organizationLocation->location->facilities
                ->contains(fn (Facility $facility): bool => Str::contains(
                    Str::of($facility->name)->lower()->ascii()->toString(),
                    'dizabilit',
                )));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function locationsBySport(Organization $organization): array
    {
        $result = [];

        foreach ($organization->organizationSports->sortBy('sort_order') as $organizationSport) {
            $sportId = $organizationSport->sport_id;

            $result[$organizationSport->sport->slug] = array_values($organization->organizationLocations
                ->filter(fn (OrganizationLocation $organizationLocation): bool => $organizationLocation->organizationLocationSports->contains('sport_id', $sportId))
                ->map(fn (OrganizationLocation $organizationLocation): array => [
                    'slug' => $organizationLocation->location->slug,
                    'name' => $organizationLocation->location->name,
                    'city' => Str::upper($organizationLocation->location->city ?? ''),
                    'schedule' => $this->schedule($organization, $organizationLocation, $sportId),
                ])
                ->all());
        }

        return $result;
    }

    private function locationCountForSport(Organization $organization, int $sportId): int
    {
        return $organization->organizationLocations
            ->filter(fn (OrganizationLocation $organizationLocation): bool => $organizationLocation->organizationLocationSports->contains('sport_id', $sportId))
            ->count();
    }

    /**
     * Build the Monday–Sunday grid for one location and sport.
     *
     * @return list<array{day: string, slots: list<array{time: string, group: string, level: string, coach: string, foreign: bool, otherClubs: list<array{name: string, key: string}>}>}>
     */
    private function schedule(Organization $organization, OrganizationLocation $organizationLocation, int $sportId): array
    {
        return $this->presentWeek(
            $organization->scheduleSlots->filter(
                fn (ScheduleSlot $slot): bool => $slot->organizationLocationSport->organization_location_id === $organizationLocation->id
                    && $slot->organizationLocationSport->sport_id === $sportId,
            ),
        );
    }
}
