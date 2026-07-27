<?php

namespace App\Models;

use Database\Factories\ClubLocationSportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A club's sport at one of its locations — the anchor the weekly schedule
 * hangs off.
 *
 * @property int $id
 * @property int $club_location_id
 * @property int $sport_id
 */
class ClubLocationSport extends Model
{
    /** @use HasFactory<ClubLocationSportFactory> */
    use HasFactory;

    protected $table = 'club_location_sport';

    /** @var list<string> */
    protected $fillable = ['club_location_id', 'sport_id'];

    /**
     * @return BelongsTo<ClubLocation, $this>
     */
    public function clubLocation(): BelongsTo
    {
        return $this->belongsTo(ClubLocation::class);
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
