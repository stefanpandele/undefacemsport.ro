<?php

namespace App\Http\Controllers;

use App\Concerns\PresentsOrganizations;
use App\Concerns\PresentsSpaces;
use App\Enums\ContactType;
use App\Enums\FacilityStatus;
use App\Enums\LocationWay;
use App\Enums\SpaceAccessMode;
use App\Models\Facility;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\OrganizationSport;
use App\Models\OrganizationSportBenefit;
use App\Models\Person;
use App\Models\ScheduleSlot;
use App\Models\Service;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use PresentsOrganizations, PresentsSpaces;

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
     * One organization, whatever it turns out to be.
     *
     * There is no second page and no type to route on: a pool that also runs a
     * swimming club would otherwise need two addresses for one company. What it
     * publishes decides which tabs exist, and the fragment decides which one
     * opens — `/la/aqua#agrement` for somebody who arrived from the leisure
     * listing.
     */
    public function show(string $slug): Response
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->with([
                'contacts',
                'organizationSports.sport',
                'organizationSports.benefits',
                'organizationSports.ageGroups',
                'organizationSports.levels',
                'organizationSports.galleryImages',
                'people.sports',
                'services.specialty',
                'services.person',
                'services.sports',
                'organizationLocations.location.facilities',
                'organizationLocations.organizationLocationSports',
                'organizationLocations.spaces.sport',
                'organizationLocations.spaces.accessSlots',
                'scheduleSlots.organizationLocationSport',
                'scheduleSlots.ageGroup',
            ])
            ->firstOrFail();

        return Inertia::render('public/organizations/Show', [
            'organization' => $this->present($organization),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Organization $organization): array
    {
        $courses = $this->courses($organization);
        $leisure = $this->leisure($organization);
        $services = $this->services($organization);

        return [
            'slug' => $organization->slug,
            'name' => $organization->name,
            'representative' => $this->representative($organization),
            'about' => $organization->description ?? '',
            'phone' => $this->phone($organization),
            'socials' => $this->socials($organization),
            'people' => $this->presentPeople($organization->people),
            'locations' => $this->locations($organization),
            // The same three words the badges and the listings use, plus the
            // services. Only the tabs with something behind them: a tab that
            // opens on an empty panel is a promise the page cannot keep.
            'tabs' => $this->tabs($courses, $leisure, $services),
            'courses' => $courses,
            'leisure' => $leisure,
            'services' => $services,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @param  list<array<string, mixed>>  $leisure
     * @param  list<array<string, mixed>>  $services
     * @return list<array{key: string, label: string}>
     */
    private function tabs(array $courses, array $leisure, array $services): array
    {
        $tabs = [];

        if ($courses !== []) {
            $tabs[] = [
                'key' => LocationWay::Organised->value,
                'label' => LocationWay::Organised->label(),
            ];
        }

        foreach ($leisure as $way) {
            $tabs[] = ['key' => $way['key'], 'label' => $way['label']];
        }

        if ($services !== []) {
            $tabs[] = ['key' => 'servicii', 'label' => 'Servicii'];
        }

        return $tabs;
    }

    /**
     * The training programme: one entry per sport, with what it teaches and where.
     *
     * @return list<array<string, mixed>>
     */
    private function courses(Organization $organization): array
    {
        $locationsBySport = $this->locationsBySport($organization);

        return array_values($organization->organizationSports
            ->sortBy('sort_order')
            ->map(function (OrganizationSport $organizationSport) use ($organization, $locationsBySport): array {
                $sport = $organizationSport->sport;
                $highlights = $this->presentHighlights(
                    $organizationSport,
                    $this->sportHasAccessibleLocation($organization, $organizationSport->sport_id),
                );

                return [
                    'key' => $sport->slug,
                    'label' => $sport->translated_name,
                    'icon' => (string) $sport->icon,
                    'color' => $sport->color,
                    'title' => 'Despre '.mb_strtolower($sport->translated_name).' la '.$organization->name,
                    'trustChips' => $highlights['general'],
                    'sessionFormat' => $highlights['sessionFormat'],
                    'audience' => $highlights['audience'],
                    'ages' => $organizationSport->ageGroups->sortBy('sort_order')->pluck('name')->values()->all(),
                    'levels' => $organizationSport->levels->sortBy('sort_order')->pluck('name')->values()->all(),
                    'gallery' => $organizationSport->galleryImages->map(fn ($image): string => $image->url)->values()->all(),
                    'locations' => $locationsBySport[$sport->slug] ?? [],
                ];
            })
            ->values()
            ->all());
    }

    /**
     * The spaces this organization operates, grouped by the way you get in.
     *
     * Only what moderation has cleared, the same rule that decides whether the
     * organization counts as a venue at all. A space can offer two ways in — open
     * on Friday evenings, rented by the hour otherwise — so it appears under each.
     *
     * @return list<array<string, mixed>>
     */
    private function leisure(Organization $organization): array
    {
        /** @var list<array{space: Space, presence: OrganizationLocation}> $spaces */
        $spaces = [];

        foreach ($organization->organizationLocations as $presence) {
            foreach ($presence->spaces as $space) {
                if ($space->status === FacilityStatus::Approved) {
                    $spaces[] = ['space' => $space, 'presence' => $presence];
                }
            }
        }

        return array_values(collect(SpaceAccessMode::cases())
            ->map(function (SpaceAccessMode $mode) use ($spaces): ?array {
                $matching = collect($spaces)->filter(
                    fn (array $entry): bool => $entry['space']->accessModes()->contains($mode),
                );

                if ($matching->isEmpty()) {
                    return null;
                }

                return [
                    'key' => LocationWay::forAccessMode($mode)->value,
                    'label' => LocationWay::forAccessMode($mode)->label(),
                    'verb' => $mode->verb(),
                    'how' => $mode->description(),
                    'spaces' => $matching
                        ->map(fn (array $entry): array => $this->presentSpace($entry['space'], $mode) + [
                            'sport' => $entry['space']->sport?->translated_name,
                            'location' => $entry['presence']->location?->name,
                            'locationSlug' => $entry['presence']->location?->slug,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all());
    }

    /**
     * What it sells one appointment at a time: a consultation, a session, the
     * massage at a pilates studio.
     *
     * @return list<array<string, mixed>>
     */
    private function services(Organization $organization): array
    {
        return array_values($organization->services
            ->sortBy('sort_order')
            ->map(function (Service $service): array {
                $specialty = $service->specialty;

                return [
                    'key' => (string) $service->getKey(),
                    'icon' => $specialty === null ? '💆' : (string) $specialty->icon,
                    'name' => $service->name,
                    'specialty' => $specialty?->translated_name,
                    'price' => $service->priceLabel(),
                    'priceNotes' => $service->price_notes,
                    'duration' => $service->durationLabel(),
                    'description' => $service->description ?? '',
                    'person' => $service->person?->name,
                    'sports' => $service->sports
                        ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                        ->map(fn (Sport $sport): array => [
                            'key' => $sport->slug,
                            'label' => $sport->translated_name,
                            'icon' => (string) $sport->icon,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * Every address it operates from, whatever it does there.
     *
     * @return list<array{slug: string, name: string, address: string}>
     */
    private function locations(Organization $organization): array
    {
        return array_values($organization->organizationLocations
            ->map(fn (OrganizationLocation $presence): ?array => $presence->location === null ? null : [
                'slug' => $presence->location->slug,
                'name' => $presence->location->name,
                'address' => collect([$presence->location->address, $presence->location->city])
                    ->filter()
                    ->implode(', '),
            ])
            ->filter()
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
