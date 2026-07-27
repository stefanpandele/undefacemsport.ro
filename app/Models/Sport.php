<?php

namespace App\Models;

use Database\Factories\SportFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Lang;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $color
 * @property-read string $translated_name
 */
class Sport extends Model
{
    /** @use HasFactory<SportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'icon', 'color'];

    /**
     * The sport's name in the current locale (from lang/{locale}/sports.php,
     * keyed by slug), falling back to the stored `name`.
     *
     * @return Attribute<string, never>
     */
    protected function translatedName(): Attribute
    {
        return Attribute::get(function (): string {
            $key = 'sports.'.$this->slug;

            return Lang::has($key) ? __($key) : $this->name;
        });
    }

    /**
     * @return BelongsToMany<Club, $this>
     */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->withPivot(['cover_path', 'description', 'sort_order'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
