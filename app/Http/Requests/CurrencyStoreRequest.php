<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:25'],
            'code' => ['required', 'string', 'max:5'],
            'delete_at' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string', ['max' => 25]),
            'code.required' => __('validation.required'),
            'code.string' => __('validation.string'),
            'code.max' => __('validation.max.string', ['max' => 5]),
        ];
    }
}
