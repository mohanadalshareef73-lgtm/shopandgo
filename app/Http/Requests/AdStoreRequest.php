<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdStoreRequest extends FormRequest
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
            'img' => ['required', 'image', 'mimes:jpg,png,jpeg,gif,webp'],
            'product_id' => ['nullable'],
            'link' => ['nullable'],
            'status ' => ['nullable'],
            'delete_at' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string', ['max' => 50]),
            'img.required' => __('validation.image'),
            'img.mimes' => __('validation.mimes', ['values' => 'jpg,png,jpeg,gif,svg']),
        ];
    }

    

}
