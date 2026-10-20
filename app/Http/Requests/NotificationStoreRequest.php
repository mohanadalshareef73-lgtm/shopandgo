<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationStoreRequest extends FormRequest
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
            'title' => ['required'],
            'title_en' => ['required'],
            'message' => ['required'],
            'message_en' => ['required'],
            'type' => ['nullable'],
            'price' => ['nullable'],
            'customer_id' => ['nullable'],
        ];
    }

    public function messages()
    {
        return [
            'message.required' => __('validation.required'),
            'message_en.required' => __('validation.required'),
            'title.required' => __('validation.required'),
            'title_en.required' => __('validation.required'),
        ];
    }

}
