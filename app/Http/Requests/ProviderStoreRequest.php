<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderStoreRequest extends FormRequest
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
            'api' => ['required', 'string', 'unique:providers'],
            'type' => ['required'],
            'token' => ['required', 'string'],
            'delete_at' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string', ['max' => 50]),
            'api.required' => __('validation.required'),
            'api.string' => __('validation.string'),
            'api.unique' => __('validation.unique'),
            'type.required' => __('validation.required'),
            'token.required' => __('validation.required'),
            'token.string' => __('validation.string'),
        ];
    }

}
