<?php

namespace App\Http\Requests;

use App\Rules\TurnstileToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClubApplicationRequest extends FormRequest
{
    /**
     * Public application form — anyone may submit.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'club_name' => ['required', 'string', 'max:255'],
            'fiscal_code' => ['required', 'string', 'max:50'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'company' => ['nullable', 'string'],
        ];

        if (config('services.turnstile.enabled')) {
            $rules['turnstile_token'] = ['required', 'string', new TurnstileToken];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'club_name.required' => __('club_application.validation.club_name.required'),
            'club_name.max' => __('club_application.validation.club_name.max'),
            'fiscal_code.required' => __('club_application.validation.fiscal_code.required'),
            'fiscal_code.max' => __('club_application.validation.fiscal_code.max'),
            'contact_name.required' => __('club_application.validation.contact_name.required'),
            'contact_name.max' => __('club_application.validation.contact_name.max'),
            'contact_email.required' => __('club_application.validation.contact_email.required'),
            'contact_email.email' => __('club_application.validation.contact_email.email'),
            'contact_email.max' => __('club_application.validation.contact_email.max'),
            'contact_phone.required' => __('club_application.validation.contact_phone.required'),
            'contact_phone.max' => __('club_application.validation.contact_phone.max'),
            'turnstile_token.required' => __('club_application.validation.turnstile.required'),
        ];
    }
}
