<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Models\CartQuote;
use App\Services\SheinLinkClassifier;
use App\Services\QuoteUrlExtractor;
use Illuminate\Foundation\Http\FormRequest;

class ManualCartStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller requires cart-list and cart-create permissions.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone) && preg_match('/^(?:\+|00)?[0-9][0-9\s().-]*$/D', $phone)) {
            $phone = preg_replace('/[\s().-]/', '', $phone);
            $phone = str_starts_with($phone, '00') ? substr($phone, 2) : ltrim($phone, '+');
            $this->merge(['phone' => $phone]);
        }
    }

    public function rules(): array
    {
        return [
            'retailer' => ['sometimes', 'required', 'in:shein,amazon,iherb'],
            'requested_quantity' => ['required_if:retailer,amazon', 'prohibited_unless:retailer,amazon', 'integer', 'between:1,999999'],
            'source_url' => ['bail', 'required', 'string', 'max:8192', 'url', function ($attribute, $value, $fail) {
                $retailer = $this->input('retailer', 'shein');
                if (! is_string($retailer) || ! array_key_exists($retailer, CartQuote::RETAILERS)) {
                    return; // The retailer rule reports invalid store selections.
                }
                if (Setting::find(1)?->isCartStoreEnabled($retailer) === false) {
                    $fail('طلبات '.(CartQuote::RETAILERS[$retailer] ?? $retailer).' متوقفة حالياً. أعد تفعيل المتجر من إعدادات النظام.');
                    return;
                }
                if ($this->input('retailer') === 'iherb') {
                    if (! app(QuoteUrlExtractor::class)->isIherbUrl($value)) {
                        $fail('أدخل رابط منتج أو سلة مشاركة من آي هيرب.');
                    }
                    return;
                }
                if ($this->input('retailer') === 'amazon') {
                    if (! app(QuoteUrlExtractor::class)->isAmazonUrl($value)) {
                        $fail('أدخل رابط منتج أمازون أو رابط مشاركة أمازون من أحد المواقع المدعومة.');
                    }
                    return;
                }
                if (app(SheinLinkClassifier::class)->classify($value) !== 'cart') {
                    $fail('يرجى إدخال رابط سلة شي إن مشتركة، وليس رابط منتج.');
                }
            }],
            'phone' => ['bail', 'required', 'string', 'regex:/^[1-9]\d{6,14}$/D'],
            'submission_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_url.required' => match ($this->input('retailer')) {
                'amazon' => 'يرجى إدخال رابط منتج أمازون.',
                'iherb' => 'يرجى إدخال رابط منتج أو سلة مشاركة من آي هيرب.',
                default => 'يرجى إدخال رابط سلة شي إن.',
            },
            'source_url.url' => 'يرجى إدخال رابط كامل يبدأ بـ https:// أو http://.',
            'source_url.max' => 'رابط السلة طويل جداً.',
            'phone.required' => 'يرجى إدخال رقم واتساب العميل.',
            'phone.regex' => 'أدخل رقم واتساب مع رمز الدولة، مثال: 218911234567.',
            'submission_key.required' => 'يرجى فتح صفحة فحص السلة من جديد وإعادة المحاولة.',
            'submission_key.uuid' => 'يرجى فتح صفحة فحص السلة من جديد وإعادة المحاولة.',
            'requested_quantity.required_if' => 'يرجى إدخال الكمية المطلوبة.',
            'requested_quantity.between' => 'أدخل كمية صحيحة بين 1 و999999.',
            'requested_quantity.integer' => 'يرجى إدخال كمية صحيحة.',
        ];
    }
}
