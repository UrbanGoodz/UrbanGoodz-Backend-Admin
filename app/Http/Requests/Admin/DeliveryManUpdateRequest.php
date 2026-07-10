<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Password;

/**
 * @property int id
 * @property array|string title
 * @property array translations
 * @property string|null|array description
 * @property string bonus_type
 * @property float bonus_amount
 * @property float minimum_add_amount
 * @property float maximum_bonus_amount
 * @property Carbon|null start_date
 * @property Carbon|null end_date
 * @property bool status
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property array lang
 */
class DeliveryManUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => 'required|unique:delivery_men,email,'.$this->id,
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:delivery_men,phone,'.$this->id,
            'vehicle_id' => 'required',
            'vehicle_type' => 'nullable|string|max:50',
            'earning' => 'required',
            'has_trailer' => 'nullable|boolean',
            'trailer_type' => 'nullable|string|max:50',
            'trailer_length_feet' => 'nullable|numeric|min:0',
            'trailer_width_feet' => 'nullable|numeric|min:0',
            'trailer_capacity_lbs' => 'nullable|numeric|min:0',
            'hitch_type' => 'nullable|string|max:50',
            'trailer_plate_number' => 'nullable|string|max:20',
            'trailer_registration_expiration' => 'nullable|date',
            'trailer_insurance_expiration' => 'nullable|date',
            'cdl_status' => 'nullable|string|max:50',
            'cdl_class' => 'nullable|string|max:20',
            'cdl_number' => 'nullable|string|max:50',
            'dot_number' => 'nullable|string|max:50',
            'mc_number' => 'nullable|string|max:50',
            'has_pallet_jack' => 'nullable|boolean',
            'has_hazmat' => 'nullable|boolean',
            'has_cargo_insurance' => 'nullable|boolean',
            'cargo_insurance_expiration' => 'nullable|date',
            'max_payload_lbs' => 'nullable|numeric|min:0',
            'cargo_length_inches' => 'nullable|numeric|min:0',
            'cargo_width_inches' => 'nullable|numeric|min:0',
            'cargo_height_inches' => 'nullable|numeric|min:0',
            'registration_expiration' => 'nullable|date',
            'insurance_expiration' => 'nullable|date',
            'inspection_expiration' => 'nullable|date',
            'password' => ['nullable', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
                function ($attribute, $value, $fail) {
                    if (strpos($value, ' ') !== false) {
                        $fail('The :attribute cannot contain white spaces.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => translate('messages.first_name_is_required'),
            'vehicle_id.required' => translate('messages.select_a_vehicle'),
            'earning.required' => translate('messages.select_dm_type'),
            'password.min_length' => translate('The password must be at least :min characters long'),
            'password.mixed' => translate('The password must contain both uppercase and lowercase letters'),
            'password.letters' => translate('The password must contain letters'),
            'password.numbers' => translate('The password must contain numbers'),
            'password.symbols' => translate('The password must contain symbols'),
            'password.uncompromised' => translate('The password is compromised. Please choose a different one'),
            'password.custom' => translate('The password cannot contain white spaces.'),
        ];
    }
}
