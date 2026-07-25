<?php

namespace App\Models;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $contactable_type
 * @property int $contactable_id
 * @property ContactType $type
 * @property ContactRole $role
 * @property string $value
 * @property string|null $name
 * @property int $sort_order
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['type', 'role', 'value', 'name', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'role' => ContactRole::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
