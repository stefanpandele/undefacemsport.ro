<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Sport;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    /**
     * A practice's public page: what it treats, what it charges, and who does it.
     *
     * Its own URL rather than /cluburi, because a physiotherapist is not a club
     * and a visitor looking for one is not looking for training.
     */
    public function show(string $slug): Response
    {
        $practice = Organization::query()
            ->where('slug', $slug)
            ->where('type', OrganizationType::Practice)
            ->with([
                'contacts',
                'people.sports',
                'services.specialty',
                'services.person',
                'services.sports',
                'organizationLocations.location',
            ])
            ->firstOrFail();

        return Inertia::render('public/practices/Show', [
            'practice' => [
                'slug' => $practice->slug,
                'name' => $practice->name,
                'about' => $practice->description ?? '',
                'phone' => $practice->contacts->firstWhere('type', ContactType::Phone)?->value,
                'specialties' => $this->specialties($practice),
                'services' => $this->services($practice),
                'people' => $this->people($practice->people),
                'locations' => $this->locations($practice),
            ],
        ]);
    }

    /**
     * The specialties this practice actually offers a service for — derived, not
     * declared, so the page cannot claim more than it sells.
     *
     * @return list<array{key: string, label: string, icon: string, color: string|null, serviceCount: int}>
     */
    private function specialties(Organization $practice): array
    {
        return array_values($practice->services
            ->map(fn (Service $service): ?Specialty => $service->specialty)
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->map(fn (Specialty $specialty): array => [
                'key' => $specialty->slug,
                'label' => $specialty->translated_name,
                'icon' => (string) $specialty->icon,
                'color' => $specialty->color,
                'serviceCount' => $practice->services
                    ->where('specialty_id', $specialty->getKey())
                    ->count(),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function services(Organization $practice): array
    {
        return array_values($practice->services
            ->sortBy('sort_order')
            ->map(fn (Service $service): array => [
                'id' => $service->getKey(),
                'name' => $service->name,
                'specialty' => $service->specialty?->slug,
                'specialtyLabel' => $service->specialty?->translated_name,
                'description' => $service->description ?? '',
                'duration' => $service->durationLabel(),
                'price' => $service->priceLabel(),
                'priceNotes' => $service->price_notes,
                'person' => $service->person?->name,
                // Which athletes this is for, as the practitioner ticked it.
                'sports' => $service->sports
                    ->sortBy(fn (Sport $sport): string => $sport->translated_name)
                    ->map(fn (Sport $sport): array => [
                        'key' => $sport->slug,
                        'label' => $sport->translated_name,
                        'icon' => (string) $sport->icon,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, Person>  $people
     * @return list<array<string, mixed>>
     */
    private function people(Collection $people): array
    {
        return array_values($people
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])
            ->map(fn (Person $person): array => [
                'key' => (string) $person->getKey(),
                'name' => $person->name,
                'profession' => $person->profession->label(),
                'role' => $person->role ?? '',
                'bio' => $person->bio ?? '',
                'photo' => $person->photo_url,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{slug: string, name: string, address: string}>
     */
    private function locations(Organization $practice): array
    {
        return array_values($practice->organizationLocations
            ->map(fn ($presence): ?array => $presence->location === null ? null : [
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
}
