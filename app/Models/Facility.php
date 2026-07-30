<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * An amenity a physical location can offer (parking, showers, …), shared by
 * every club operating at that location.
 *
 * The vocabulary is global, so a club may propose an entry but it stays
 * `pending` — and out of every public page — until an admin approves it.
 *
 * @property int $id
 * @property string $name
 * @property string|null $icon
 * @property FacilityStatus $status
 * @property int|null $suggested_by_organization_id
 * @property int $sort_order
 */
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'icon', 'status', 'suggested_by_organization_id', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FacilityStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * Only the amenities cleared for public pages.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', FacilityStatus::Approved);
    }

    /**
     * Narrow a facilities query to what a club may attach: the shared
     * vocabulary plus its own suggestions, usable while they wait for review.
     * Without a club, only the approved vocabulary.
     *
     * A plain static rather than a scope, so it also works on the untyped
     * builders Filament hands to `modifyQueryUsing`.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function constrainUsable(Builder $query, ?Organization $organization): Builder
    {
        return $query->where(function (Builder $usable) use ($organization): void {
            $usable->where('status', FacilityStatus::Approved);

            if ($organization instanceof Organization) {
                $usable->orWhere('suggested_by_organization_id', $organization->getKey());
            }
        });
    }

    public function isPending(): bool
    {
        return $this->status === FacilityStatus::Pending;
    }

    /**
     * The club that proposed this amenity, if it did not come from the seed.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function suggestedByOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'suggested_by_organization_id');
    }

    /**
     * @return BelongsToMany<Location, $this, FacilityLocation>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->using(FacilityLocation::class)
            ->withPivot('photo_path');
    }

    /**
     * The photo a club attached as proof that this amenity exists at a given
     * place — what an admin looks at before approving the suggestion.
     */
    public function proofPhotoUrl(): ?string
    {
        return $this->proofPhotos()[0]['url'] ?? null;
    }

    /**
     * Every proof this amenity has, one per place it was attached to. A club
     * photographs its own bar; another club at another hall photographs theirs.
     * An admin judging the suggestion needs all of them, not just the first —
     * and each is only meaningful next to the place it was taken at.
     *
     * @return list<array{location: string, url: string}>
     */
    public function proofPhotos(): array
    {
        return array_values($this->locations
            ->filter(fn (Location $location): bool => filled($location->pivot?->photo_path))
            ->map(fn (Location $location): array => [
                'location' => $location->name,
                'url' => Storage::disk('s3')->url((string) $location->pivot?->photo_path),
            ])
            ->all());
    }
}
