<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'is_admin', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    use HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Organization, $this, Pivot>
     */
    final public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_user_id');
    }

    public function ownsAnyOrganization(): bool
    {
        return $this->ownedOrganizations()->exists();
    }

    public function belongsToAnyOrganization(): bool
    {
        return $this->organizations()->exists();
    }

    public function isMasterOf(Organization $organization): bool
    {
        return $organization->owner_user_id !== null
            && (int) $organization->owner_user_id === (int) $this->getKey();
    }

    /**
     * Platform super admin — defined by email in config/auth.php, not stored in
     * the database. Bypasses all authorization via a Gate::before.
     */
    public function isSuperAdmin(): bool
    {
        return in_array($this->email, config('auth.super_admins'), true);
    }

    public function isConsumer(): bool
    {
        return ! $this->is_admin && ! $this->isSuperAdmin() && ! $this->belongsToAnyOrganization();
    }

    /**
     * @return Collection<int, Organization>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->organizations;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->organizations()->whereKey($tenant)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_admin || $this->isSuperAdmin(),
            'organization' => $this->organizations()->exists(),
            default => false,
        };
    }

    /**
     * The URL of the area this user belongs to: the admin panel for staff, the
     * club panel for club members, otherwise the consumer dashboard. Used to
     * redirect users away from areas they can't access instead of showing 403.
     */
    public function homeUrl(): string
    {
        if ($this->is_admin || $this->isSuperAdmin()) {
            return Filament::getPanel('admin')->getUrl();
        }

        if ($organization = $this->organizations()->first()) {
            return Filament::getPanel('organization')->getUrl($organization);
        }

        return route('dashboard');
    }
}
