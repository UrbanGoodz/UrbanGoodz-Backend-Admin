<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (int) auth('admin')->user()?->role_id === 1;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => [
                'sometimes',
                'string',
                Rule::in(['disabled', 'sandbox']),
            ],
            'platform_fee_percent' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:50',
            ],
            'driver_share_source' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'vendor_share_source' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'dispatcher_percent' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:50',
            ],
            'creator_referral_percent' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:50',
            ],
            'tax_handling' => [
                'sometimes',
                'string',
                Rule::in(['platform_collects', 'pass_through', 'excluded']),
            ],
            'pass_through_handling' => [
                'sometimes',
                'string',
                Rule::in(['included', 'excluded', 'itemized']),
            ],
            'minimum_order_amount' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],
            'maximum_order_amount' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'gte:minimum_order_amount',
            ],
        ];
    }
}
