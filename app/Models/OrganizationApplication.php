<?php

namespace App\Models;

use App\Enums\OrganizationApplicationStatus;
use Database\Factories\OrganizationApplicationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $fiscal_code
 * @property string|null $company_name
 * @property bool|null $is_vat_payer
 * @property string|null $address
 * @property string $contact_name
 * @property string|null $contact_role
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property string|null $county
 * @property string|null $city
 * @property OrganizationApplicationStatus $status
 * @property string|null $rejection_reason
 * @property int|null $organization_id
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property-read Organization|null $organization
 */
class OrganizationApplication extends Model
{
    /** @use HasFactory<OrganizationApplicationFactory> */
    use HasFactory;

    /**
     * Only the public summary form fields are mass-assignable.
     * `status`, `reviewed_at`, `reviewed_by` are set by the approval flow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'fiscal_code',
        'company_name',
        'address',
        'contact_name',
        'contact_role',
        'contact_email',
        'contact_phone',
        'county',
        'city',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationApplicationStatus::class,
            'is_vat_payer' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === OrganizationApplicationStatus::Pending;
    }

    /**
     * Turn the request into a real account holder.
     *
     * Everything the organization starts life with comes from the request: the
     * name, and the company details ANAF returned. It opens on the free plan
     * with no owner — the applicant still has no way in until somebody attaches
     * a user, and nothing yet published, so it has no public page either.
     *
     * The created organization is kept on the request so a second approval is
     * impossible and so the queue can show what each decision produced.
     *
     * @throws DomainException when the fiscal code already belongs to an organization
     */
    public function approve(User $reviewer): Organization
    {
        if ($this->organization_id !== null) {
            throw new DomainException('Cererea a fost deja aprobată.');
        }

        // The public form checks this too, but months can pass between the
        // request and the review, and nothing stops two requests carrying the
        // same code. A unique index violation here would surface as a 500.
        if (Organization::query()->where('fiscal_code', $this->fiscal_code)->exists()) {
            throw new DomainException('Există deja o organizație cu acest CUI.');
        }

        return DB::transaction(function () use ($reviewer): Organization {
            $organization = Organization::create([
                'name' => $this->name,
                'slug' => Organization::uniqueSlug($this->name, $this->city),
                'company_name' => $this->company_name,
                'fiscal_code' => $this->fiscal_code,
                'is_vat_payer' => $this->is_vat_payer,
                'address' => $this->address,
                'county' => $this->county,
                'city' => $this->city,
            ]);

            $this->forceFill([
                'organization_id' => $organization->getKey(),
                'rejection_reason' => null,
            ]);

            $this->markReviewed(OrganizationApplicationStatus::Approved, $reviewer);

            return $organization;
        });
    }

    /**
     * Refuse the request, with the reason the applicant is owed. The reason is
     * required: "respinsă" on its own tells somebody who filled in a form
     * nothing about what to fix.
     */
    public function reject(User $reviewer, string $reason): void
    {
        $this->forceFill(['rejection_reason' => $reason]);

        $this->markReviewed(OrganizationApplicationStatus::Rejected, $reviewer);
    }

    private function markReviewed(OrganizationApplicationStatus $status, User $reviewer): void
    {
        $this->forceFill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->getKey(),
        ])->save();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
