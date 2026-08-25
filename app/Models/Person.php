<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Someone an organization puts in front of the public: a coach at a club, a
 * doctor or physiotherapist at a practice. Same card on the same page, so one
 * model.
 *
 * What they are is `role`, in their own words — "Antrenor principal",
 * "Fizioterapeut", "Președinte". A second, structured profession column used to
 * sit beside it, defaulting to "coach" because nothing ever asked: every
 * receptionist a club entered was filed as a coach, which is the false claim
 * the column existed to prevent. Nothing public read it, and the medical line
 * is drawn on `specialties.is_medical` rather than on who sells the service.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $role
 * @property string|null $bio
 * @property string|null $photo_path
 * @property bool $is_primary
 * @property int $sort_order
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected $table = 'people';

    /** @var list<string> */
    protected $fillable = ['name', 'role', 'bio', 'photo_path', 'is_primary', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
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
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class)->withPivot('offers_private_sessions');
    }

    /**
     * The person-and-sport rows, read as records rather than through the pivot:
     * they carry an answer of their own now.
     *
     * @return HasMany<PersonSport, $this>
     */
    public function sportAssignments(): HasMany
    {
        return $this->hasMany(PersonSport::class);
    }

    /**
     * Whether this person takes clients one to one for a given sport.
     *
     * Asked per sport rather than once per person: a coach who gives individual
     * swimming lessons and only group basketball used to claim both, and the
     * chip turned up on a tab nobody had said it about.
     */
    public function offersPrivateSessionsIn(int $sportId): bool
    {
        return $this->sportAssignments->contains(
            fn (PersonSport $assignment): bool => $assignment->sport_id === $sportId
                && $assignment->offers_private_sessions,
        );
    }

    /**
     * The sports this person gives individual sessions in.
     *
     * @return list<int>
     */
    public function privateSessionSportIds(): array
    {
        return array_values($this->sportAssignments
            ->filter(fn (PersonSport $assignment): bool => $assignment->offers_private_sessions)
            ->map(fn (PersonSport $assignment): int => $assignment->sport_id)
            ->all());
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path === null
            ? null
            : Storage::disk('s3')->url($this->photo_path);
    }
}
