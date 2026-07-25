<?php

namespace App\Models;

use Database\Factories\ClubSportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sport offered by a specific club, with per-club presentation (cover,
 * description). Backs the many-to-many between Club and the global Sport
 * taxonomy, but as a first-class model for rich management on the club panel.
 *
 * @property int $id
 * @property int $club_id
 * @property int $sport_id
 * @property string|null $cover_path
 * @property string|null $description
 * @property int $sort_order
 */
class ClubSport extends Model
{
    /** @use HasFactory<ClubSportFactory> */
    use HasFactory;

    protected $table = 'club_sport';

    /** @var list<string> */
    protected $fillable = ['sport_id', 'cover_path', 'description', 'sort_order'];

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
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }
}
