<?php

namespace App\Models;

use App\Enums\PersonProfession;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Someone an organization puts in front of the public: a coach at a club, a
 * doctor or physiotherapist at a practice. Same card on the same page, so one
 * model with a `profession`.
 *
 * `role` is a different thing: the free-text job title shown publicly
 * ("Antrenor principal", "Coordonator").
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property PersonProfession $profession
 * @property string|null $role
 * @property string|null $bio
 * @property string|null $photo_path
 * @property bool $offers_private_sessions
 * @property bool $is_primary
 * @property int $sort_order
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected $table = 'people';

    /** @var list<string> */
    protected $fillable = ['name', 'profession', 'role', 'bio', 'photo_path', 'offers_private_sessions', 'is_primary', 'sort_order'];

    /**
     * Mirrors the column default, so a freshly created person reports a profession
     * without having to be refreshed from the database.
     *
     * @var array<string, string>
     */
    protected $attributes = ['profession' => PersonProfession::Coach->value];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offers_private_sessions' => 'boolean',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'profession' => PersonProfession::class,
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
        return $this->belongsToMany(Sport::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path === null
            ? null
            : Storage::disk('s3')->url($this->photo_path);
    }
}
