<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $user = $this->user();

        if ($user === null || ! $organization instanceof Organization) {
            return false;
        }

        return $user->isSuperAdmin() || (int) $organization->owner_user_id === (int) $user->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $clubId = $organization instanceof Organization ? $organization->getKey() : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('organizations', 'slug')->ignore($clubId)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'fiscal_code' => ['nullable', 'string', 'max:50', 'regex:/^(RO)?\d{2,10}$/i', Rule::unique('organizations', 'fiscal_code')->ignore($clubId)],
            'is_vat_payer' => ['boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
