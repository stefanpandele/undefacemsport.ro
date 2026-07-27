<?php

namespace App\Http\Controllers;

use App\Concerns\PresentsClubs;
use App\Enums\ContactType;
use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\ClubSport;
use App\Models\ClubSportBenefit;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\ScheduleSlot;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    use PresentsClubs;

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
     * Benefit labels mentioning these describe a service adjacent to the
     * sport rather than the sport itself (e.g. massage at a pilates studio).
     *
     * @var list<string>
     */
    private const BEYOND_SPORT_KEYWORDS = [
        'masaj', 'sauna', 'nutritie', 'fizioterapie', 'kineto', 'spa', 'recuperare',
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
     * Show a public club profile with its sports, coaches and per-location schedule.
     */
    public function show(string $slug): Response
    {
        $club = Club::query()
            ->where('slug', $slug)
            ->with([
                'contacts',
                'clubSports.sport',
                'clubSports.benefits',
                'clubSports.ageGroups',
                'clubSports.galleryImages',
                'coaches.sports',
                'clubLocations.location.facilities',
                'clubLocations.clubLocationSports',
                'scheduleSlots.clubLocationSport',
                'scheduleSlots.ageGroup',
            ])
            ->firstOrFail();

        return Inertia::render('public/clubs/Show', [
            'club' => $this->presentClub($club),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentClub(Club $club): array
    {
        return [
            'slug' => $club->slug,
            'name' => $club->name,
            'representative' => $this->representative($club),
            'about' => $club->description ?? '',
            'phone' => $this->phone($club),
            'socials' => $this->socials($club),
            'sports' => $this->sports($club),
            'sportDetails' => $this->sportDetails($club),
            'coaches' => $this->presentCoaches($club->coaches),
            'locationsBySport' => $this->locationsBySport($club),
        ];
    }

    private function representative(Club $club): string
    {
        $primary = $club->coaches->firstWhere('is_primary', true) ?? $club->coaches->first();

        if (! $primary instanceof Coach) {
            return '';
        }

        return $primary->role ? "{$primary->name}, {$primary->role}" : $primary->name;
    }

    private function phone(Club $club): ?string
    {
        return $club->contacts->firstWhere('type', ContactType::Phone)?->value;
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function socials(Club $club): array
    {
        return $club->contacts
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
    private function sports(Club $club): array
    {
        return $club->clubSports
            ->sortBy('sort_order')
            ->map(fn (ClubSport $clubSport): array => [
                'key' => $clubSport->sport->slug,
                'label' => $clubSport->sport->translated_name,
                'icon' => (string) $clubSport->sport->icon,
                'color' => $clubSport->sport->color,
                'locationCount' => $this->locationCountForSport($club, $clubSport->sport_id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sportDetails(Club $club): array
    {
        return $club->clubSports
            ->sortBy('sort_order')
            ->mapWithKeys(function (ClubSport $clubSport) use ($club): array {
                $highlights = $this->presentHighlights(
                    $clubSport,
                    $this->sportHasAccessibleLocation($club, $clubSport->sport_id),
                );

                return [
                    $clubSport->sport->slug => [
                        'icon' => (string) $clubSport->sport->icon,
                        'title' => 'Despre '.mb_strtolower($clubSport->sport->translated_name).' la '.$club->name,
                        'trustChips' => $highlights['general'],
                        'sessionFormat' => $highlights['sessionFormat'],
                        'audience' => $highlights['audience'],
                        'beyondSport' => $highlights['beyondSport'],
                        'ages' => $clubSport->ageGroups->sortBy('sort_order')->pluck('name')->values()->all(),
                        'gallery' => $clubSport->galleryImages->map(fn ($image): string => $image->url)->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Sort a sport's benefit chips into the groups the club page highlights
     * separately, so a non-sport service (massage at a pilates studio) or an
     * accessibility note doesn't disappear into a wall of generic chips.
     *
     * @return array{general: list<string>, sessionFormat: list<string>, audience: list<string>, beyondSport: list<string>}
     */
    private function presentHighlights(ClubSport $clubSport, bool $locationIsAccessible): array
    {
        $general = [];
        $sessionFormat = [];
        $audience = [];
        $beyondSport = [];

        foreach ($clubSport->benefits->sortBy('sort_order') as $benefit) {
            /** @var ClubSportBenefit $benefit */
            $label = trim(($benefit->icon ?? '').' '.$benefit->label);
            $normalized = Str::of($benefit->label)->lower()->ascii()->toString();

            if (Str::contains($normalized, self::BEYOND_SPORT_KEYWORDS)) {
                $beyondSport[] = $label;
            } elseif (Str::contains($normalized, self::AUDIENCE_KEYWORDS)) {
                $audience[] = $label;
            } elseif (Str::contains($normalized, self::SESSION_FORMAT_KEYWORDS)) {
                $sessionFormat[] = $label;
            } else {
                $general[] = $label;
            }
        }

        if ($clubSport->offers_private_sessions) {
            $sessionFormat[] = '🎯 Antrenament 1:1 disponibil';
        }

        if ($locationIsAccessible && ! collect($audience)->contains(fn (string $a): bool => Str::contains(Str::of($a)->lower()->ascii()->toString(), 'dizabilit'))) {
            $audience[] = '♿ Acces persoane cu dizabilități';
        }

        return [
            'general' => $general,
            'sessionFormat' => $sessionFormat,
            'audience' => $audience,
            'beyondSport' => $beyondSport,
        ];
    }

    /**
     * Whether any of the club's locations teaching this sport has marked
     * itself wheelchair/disability accessible.
     */
    private function sportHasAccessibleLocation(Club $club, int $sportId): bool
    {
        return $club->clubLocations
            ->filter(fn (ClubLocation $clubLocation): bool => $clubLocation->clubLocationSports->contains('sport_id', $sportId))
            ->contains(fn (ClubLocation $clubLocation): bool => $clubLocation->location->facilities
                ->contains(fn (Facility $facility): bool => Str::contains(
                    Str::of($facility->name)->lower()->ascii()->toString(),
                    'dizabilit',
                )));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function locationsBySport(Club $club): array
    {
        $result = [];

        foreach ($club->clubSports->sortBy('sort_order') as $clubSport) {
            $sportId = $clubSport->sport_id;

            $result[$clubSport->sport->slug] = array_values($club->clubLocations
                ->filter(fn (ClubLocation $clubLocation): bool => $clubLocation->clubLocationSports->contains('sport_id', $sportId))
                ->map(fn (ClubLocation $clubLocation): array => [
                    'slug' => $clubLocation->location->slug,
                    'name' => $clubLocation->location->name,
                    'city' => Str::upper($clubLocation->location->city ?? ''),
                    'schedule' => $this->schedule($club, $clubLocation, $sportId),
                ])
                ->all());
        }

        return $result;
    }

    private function locationCountForSport(Club $club, int $sportId): int
    {
        return $club->clubLocations
            ->filter(fn (ClubLocation $clubLocation): bool => $clubLocation->clubLocationSports->contains('sport_id', $sportId))
            ->count();
    }

    /**
     * Build the Monday–Sunday grid for one location and sport.
     *
     * @return list<array{day: string, slots: list<array{time: string, group: string, coach: string}>}>
     */
    private function schedule(Club $club, ClubLocation $clubLocation, int $sportId): array
    {
        return $this->presentWeek(
            $club->scheduleSlots->filter(
                fn (ScheduleSlot $slot): bool => $slot->clubLocationSport->club_location_id === $clubLocation->id
                    && $slot->clubLocationSport->sport_id === $sportId,
            ),
        );
    }
}
