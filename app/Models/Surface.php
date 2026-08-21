<?php

namespace App\Models;

use Database\Factories\SurfaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a court is played on: clay, grass, hard, parquet.
 *
 * A controlled vocabulary rather than a description, because the value of the
 * answer is entirely in being able to search for it — clay or hard is the first
 * question a tennis player asks, and free text could never answer it.
 *
 * Which surfaces make sense is a property of the sport, so the vocabulary is
 * tied to sports rather than offered whole. That is what makes the question
 * disappear where it has no answer: nobody asks what a pool is surfaced with.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
class Surface extends Model
{
    /** @use HasFactory<SurfaceFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'sort_order'];

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
     * The surfaces a sport is played on, keyed by id, ready for a select.
     *
     * Empty for a space with no sport, and for every sport nobody asks the
     * question about — which is what lets the field disappear rather than stand
     * there wanting an answer that does not exist.
     *
     * Empty too when only one surface is possible: a squash court is parquet, and
     * so is every other one. A select with a single option asks for a decision
     * that has already been made, which is the rule the access-mode chooser
     * already follows on the public pages. The link stays in the vocabulary, so
     * the day a second surface joins it the question appears on its own.
     *
     * @return array<int, string>
     */
    public static function optionsFor(mixed $sportId): array
    {
        if (blank($sportId)) {
            return [];
        }

        $sport = Sport::query()->with('surfaces')->whereKey($sportId)->first();

        if ($sport === null || $sport->surfaces->count() < 2) {
            return [];
        }

        return $sport->surfaces
            ->mapWithKeys(fn (self $surface): array => [$surface->getKey() => $surface->name])
            ->all();
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'sport_surface');
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }
}
