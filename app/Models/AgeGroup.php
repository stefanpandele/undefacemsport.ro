<?php

namespace App\Models;

use Database\Factories\AgeGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable audience/age group (e.g. "3–7 ani", "Seniori") a schedule slot is
 * for. Nothing else claims a group: who a club teaches is read from its hours.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
class AgeGroup extends Model
{
    /** @use HasFactory<AgeGroupFactory> */
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
     * @return HasMany<ScheduleSlot, $this>
     */
    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }
}
