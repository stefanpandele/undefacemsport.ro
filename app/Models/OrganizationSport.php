<?php

namespace App\Models;

use Database\Factories\OrganizationSportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A sport offered by a specific club, with its own presentation: a photo
 * gallery and the trust benefits it claims.
 *
 * What it does *not* hold is who the club teaches and how far along they are.
 * At one pool a club may take only children and at another only adults, so
 * groups and levels are read from the hours — see `ScheduleSlot::ageGroupNames()`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $sport_id
 * @property bool $offers_private_sessions
 * @property int $sort_order
 */
class OrganizationSport extends Model
{
    /** @use HasFactory<OrganizationSportFactory> */
    use HasFactory;

    protected $table = 'organization_sport';

    /** @var list<string> */
    protected $fillable = ['sport_id', 'offers_private_sessions', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offers_private_sessions' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * All polymorphic images attached to this club-sport.
     *
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * The gallery photos shown on the club page, ordered.
     *
     * @return MorphMany<Image, $this>
     */
    public function galleryImages(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')
            ->where('collection', 'gallery')
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<OrganizationSportBenefit, $this>
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(OrganizationSportBenefit::class)->orderBy('sort_order');
    }
}
