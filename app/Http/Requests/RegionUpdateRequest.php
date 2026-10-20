<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegionUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:30'],
            'name_en' => ['required', 'string', 'max:30'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'delete_at' => ['nullable'],
        ];
    }

    
    public function messages()
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string', ['max' => 30]),
            'name_en.required' => __('validation.required'),
            'name_en.string' => __('validation.string'),
            'name_en.max' => __('validation.max.string', ['max' => 30]),
            'product_id.required' => __('validation.required'),
            'product_id.integer' => __('validation.int'),
            'product_id.exists' => __('validation.exists'),
        ];
    }
    
}
