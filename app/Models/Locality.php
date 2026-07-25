<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Romanian locality (oraș/comună/sat) within a county — reference data.
 *
 * @property int $id
 * @property int $county_id
 * @property string $name
 */
class Locality extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['county_id', 'name'];

    /**
     * @return BelongsTo<County, $this>
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }
}
