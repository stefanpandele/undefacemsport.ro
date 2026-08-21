<?php

namespace App\Models;

use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;

/**
 * What a practice does: physiotherapy, sports medicine, nutrition.
 *
 * A separate taxonomy from `Sport`, not a branch of it. Physiotherapy is not a
 * sport, and `sports` carries product logic — popular sports, reach counts,
 * explore filters, age groups, levels — that medical specialties would pollute.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $color
 * @property bool $is_medical
 * @property int $sort_order
 * @property-read string $translated_name
 */
class Specialty extends Model
{
    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'icon', 'color', 'is_medical', 'sort_order'];

    /** @var array<string, bool> */
    protected $attributes = ['is_medical' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_medical' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The specialty's name in the current locale, falling back to the stored one —
     * the same arrangement `Sport` uses.
     *
     * @return Attribute<string, never>
     */
    protected function translatedName(): Attribute
    {
        return Attribute::get(function (): string {
            $key = 'specialties.'.$this->slug;

            return Lang::has($key) ? __($key) : $this->name;
        });
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
