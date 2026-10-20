<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'background_color' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'name_en' => ['required', 'string'],
            'provider_id' => ['required', 'exists:providers,id'],
            'category_id' => ['required','exists:categories,id'],
            'description' => ['nullable'],
            'description_en' => ['nullable'],
            'logo_upload' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'background_color.required' => __('validation.required'),
            'background_color.string' => __('validation.string'),
            'background_color.max' => __('validation.max.string', ['max' => 50]),
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name_en.required' => __('validation.required'),
            'name_en.string' => __('validation.string'),
            'provider_id.required' => __('validation.required'),
            'provider_id.exists' => __('validation.exists'),
            'category_id.required' => __('validation.required'),
            'category_id.exists' => __('validation.exists'),
        ];
    }
    
}
