<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeStoreRequest extends FormRequest
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
            'value' => ['required'],
            'currency_id' => ['required','exists:currencies,id'],
        ];
    }

    public function messages()
    {
        return [
            'value.required' => __('validation.required'),
            'currency_id.required' => __('validation.string'),
            'currency_id.exists' => __('validation.exists'),
        ];
    }
}
