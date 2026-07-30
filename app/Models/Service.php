<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a practice sells: a consultation, a session, an assessment.
 *
 * The third kind of offer, beside a club's programme and a space's access. It is
 * neither of those because it is priced per appointment and bounded by a
 * duration — you book a person's time, not a place and not a term.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $specialty_id
 * @property int|null $organization_location_id
 * @property int|null $person_id
 * @property string $name
 * @property string|null $description
 * @property int|null $duration_minutes
 * @property string|null $price
 * @property string|null $price_notes
 * @property int $sort_order
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'specialty_id',
        'organization_location_id',
        'person_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'price_notes',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The price as a visitor reads it, or null when nobody has said. Free means
     * somebody said zero; unpriced means unknown, and the page says so rather
     * than promising anything — the same rule spaces follow.
     */
    public function priceLabel(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        $amount = (float) $this->price;

        if ($amount === 0.0) {
            return 'Gratuit';
        }

        $formatted = $amount === floor($amount)
            ? number_format($amount, 0, ',', '.')
            : number_format($amount, 2, ',', '.');

        return $formatted.' lei';
    }

    /**
     * "50 min" — how long an appointment takes, when the practice says.
     */
    public function durationLabel(): ?string
    {
        return $this->duration_minutes === null ? null : $this->duration_minutes.' min';
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Offered at every location the organization has, or only at one.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAvailableAt(Builder $query, OrganizationLocation $presence): void
    {
        $query->where('organization_id', $presence->organization_id)
            ->where(fn (Builder $available) => $available
                ->whereNull('organization_location_id')
                ->orWhere('organization_location_id', $presence->getKey()));
    }

    /**
     * @return BelongsTo<OrganizationLocation, $this>
     */
    public function organizationLocation(): BelongsTo
    {
        return $this->belongsTo(OrganizationLocation::class);
    }

    /**
     * @return BelongsTo<Specialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    /**
     * Who provides it, when the practice wants to say. A clinic with six
     * physiotherapists may leave it open.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
