<?php

namespace App\Models;

use App\Enums\ClubApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $club_name
 * @property string $fiscal_code
 * @property string|null $company_name
 * @property bool|null $is_vat_payer
 * @property string $contact_name
 * @property string|null $contact_role
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property string|null $county
 * @property string|null $city
 * @property string|null $message
 * @property ClubApplicationStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 */
class ClubApplication extends Model
{
    /**
     * Only the public summary form fields are mass-assignable.
     * `status`, `reviewed_at`, `reviewed_by` are set by the approval flow.
     *
     * @var list<string>
     */
    protected $fillable = [
        'club_name',
        'fiscal_code',
        'contact_name',
        'contact_role',
        'contact_email',
        'contact_phone',
        'county',
        'city',
        'message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClubApplicationStatus::class,
            'is_vat_payer' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
