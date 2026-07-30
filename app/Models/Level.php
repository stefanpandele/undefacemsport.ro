<?php

namespace App\Models;

use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * How far along a training group is: learning the basics, or training to
 * compete.
 *
 * A separate axis from `AgeGroup`, which says who the group is for. Both a child
 * and an adult can be beginners, and both can be competing — so the two are
 * chosen independently.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
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
     * @return BelongsToMany<OrganizationSport, $this>
     */
    public function organizationSports(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationSport::class, 'organization_sport_level');
    }
}
