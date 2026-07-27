<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trust chip a club shows for one of its sports (icon + label).
 *
 * @property int $id
 * @property int $club_sport_id
 * @property string|null $icon
 * @property string $label
 * @property int $sort_order
 */
class ClubSportBenefit extends Model
{
    /** @var list<string> */
    protected $fillable = ['icon', 'label', 'sort_order'];

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
     * @return BelongsTo<ClubSport, $this>
     */
    public function clubSport(): BelongsTo
    {
        return $this->belongsTo(ClubSport::class);
    }
}
