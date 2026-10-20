<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartPdfUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller requires cart-list and cart-edit.
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'string', 'size:64'],
            'exchange_rate' => ['required', 'string', 'regex:/^\d{1,6}(?:\.\d{1,6})?$/D', 'numeric', 'gt:0'],
            'company_percentage' => ['required', 'string', 'regex:/^\d{1,4}(?:\.\d{1,4})?$/D', 'numeric', 'between:0,1000'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*' => ['required', 'array:qty,original_usd,sale_usd,customer_usd'],
            'items.*.qty' => ['required', 'integer', 'between:1,100000'],
            'items.*.original_usd' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,16})?$/D'],
            'items.*.sale_usd' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,16})?$/D'],
            'items.*.customer_usd' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,16})?$/D'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $cart = $this->route('cart');
            if ($cart->status !== 'succeeded' || ! $cart->quotation) {
                $validator->errors()->add('items', 'يجب تجهيز عرض السعر قبل تعديل PDF.');
                return;
            }
            $items = $this->input('items');
            if (! is_array($items) || array_keys($items) !== array_keys($cart->quotation['items'])) {
                $validator->errors()->add('items', 'أرسل جميع منتجات السلة. يرجى تحديث الصفحة وإعادة المحاولة.');
                return;
            }
            foreach ($cart->quotation['items'] as $index => $item) {
                if (! is_array($items[$index])) {
                    continue;
                }
                if ($item['availability'] === 'available' && ($items[$index]['sale_usd'] ?? null) === null) {
                    $validator->errors()->add('items.'.$index.'.sale_usd', 'يرجى إدخال سعر التخفيض الأساسي بالدولار.');
                }
                if ($item['availability'] !== 'available') {
                    foreach (['sale_usd', 'original_usd', 'customer_usd'] as $field) {
                        if (($items[$index][$field] ?? null) !== null) {
                            $validator->errors()->add('items.'.$index.'.'.$field, 'المنتج غير المتوفر لا يدخل في أسعار PDF.');
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return ['items.*.qty.between' => 'الكمية يجب أن تكون بين 1 و100000.',
            'items.*.qty.integer' => 'الكمية يجب أن تكون عدداً صحيحاً.',
            'exchange_rate.gt' => 'سعر الصرف يجب أن يكون أكبر من صفر.',
            'company_percentage.between' => 'النسبة يجب أن تكون بين 0 و1000.',
            'items.*.original_usd.regex' => 'أدخل سعراً موجباً أو صفراً، بحد أقصى 16 منزلة عشرية.',
            'items.*.sale_usd.regex' => 'أدخل سعراً موجباً أو صفراً، بحد أقصى 16 منزلة عشرية.',
            'items.*.customer_usd.regex' => 'أدخل سعراً موجباً أو صفراً، بحد أقصى 16 منزلة عشرية.'];
    }
}
