<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoucherUpdateRequest extends FormRequest
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
            'classification_id' => ['required', 'integer', 'exists:classifications,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'serial_number' => ['required', 'string'],
            'delete_at' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'classification_id.required' => __('validation.required'),
            'classification_id.integer' => __('validation.int'),
            'classification_id.exists' => __('validation.exists'),
            'user_id.required' => __('validation.required'),
            'user_id.integer' => __('validation.int'),
            'user_id.exists' => __('validation.exists'),
            'serial_number.required' => __('validation.required'),
            'serial_number.string' => __('validation.string'),
        ];
    }
    
}
