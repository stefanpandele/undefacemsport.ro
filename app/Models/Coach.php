<?php

namespace App\Models;

use Database\Factories\CoachFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A coach at a club, teaching one or more of the club's sports.
 *
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property string|null $role
 * @property string|null $bio
 * @property string|null $photo_path
 * @property bool $offers_private_sessions
 * @property bool $is_primary
 * @property int $sort_order
 */
class Coach extends Model
{
    /** @use HasFactory<CoachFactory> */
    use HasFactory;

    protected $table = 'coaches';

    /** @var list<string> */
    protected $fillable = ['name', 'role', 'bio', 'photo_path', 'offers_private_sessions', 'is_primary', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offers_private_sessions' => 'boolean',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
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
