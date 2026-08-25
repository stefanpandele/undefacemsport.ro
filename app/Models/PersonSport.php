<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person at one sport — and whether they take clients alone in it.
 *
 * A model rather than a bare pivot because the row now says something: a coach
 * who gives individual swimming lessons and only group basketball is two
 * different answers, and the claim belongs to the pair.
 *
 * @property int $person_id
 * @property int $sport_id
 * @property bool $offers_private_sessions
 */
class PersonSport extends Model
{
    protected $table = 'person_sport';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['person_id', 'sport_id', 'offers_private_sessions'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offers_private_sessions' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }
}
