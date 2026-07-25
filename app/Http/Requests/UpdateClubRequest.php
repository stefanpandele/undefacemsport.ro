<?php

namespace App\Http\Requests;

use App\Models\Club;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $club = $this->route('club');
        $user = $this->user();

        if ($user === null || ! $club instanceof Club) {
            return false;
        }

        return $user->isSuperAdmin() || (int) $club->owner_user_id === (int) $user->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $club = $this->route('club');
        $clubId = $club instanceof Club ? $club->getKey() : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('clubs', 'slug')->ignore($clubId)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'fiscal_code' => ['nullable', 'string', 'max:50', 'regex:/^(RO)?\d{2,10}$/i', Rule::unique('clubs', 'fiscal_code')->ignore($clubId)],
            'is_vat_payer' => ['boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
