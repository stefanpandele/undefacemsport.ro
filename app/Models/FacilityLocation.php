<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The link between a shared location and one of its amenities, carrying the
 * photo a club sent as proof that the amenity really is there.
 *
 * @property int $facility_id
 * @property int $location_id
 * @property string|null $photo_path
 */
class FacilityLocation extends Pivot
{
    protected $table = 'facility_location';

    public $incrementing = false;

    public $timestamps = false;
}
