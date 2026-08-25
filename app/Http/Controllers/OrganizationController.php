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
use App\Models\OrganizationLocationSport;
use App\Models\OrganizationSport;
use App\Models\OrganizationSportBenefit;
use App\Models\Person;
use App\Models\ScheduleSlot;
use App\Models\Service;
use App\Models\Space;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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
                'organizationSports.galleryImages',
                'people.sports',
                'services.specialty',
                'services.person',
                'services.sports',
                'organizationLocations.location.facilities',
                'organizationLocations.organizationLocationSports.sport',
                'organizationLocations.spaces.sport',
                'organizationLocations.spaces.accessSlots',
                'trainingSlots.organizationLocationSport',
                'trainingSlots.ageGroup',
                'trainingSlots.level',
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
            'counties' => $this->counties($organization),
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
     * Where this organization works, county first.
     *
     * An organization with halls in three counties cannot be read as one list of
     * offers: nobody attends a course two counties away, so the first question on
     * its page is which of them you are asking about. In the order the presences
     * were added, because that is the order it built itself in and no ranking we
     * invented would mean more.
     *
     * Everything below is carried in the same payload — county, location, sport,
     * way in and schedule. It is one organization's data, not a catalogue, and a
     * round trip per level would be four waits to answer one question.
     *
     * @return list<array<string, mixed>>
     */
    private function counties(Organization $organization): array
    {
        $byCounty = $organization->organizationLocations
            ->sortBy('id')
            ->filter(fn (OrganizationLocation $presence): bool => filled($presence->location?->county))
            ->groupBy(fn (OrganizationLocation $presence): string => (string) $presence->location->county);

        return array_values($byCounty
            ->map(fn (Collection $presences, string $county): array => [
                'key' => Str::slug($county),
                'label' => $county,
                'locations' => array_values($presences
                    ->map(fn (OrganizationLocation $presence): array => $this->locationDetail($organization, $presence))
                    ->all()),
            ])
            ->all());
    }

    /**
     * One address, with everything on offer at it.
     *
     * @return array<string, mixed>
     */
    private function locationDetail(Organization $organization, OrganizationLocation $presence): array
    {
        $location = $presence->location;
        $spaces = $presence->spaces->where('status', FacilityStatus::Approved);

        return [
            'slug' => (string) $location?->slug,
            'name' => (string) $location?->name,
            'address' => collect([$location?->address, $location?->city])->filter()->implode(', '),
            'city' => (string) $location?->city,
            'facilities' => $location === null ? [] : $location->facilities
                ->where('status', FacilityStatus::Approved)
                ->sortBy('sort_order')
                ->map(fn (Facility $facility): array => [
                    'icon' => $facility->icon ?? '🛠',
                    'label' => $facility->name,
                ])
                ->values()
                ->all(),
            'sports' => $this->sportsAt($organization, $presence, $spaces),
            // Paid things that are not a sport: the sauna you buy a ticket for,
            // the massage somebody gives you. Their own list, because they answer
            // "what else can I get here" rather than "where do I play".
            'extras' => $this->extrasAt($organization, $presence, $spaces),
        ];
    }

    /**
     * The sports on offer at one address, each with the ways into it.
     *
     * Both sides count: a sport is here because the organization teaches it here,
     * or because it operates a space for it here, or both — the pool that runs a
     * swimming club and sells tickets for the same water.
     *
     * @param  Collection<int, Space>  $spaces
     * @return list<array<string, mixed>>
     */
    private function sportsAt(Organization $organization, OrganizationLocation $presence, Collection $spaces): array
    {
        /** @var SupportCollection<int, Sport> $sports */
        $sports = collect($presence->organizationLocationSports
            ->map(fn (OrganizationLocationSport $presenceSport): ?Sport => $presenceSport->sport)
            ->filter()
            ->all())
            ->merge($spaces
                ->map(fn (Space $space): ?Sport => $space->sport)
                ->filter()
                ->all());

        return array_values($sports
            ->unique('id')
            ->sortBy(fn (Sport $sport): string => $sport->translated_name)
            ->map(fn (Sport $sport): array => [
                'key' => $sport->slug,
                'label' => $sport->translated_name,
                'icon' => (string) $sport->icon,
                'color' => $sport->color,
                'ways' => $this->waysAt($organization, $presence, $spaces, $sport),
            ])
            ->values()
            ->all());
    }

    /**
     * How you get into one sport at one address, with the timetable for each.
     *
     * @param  Collection<int, Space>  $spaces
     * @return list<array<string, mixed>>
     */
    private function waysAt(Organization $organization, OrganizationLocation $presence, Collection $spaces, Sport $sport): array
    {
        $ways = [];

        if ($presence->organizationLocationSports->contains('sport_id', $sport->getKey())) {
            $ways[] = [
                'key' => LocationWay::Organised->value,
                'label' => LocationWay::Organised->label(),
                'how' => LocationWay::Organised->description(),
                'schedule' => $this->schedule($organization, $presence, $sport->getKey()),
                'spaces' => [],
            ];
        }

        $forSport = $spaces->where('sport_id', $sport->getKey());

        foreach ([SpaceAccessMode::OpenAccess, SpaceAccessMode::ExclusiveRental] as $mode) {
            $matching = $forSport->filter(
                fn (Space $space): bool => $space->accessModes()->contains($mode),
            );

            if ($matching->isEmpty()) {
                continue;
            }

            $ways[] = [
                'key' => LocationWay::forAccessMode($mode)->value,
                'label' => LocationWay::forAccessMode($mode)->label(),
                'how' => LocationWay::forAccessMode($mode)->description(),
                'schedule' => [],
                'spaces' => $matching
                    ->map(fn (Space $space): array => $this->presentSpace($space, $mode))
                    ->values()
                    ->all(),
            ];
        }

        return $ways;
    }

    /**
     * What is sold here beside the sport: a sport-less space is the sauna, a
     * service is the massage.
     *
     * @param  Collection<int, Space>  $spaces
     * @return list<array<string, mixed>>
     */
    private function extrasAt(Organization $organization, OrganizationLocation $presence, Collection $spaces): array
    {
        $sauna = $spaces
            ->whereNull('sport_id')
            ->map(fn (Space $space): array => [
                'key' => 'space-'.$space->getKey(),
                'icon' => '🧖',
                'name' => $space->name,
                'detail' => $space->priceFromLabel(),
            ]);

        $services = $organization->services
            // A service pinned to another branch is not on offer here.
            ->filter(fn (Service $service): bool => $service->organization_location_id === null
                || $service->organization_location_id === $presence->getKey())
            ->map(fn (Service $service): array => [
                'key' => 'service-'.$service->getKey(),
                'icon' => $service->specialty === null ? '💆' : (string) $service->specialty->icon,
                'name' => $service->name,
                'detail' => $service->priceLabel(),
            ]);

        return array_values($sauna->concat($services)->values()->all());
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
                $slots = $this->trainingSlotsFor($organization, $organizationSport->sport_id);
                $highlights = $this->presentHighlights(
                    $organizationSport,
                    $this->sportHasAccessibleLocation($organization, $organizationSport->sport_id),
                    $organization->offersPrivateSessionsFor($organizationSport->sport_id),
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
                    'ages' => ScheduleSlot::ageGroupNames($slots),
                    'levels' => ScheduleSlot::levelNames($slots),
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
    private function presentHighlights(OrganizationSport $organizationSport, bool $locationIsAccessible, bool $offersPrivateSessions): array
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

        if ($offersPrivateSessions) {
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
     * Every training hour this club runs for one sport, across all its
     * locations. The club page speaks for the whole club, so its groups and
     * levels are the union of what each address teaches.
     *
     * @return Collection<int, ScheduleSlot>
     */
    private function trainingSlotsFor(Organization $organization, int $sportId): Collection
    {
        return $organization->trainingSlots->filter(
            fn (ScheduleSlot $slot): bool => $slot->organizationLocationSport?->sport_id === $sportId,
        );
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
            $organization->trainingSlots->filter(
                fn (ScheduleSlot $slot): bool => $slot->organizationLocationSport->organization_location_id === $organizationLocation->id
                    && $slot->organizationLocationSport->sport_id === $sportId,
            ),
        );
    }
}
