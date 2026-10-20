<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'mobile_number' => ['required', 'string', 'max:10'],
            'name' => ['nullable'],
            'password' => ['nullable'],
            'paymentType_id' => ['required', 'exists:payment_types,id'],
            'delete_at' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'mobile_number.required' => __('validation.required'),
            'mobile_number.string' => __('validation.string'),
            'mobile_number.max' => __('validation.max.string', ['max' => 10]),
            'paymentType_id.required' => __('validation.required'),
            'paymentType_id.exists' => __('validation.exists'),
        ];
    }
}
