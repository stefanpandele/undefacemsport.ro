<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trust chip a club shows for one of its sports (icon + label).
 *
 * @property int $id
 * @property int $organization_sport_id
 * @property string|null $icon
 * @property string $label
 * @property int $sort_order
 */
class OrganizationSportBenefit extends Model
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
     * @return BelongsTo<OrganizationSport, $this>
     */
    public function organizationSport(): BelongsTo
    {
        return $this->belongsTo(OrganizationSport::class);
    }
}
