<?php

namespace App\Models;

use Database\Factories\ClubLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A club's presence at a shared location, plus the sports it teaches there.
 *
 * @property int $id
 * @property int $club_id
 * @property int $location_id
 */
class ClubLocation extends Model
{
    /** @use HasFactory<ClubLocationFactory> */
    use HasFactory;

    /** @var string */
    protected $table = 'club_location';

    /** @var list<string> */
    protected $fillable = ['club_id', 'location_id'];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
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
        return $this->belongsToMany(Sport::class, 'club_location_sport')->withTimestamps();
    }

    /**
     * @return HasMany<ClubLocationSport, $this>
     */
    public function clubLocationSports(): HasMany
    {
        return $this->hasMany(ClubLocationSport::class);
    }
}
