<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreRequest extends FormRequest
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
            // 'total_price' => ['required'],
            'customer_id' => ['required', 'exists:customers,id'],
            'mobile_number' => ['required'],
            // 'cards' => ['required', 'array'],
        ];
    }

    public function messages()
    {
        return [
            // 'total_price.required' => __('validation.required'),
            'customer_id.required' => __('validation.string'),
            'customer_id.exists' => __('validation.exists'),
            'mobile_number.required' => __('validation.required'),
            // 'cards.required' => __('validation.required'),
            // 'cards.array' => __('validation.array'),
        ];
    }
}
