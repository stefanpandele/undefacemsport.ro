<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Club extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'slug'];

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
}
