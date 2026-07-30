<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A slug that used to be a location's own, kept pointing at the record it was
 * merged into.
 *
 * @property int $id
 * @property string $slug
 * @property int $location_id
 */
class LocationRedirect extends Model
{
    /** @var list<string> */
    protected $fillable = ['slug', 'location_id'];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
