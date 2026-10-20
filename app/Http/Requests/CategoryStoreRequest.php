<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'name_en' => ['required', 'string', 'max:50'],
            'logo' => ['nullable'],
            'delete_at' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string', ['max' => 50]),
            'name_en.required' => __('validation.required'),
            'name_en.string' => __('validation.string'),
            'name_en.max' => __('validation.max.string', ['max' => 50]),
        ];
    }

}
