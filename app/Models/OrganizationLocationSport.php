<?php

namespace App\Models;

use Database\Factories\OrganizationLocationSportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A club's sport at one of its locations — the anchor the weekly schedule
 * hangs off.
 *
 * @property int $id
 * @property int $organization_location_id
 * @property int $sport_id
 */
class OrganizationLocationSport extends Model
{
    /** @use HasFactory<OrganizationLocationSportFactory> */
    use HasFactory;

    protected $table = 'organization_location_sport';

    /** @var list<string> */
    protected $fillable = ['organization_location_id', 'sport_id'];

    /**
     * @return BelongsTo<OrganizationLocation, $this>
     */
    public function organizationLocation(): BelongsTo
    {
        return $this->belongsTo(OrganizationLocation::class);
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return HasMany<ScheduleSlot, $this>
     */
    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }
}
