<?php

namespace App\Models;

use App\Enums\LocationCorrectionField;
use App\Enums\LocationCorrectionStatus;
use Database\Factories\LocationCorrectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A club's request to fix one field of a shared location it does not own.
 *
 * @property int $id
 * @property int $location_id
 * @property int|null $organization_id
 * @property LocationCorrectionField $field
 * @property string $suggested_value
 * @property string|null $note
 * @property LocationCorrectionStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 */
class LocationCorrection extends Model
{
    /** @use HasFactory<LocationCorrectionFactory> */
    use HasFactory;

    /**
     * Only what the club proposes is mass-assignable. `status`, `reviewed_at` and
     * `reviewed_by` belong to the review flow.
     *
     * @var list<string>
     */
    protected $fillable = ['location_id', 'organization_id', 'field', 'suggested_value', 'note'];

    /**
     * Mirrors the column default, so a freshly created request reports its status
     * without having to be refreshed from the database.
     *
     * @var array<string, string>
     */
    protected $attributes = ['status' => LocationCorrectionStatus::Pending->value];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => LocationCorrectionField::class,
            'status' => LocationCorrectionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === LocationCorrectionStatus::Pending;
    }

    /**
     * Write the proposed value onto the shared location and close the request.
     *
     * Renaming a location also has to renew its slug, or the public URL keeps
     * advertising the wrong name; the old slug is left behind rather than
     * redirected, which is acceptable only because a correction fixes a record
     * nobody has claimed yet. A claimed location's rename is a different flow.
     */
    public function apply(User $reviewer): void
    {
        DB::transaction(function () use ($reviewer): void {
            $location = $this->location;

            $location->{$this->field->value} = $this->suggested_value;

            if ($this->field === LocationCorrectionField::Name) {
                $location->slug = Location::uniqueSlug($this->suggested_value, $location->city);
            }

            $location->save();

            $this->markReviewed(LocationCorrectionStatus::Approved, $reviewer);
        });
    }

    public function reject(User $reviewer): void
    {
        $this->markReviewed(LocationCorrectionStatus::Rejected, $reviewer);
    }

    /**
     * Force-filled because the review columns are deliberately not fillable: only
     * this flow may set them, never a club's submission.
     */
    private function markReviewed(LocationCorrectionStatus $status, User $reviewer): void
    {
        $this->forceFill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->getKey(),
        ])->save();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
