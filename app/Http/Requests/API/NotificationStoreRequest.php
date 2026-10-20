<?php

namespace App\Http\Requests\API;

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
     * @return array
     */
    public function rules()
    {
        return [
            'type' => ['required', 'in:1,2,3,4'],
            'message' => ['required', 'string'],
            'price' => ['nullable', 'numeric'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'delete_at' => ['nullable'],
        ];
    }
}
