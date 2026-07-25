<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Romanian county (județ) — reference data.
 *
 * @property int $id
 * @property string $name
 */
class County extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name'];

    /**
     * @return HasMany<Locality, $this>
     */
    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }
}
