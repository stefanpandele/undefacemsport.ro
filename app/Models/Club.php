<?php

namespace App\Models;

use App\Enums\Plan;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $company_name
 * @property string|null $fiscal_code
 * @property bool|null $is_vat_payer
 * @property string|null $address
 * @property string|null $county
 * @property string|null $city
 * @property string|null $logo_path
 * @property string|null $cover_path
 * @property Plan $plan
 * @property int|null $owner_user_id
 * @property-read string|null $logo_url
 * @property-read string|null $cover_url
 */
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'company_name',
        'fiscal_code',
        'is_vat_payer',
        'address',
        'county',
        'city',
        'logo_path',
        'cover_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_vat_payer' => 'boolean',
            'plan' => Plan::class,
        ];
    }

    /**
     * Whether the club's current plan includes the given feature.
     */
    public function planAllows(string $feature): bool
    {
        return ($this->plan ?? Plan::Free)->allows($feature);
    }

    /**
     * The plan's numeric limit for the given key, or null for unlimited.
     */
    public function planLimit(string $key): ?int
    {
        return ($this->plan ?? Plan::Free)->limit($key);
    }

    /**
     * Whether the club can still add another item under the given plan limit.
     */
    public function withinPlanLimit(string $key, int $current): bool
    {
        $limit = $this->planLimit($key);

        return $limit === null || $current < $limit;
    }

    /**
     * Whether the club can add another sport under its plan's `sports` limit.
     */
    public function canAddSport(): bool
    {
        return $this->withinPlanLimit('sports', $this->clubSports()->count());
    }

    /**
     * Create a club and make its creator the owner and first member.
     *
     * @param  array{name: string, slug: string}  $attributes
     */
    public static function createForOwner(User $owner, array $attributes): self
    {
        return DB::transaction(function () use ($owner, $attributes): self {
            $club = static::create($attributes);
            $club->addMember($owner);

            return $club->refresh();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class)
            ->withPivot(['cover_path', 'description', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ClubSport, $this>
     */
    public function clubSports(): HasMany
    {
        return $this->hasMany(ClubSport::class);
    }

    /**
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * @return MorphMany<Contact, $this>
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function addMember(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var self $club */
            $club = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $club->users()->syncWithoutDetaching($user);

            if (is_null($club->owner_user_id)) {
                $club->owner()->associate($user);
                $club->save();
            }
        });
    }

    /**
     * Hand the club over to another user, who becomes the new owner (master).
     * The previous owner remains a regular member.
     */
    public function transferOwnershipTo(User $newOwner): void
    {
        DB::transaction(function () use ($newOwner): void {
            /** @var self $club */
            $club = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $club->users()->syncWithoutDetaching($newOwner);
            $club->owner()->associate($newOwner)->save();
        });
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->urlForPath($this->logo_path));
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->urlForPath($this->cover_path));
    }

    private function urlForPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return Storage::disk('s3')->url($path);
    }
}
