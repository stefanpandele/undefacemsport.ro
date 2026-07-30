<?php

namespace App\Models;

use App\Enums\LocationClaimStatus;
use Database\Factories\LocationClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An organization asking to become the authoritative editor of a place it
 * operates.
 *
 * Locations are created by whoever arrives first, which in practice is a club
 * saying it trains somewhere — and that club has no claim to the record itself.
 * When the company that actually runs the place turns up, this is how it takes
 * over the fields that describe the place rather than anybody's offer.
 *
 * @property int $id
 * @property int $location_id
 * @property int $organization_id
 * @property string|null $evidence
 * @property LocationClaimStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 */
class LocationClaim extends Model
{
    /** @use HasFactory<LocationClaimFactory> */
    use HasFactory;

    /**
     * Only what the organization submits. The review columns belong to the
     * approval flow.
     *
     * @var list<string>
     */
    protected $fillable = ['location_id', 'organization_id', 'evidence'];

    /**
     * Mirrors the column default, so a freshly created claim reports its status
     * without a round trip to the database.
     *
     * @var array<string, string>
     */
    protected $attributes = ['status' => LocationClaimStatus::Pending->value];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LocationClaimStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === LocationClaimStatus::Pending;
    }

    /**
     * Hand the place over: this organization now holds the pen on the location's
     * own fields.
     *
     * Every other pending claim on the same place is refused in the same breath —
     * a place has one authoritative editor, and leaving rival claims open would
     * invite a second approval that silently overwrites the first.
     *
     * Nothing belonging to the clubs there is touched. Their sports, schedules,
     * people and galleries are their own; only the description of the place moves.
     */
    public function approve(User $reviewer): void
    {
        DB::transaction(function () use ($reviewer): void {
            $this->location->forceFill([
                'claimed_by_organization_id' => $this->organization_id,
                'claimed_at' => now(),
            ])->save();

            static::query()
                ->where('location_id', $this->location_id)
                ->whereKeyNot($this->getKey())
                ->where('status', LocationClaimStatus::Pending)
                ->update([
                    'status' => LocationClaimStatus::Rejected,
                    'reviewed_at' => now(),
                    'reviewed_by' => $reviewer->getKey(),
                ]);

            $this->markReviewed(LocationClaimStatus::Approved, $reviewer);
        });
    }

    public function reject(User $reviewer): void
    {
        $this->markReviewed(LocationClaimStatus::Rejected, $reviewer);
    }

    private function markReviewed(LocationClaimStatus $status, User $reviewer): void
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
