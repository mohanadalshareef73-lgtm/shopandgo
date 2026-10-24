<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\CartQuote;
use App\Models\Notif_orgs;
use App\Models\Setting;
use App\Support\AmazonMessageTemplates;
use App\Support\IherbMessageTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:system-list|system-edit', ['only' => ['edit', 'replyImage']]);
        $this->middleware('permission:system-edit', ['only' => ['update']]);
    }

    public function edit()
    {
        $settings = Setting::findOrFail(1);
        $main_notifications = Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();

        return view('admin.settings.edit', compact('settings', 'main_notifications'));
    }

    public function update(Request $request)
    {
        $imageKeys = Setting::replyImageKeys();
        $storeRules = [];
        foreach (array_keys(CartQuote::RETAILERS) as $retailer) {
            $storeRules[$retailer.'_enabled'] = ['sometimes', 'required', 'boolean'];
        }
        $amazonMessageRules = ['amazon_messages' => ['sometimes', 'required',
            'array:'.implode(',', array_keys(AmazonMessageTemplates::DEFINITIONS))]];
        foreach (array_keys(AmazonMessageTemplates::DEFINITIONS) as $status) {
            $amazonMessageRules['amazon_messages.'.$status] = ['sometimes', 'required', 'string', 'max:2000'];
        }
        $iherbMessageRules = ['iherb_messages' => ['sometimes', 'required',
            'array:'.implode(',', array_keys(IherbMessageTemplates::DEFINITIONS))]];
        foreach (array_keys(IherbMessageTemplates::DEFINITIONS) as $status) {
            $iherbMessageRules['iherb_messages.'.$status] = ['sometimes', 'required', 'string', 'max:2000'];
        }
        $validated = $request->validate([
            'reply_images' => ['sometimes', 'array:'.implode(',', $imageKeys)],
            'reply_images.*' => ['bail', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120',
                function ($attribute, $file, $fail) {
                    $image = @getimagesize($file->getRealPath());
                    if (! $image || ! in_array($image['mime'], ['image/jpeg', 'image/png'], true)) {
                        $fail('يرجى اختيار صورة JPG أو PNG صالحة.');
                    }
                }],
            'remove_reply_images' => ['sometimes', 'array'],
            'remove_reply_images.*' => ['required', 'string', Rule::in($imageKeys)],
            'usd_to_lyd_rate' => [
                'bail', 'required', 'string',
                'regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,6})?$/D',
                'not_regex:/^0(?:\.0+)?$/D',
            ],
            'shein_company_percentage' => ['sometimes', 'required', 'string',
                'regex:/^(?:0|[1-9]\d{0,3})(?:\.\d{1,4})?$/D', 'numeric', 'between:0,1000'],
            'amazon_fixed_fee_usd' => ['sometimes', 'required', 'string', 'regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,4})?$/D'],
            'amazon_flat_surcharge_usd' => ['sometimes', 'required', 'string', 'regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,4})?$/D'],
            'amazon_threshold_usd' => ['sometimes', 'required', 'string', 'regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,4})?$/D', 'not_regex:/^0(?:\.0+)?$/D'],
            'amazon_percentage' => ['sometimes', 'required', 'string', 'regex:/^(?:0|[1-9]\d{0,3})(?:\.\d{1,4})?$/D', 'numeric', 'between:0,1000'],
            'shein_received_message' => ['sometimes', 'required', 'string', 'max:2000'],
            'shein_product_message' => ['sometimes', 'required', 'string', 'max:2000'],
            'shein_completed_message' => ['sometimes', 'required', 'string', 'max:2000'],
            'shein_confirm_reply' => ['sometimes', 'required', 'string', 'max:2000'],
            'shein_edit_reply' => ['sometimes', 'required', 'string', 'max:2000'],
            'shein_cancel_reply' => ['sometimes', 'required', 'string', 'max:2000'],
            'cart_cash_reply' => ['sometimes', 'required', 'string', 'max:2000'],
            'cart_bank_reply' => ['sometimes', 'required', 'string', 'max:2000'],
            'shein_calculation_note' => ['sometimes', 'required', 'string', 'max:4000'],
            'shein_pause_on_empty_wallet' => ['sometimes', 'required', 'boolean'],
        ] + $amazonMessageRules + $iherbMessageRules + $storeRules, [
            'reply_images.*.image' => 'يرجى اختيار ملف صورة صالح.',
            'reply_images.*.mimes' => 'الصيغ المسموحة للصورة هي JPG وPNG.',
            'reply_images.*.max' => 'يجب ألا يتجاوز حجم الصورة 5 ميغابايت.',
            'usd_to_lyd_rate.required' => 'يرجى إدخال سعر صرف الدولار إلى الدينار الليبي.',
            'usd_to_lyd_rate.string' => 'يرجى إدخال سعر صرف صحيح.',
            'usd_to_lyd_rate.regex' => 'أدخل سعراً موجباً لا يتجاوز 999999.999999 وبحد أقصى 6 منازل عشرية.',
            'usd_to_lyd_rate.not_regex' => 'يجب أن يكون سعر الصرف أكبر من صفر.',
            'shein_company_percentage.between' => 'يجب أن تكون نسبة الشركة بين 0 و1000 بالمئة.',
            'shein_company_percentage.regex' => 'أدخل نسبة صحيحة بدقة تصل إلى 4 منازل عشرية.',
        ]);

        $uploads = $validated['reply_images'] ?? [];
        $removeImages = $validated['remove_reply_images'] ?? [];
        unset($validated['reply_images'], $validated['remove_reply_images']);
        DB::transaction(function () use ($validated, $uploads, $removeImages) {
            $settings = Setting::lockForUpdate()->findOrFail(1);
            $images = $settings->reply_images ?? [];
            $previousImages = $images;
            foreach ($removeImages as $key) {
                unset($images[$key]);
            }
            foreach ($uploads as $key => $upload) {
                if (! $upload) {
                    continue;
                }
                $extension = $upload->getMimeType() === 'image/png' ? 'png' : 'jpg';
                $path = $upload->storeAs('whatsapp/reply-images', Str::uuid().'.'.$extension, 'local');
                if (! $path) {
                    throw new \RuntimeException('تعذر حفظ صورة الرد. حاول مرة أخرى.');
                }
                $images[$key] = $path;
            }
            // Immutable files remain available to replies already awaiting retries.
            if ($images !== $previousImages) {
                $validated['reply_images'] = $images;
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل صور ردود واتساب', 'log' => 'تم تحديث صور الردود. تطبق على الردود الجديدة.']);
            }
            $previousRate = $settings->usd_to_lyd_rate ?? 'غير محدد';
            $previousPercentage = $settings->shein_company_percentage;
            $previousAmazonRules = $settings->only(['amazon_fixed_fee_usd', 'amazon_flat_surcharge_usd', 'amazon_threshold_usd', 'amazon_percentage']);
            $previousAmazonMessages = $settings->amazon_messages ?? [];
            $previousIherbMessages = $settings->iherb_messages ?? [];
            $previousStores = [];
            foreach (array_keys(CartQuote::RETAILERS) as $retailer) {
                $previousStores[$retailer] = $settings->isCartStoreEnabled($retailer);
            }
            if (array_key_exists('amazon_messages', $validated)) {
                $validated['amazon_messages'] = array_replace($previousAmazonMessages, $validated['amazon_messages']);
            }
            if (array_key_exists('iherb_messages', $validated)) {
                $validated['iherb_messages'] = array_replace($previousIherbMessages, $validated['iherb_messages']);
            }
            $changedReplies = [];
            foreach (CartQuote::PAYMENT_METHODS as $method => $label) {
                $field = 'cart_'.$method.'_reply';
                if (array_key_exists($field, $validated) && $validated[$field] !== $settings->cartPaymentReplyTemplate($method)) {
                    $changedReplies[] = $label;
                }
            }
            foreach (['confirm' => 'تأكيد الطلب', 'edit' => 'تعديل الكميات', 'cancel' => 'إلغاء الطلب'] as $action => $label) {
                $field = 'shein_'.$action.'_reply';
                if (array_key_exists($field, $validated) && $validated[$field] !== $settings->cartActionReplyTemplate($action)) {
                    $changedReplies[] = $label;
                }
            }
            $settings->update($validated);

            $changedStores = [];
            foreach (CartQuote::RETAILERS as $retailer => $label) {
                if ($settings->isCartStoreEnabled($retailer) !== $previousStores[$retailer]) {
                    $changedStores[] = $label.': '.($settings->isCartStoreEnabled($retailer) ? 'مفعل' : 'متوقف');
                }
            }
            if ($changedStores) {
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل تفعيل المتاجر', 'log' => implode('، ', $changedStores).'.']);
            }

            if (($settings->amazon_messages ?? []) !== $previousAmazonMessages) {
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل رسائل حالات أمازون',
                    'log' => 'تم تحديث رسائل واتساب لحالات أمازون. تطبق الرسائل المحفوظة على الردود الجديدة.']);
            }
            if (($settings->iherb_messages ?? []) !== $previousIherbMessages) {
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل رسائل حالات وأزرار آي هيرب',
                    'log' => 'تم تحديث رسائل حالات واتساب وردود أزرار آي هيرب. تطبق الرسائل المحفوظة على الردود الجديدة.']);
            }

            $amazonRules = $settings->only(array_keys($previousAmazonRules));
            if ($amazonRules !== $previousAmazonRules) {
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل قواعد تسعير أمازون',
                    'log' => 'تم تعديل قواعد تسعير أمازون من '.json_encode($previousAmazonRules)
                        .' إلى '.json_encode($amazonRules).'. تطبق على عروض الأسعار الجديدة فقط.']);
            }

            if ($changedReplies !== []) {
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل ردود أزرار السلة في واتساب',
                    'log' => 'تم تعديل رسائل الرد على: '.implode('، ', $changedReplies).'.']);
            }

            if (array_key_exists('usd_to_lyd_rate', $validated)) {
                Log::create([
                    'user_id' => auth()->id(),
                    'type' => 'settings',
                    'title' => 'تعديل سعر صرف الدولار إلى الدينار الليبي',
                    'log' => 'تم تعديل سعر الصرف من ('.$previousRate.') إلى ('.$settings->usd_to_lyd_rate.') دينار لكل دولار.',
                ]);
            }
            if (array_key_exists('shein_company_percentage', $validated)
                || array_key_exists('shein_received_message', $validated)
                || array_key_exists('shein_product_message', $validated)
                || array_key_exists('shein_completed_message', $validated)
                || array_key_exists('shein_calculation_note', $validated)
                || array_key_exists('shein_pause_on_empty_wallet', $validated)) {
                Log::create(['user_id' => auth()->id(), 'type' => 'settings',
                    'title' => 'تعديل إعدادات عروض شي إن',
                    'log' => 'تم حفظ إعدادات الرسائل وحساب العرض. نسبة الشركة من ('.$previousPercentage
                        .') إلى ('.$settings->shein_company_percentage.') بالمئة. إيقاف ردود السلات عند نفاد الرصيد: '
                        .($settings->shein_pause_on_empty_wallet ? 'مفعل' : 'غير مفعل').'.']);
            }
        });

        return redirect()->route('settings.edit')->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    public function replyImage(string $key)
    {
        abort_unless(in_array($key, Setting::replyImageKeys(), true), 404);
        $path = Setting::findOrFail(1)->replyImagePath($key);
        abort_unless($path && preg_match('~^whatsapp/reply-images/[a-f0-9-]{36}\.(?:jpg|png)$~D', $path)
            && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    public function clearCacheAndCookies(Request $request)
    {
        // Clear Laravel cache (routes, config, views, etc.)
        if (function_exists('opcache_reset')) {
            @opcache_reset(); // ملفات PHP الجديدة تشتغل طول (مش النسخة القديمة من الذاكرة)
        }
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        $fast = \App\Support\Optimizer::run(); // ⚡ نرجعو نسرّعوها بعد المسح
        // Clear session and cookies
        // Assuming you want to delete all cookies
        foreach ($request->cookies as $name => $value) {
            Cookie::queue(Cookie::forget($name));
        }
        // Optional: Set a response message
        return redirect()->route('home')->with(['success' => 'تم مسح الكاش' . ((!empty($fast['config']) && !empty($fast['routes'])) ? ' ⚡ والمنظومة متسرّعة' : ' (التسريع: ' . (!empty($fast['config']) ? 'الإعدادات ✓' : 'الإعدادات ✗') . ' · ' . (!empty($fast['routes']) ? 'الروابط ✓' : 'الروابط ✗') . ')')]);
    }
}
