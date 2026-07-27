<?php

namespace App\Models;

use Database\Factories\AgeGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A reusable audience/age group (e.g. "3–7 ani", "Seniori") a club serves for a
 * sport, and that its schedule slots target.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
class AgeGroup extends Model
{
    /** @use HasFactory<AgeGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<ClubSport, $this>
     */
    public function clubSports(): BelongsToMany
    {
        return $this->belongsToMany(ClubSport::class, 'club_sport_age_group');
    }
}
