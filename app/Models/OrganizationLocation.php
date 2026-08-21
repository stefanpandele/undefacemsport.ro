<?php

namespace App\Models;

use Database\Factories\OrganizationLocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A club's presence at a shared location, plus the sports it teaches there.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $location_id
 */
class OrganizationLocation extends Model
{
    /** @use HasFactory<OrganizationLocationFactory> */
    use HasFactory;

    /** @var string */
    protected $table = 'organization_location';

    /** @var list<string> */
    protected $fillable = ['organization_id', 'location_id'];

    /**
     * Only the presences that actually teach something here.
     *
     * The question is what happens at this address, never who the organization
     * is: a venue that hires a coach runs a programme like anybody else, and a
     * club present at a hall it merely rents out teaches nothing there. Renting
     * the same hall stays a separate offer, counted through `spaces`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeTeaching(Builder $query): void
    {
        $query->whereHas('organizationLocationSports');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'organization_location_sport')->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationLocationSport, $this>
     */
    public function organizationLocationSports(): HasMany
    {
        return $this->hasMany(OrganizationLocationSport::class);
    }

    /**
     * The spaces this organization operates at this location.
     *
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class)->orderBy('sort_order')->orderBy('name');
    }
}
