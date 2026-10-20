<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\WhatsappOutbox;
use App\Models\whatsappMessage;

/**
 * كل رسائل الواتساب تمشي من هنا: تجيب القالب، تعبي الخانات، ترسل، وسُجّل في whatsapp_outbox.
 * الخانات بالعربي مثل {الاسم} {الباقي}، والخانات القديمة {{$customer->name}} تخدم مثل ما هي.
 */
class MessageService
{
    public const VARIABLES = [
        '{الاسم}' => 'اسم العميل',
        '{كود_الزبون}' => 'كود العميل',
        '{رقم_الإيصال}' => 'رقم الإيصال',
        '{الرحلة}' => 'رقم الرحلة',
        '{الحالة}' => 'حالة الرحلة',
        '{القطع_الواصلة}' => 'القطع الذي وصلت',
        '{القطع_المطلوبة}' => 'القطع المطلوبة',
        '{الطرود}' => 'عدد الطرود',
        '{الوزن}' => 'الوزن',
        '{الرف}' => 'الرف (لا يُرسل للعميل)',
        '{الفاتورة}' => 'قيمة الفاتورة',
        '{الشحن}' => 'الشحن',
        '{المدفوع}' => 'المدفوع',
        '{المدفوع_الآن}' => 'المدفوع في العملية',
        '{الباقي}' => 'الباقي على الإيصال',
        '{الباقي_الكلي}' => 'كل الذي على العميل',
        '{أيام_الرف}' => 'أيام في الرف',
        '{رابط_الفاتورة}' => 'رابط الفاتورة',
        '{رابط_الموقع}' => 'رابط الموقع',
        '{المندوب}' => 'اسم المندوب',
        '{تليفون_المندوب}' => 'هاتف المندوب',
        '{الملاحظة}' => 'نص الملاحظة',
        '{النقاط}' => 'النقاط الجديدة',
        '{رصيد_النقاط}' => 'رصيد النقاط',
        '{قيمة_النقاط}' => 'قيمة النقاط بالدينار',
        '{المرتجع}' => 'تفاصيل المرتجع',
        '{أيام_الدين}' => 'عمر الدين بالأيام',
    ];

    public static function fmt($n): string
    {
        return TreasuryService::fmt($n);
    }

    public static function template(string $type): ?whatsappMessage
    {
        return whatsappMessage::where('type', $type)->first();
    }

    public static function enabled(string $type): bool
    {
        $t = static::template($type);

        return $t && ($t->enabled ?? true);
    }

    /** الخانات لإيصال معين */
    public static function orderVars(Order $order, array $extra = []): array
    {
        $c = $order->customer;
        $vars = [
            '{الاسم}' => optional($c)->name,
            '{كود_الزبون}' => optional($c)->code,
            '{الهاتف}' => optional($c)->phone_1,
            '{رقم_الإيصال}' => $order->code,
            '{الرحلة}' => $order->trip_details,
            '{الحالة}' => $order->trip_status,
            '{القطع_الواصلة}' => $order->received_qty ?? $order->qty,
            '{القطع_المطلوبة}' => $order->qty,
            '{الطرود}' => $order->bags_num,
            '{الوزن}' => static::fmt($order->kilo),
            '{الرف}' => '', // مكان التخزين للإدارة والموظفين والمندوب فقط، لا يظهر للعميل
            '{الفاتورة}' => static::fmt($order->total_price - $order->discount),
            '{الشحن}' => static::fmt($order->ship_price),
            '{المدفوع}' => static::fmt($order->paid_price),
            '{الباقي}' => static::fmt(max(0, $order->remaining())),
            '{أيام_الرف}' => $order->shelfDays(),
            '{رابط_الفاتورة}' => rtrim(config('app.url'), '/') . '/order-invoice/' . $order->code,
            '{رابط_الموقع}' => config('app.url'),
            '{رابط_الحساب}' => rtrim((string) config('app.url'), '/') . '/login',
            // 🛵 رابط طلب مندوب (من غير دخول) — يطلع بس لو الطلبية ما تسلمتش
            '{رابط_التوصيل}' => $order->id && !$order->delivered ? \App\Support\DeliveryLink::url($order) : '',
            '{دخول_الحساب}' => $c ? static::loginText($c) : '',
            // القديمة
            '{{$customer->name}}' => optional($c)->name,
            '{{$customer->code}}' => optional($c)->code,
            '{{$order1->customer->name}}' => optional($c)->name,
            '{{$order1->customer->code}}' => optional($c)->code,
            '{{$order1->code}}' => $order->code,
            '{{$order1->trip_details}}' => $order->trip_details,
            '{{$order1->trip_status}}' => $order->trip_status,
            '{{$order1->note}}' => $order->note,
            '{{$invoice_link}}' => $order->code,
            '{{$app_url}}' => config('app.url'),
            '{{$shipPrice}}' => static::fmt($order->ship_price),
            '{{$total}}' => static::fmt(max(0, $order->remaining())),
            '{{$currency}}' => $order->currency_sign ?: 'د.ل',
            '{{$name}}' => optional($c)->name,
            '{{$customer_code}}' => optional($c)->code,
            '{{$order_code}}' => $order->code,
            '{{$pieces}}' => $order->received_qty ?? $order->qty,
            '{{$parcels}}' => $order->bags_num,
            '{{$weight}}' => static::fmt($order->kilo),
            '{{$ship_price}}' => static::fmt($order->ship_price),
            '{{$grand_total}}' => static::fmt($order->grandTotal()),
            '{{$paid}}' => static::fmt($order->paid_price),
            '{{$remaining}}' => static::fmt(max(0, $order->remaining())),
        ];

        return array_merge($vars, $extra);
    }

    /** 👤 بيانات الدخول للحساب: أول مرة (بدون كلمة مرور) = رقم الهاتف + رمز واتساب وبعدها يختار كلمة جديدة */
    public static function loginText(Customer $c): string
    {
        $url = rtrim((string) config('app.url'), '/') . '/login';
        if (empty($c->password)) {
            return "👤 *حسابك في المنظومة* 👇\n{$url}\n📱 اسم المستخدم: *{$c->phone_1}*\n🔑 أول مرة: كلمة المرور هي *رقم هاتفك*، ويجيك رمز تحقق على الواتساب، وبعدها تختار كلمة مرور جديدة";
        }

        return "👤 *حسابك في المنظومة* 👇\n{$url}\n📱 اسم المستخدم: *{$c->phone_1}* · كودك {$c->code}\n🔑 نسيت كلمة المرور؟ اضغط «نسيت كلمة المرور» ويجيك رمز على الواتساب";
    }

    public static function customerVars(Customer $c, array $extra = []): array
    {
        $due = Order::where('customer_id', $c->id)->where('status', '!=', 'canceld')->get()->sum(fn ($o) => max(0, $o->remaining()));

        return array_merge([
            '{الاسم}' => $c->name,
            '{كود_الزبون}' => $c->code,
            '{الباقي_الكلي}' => static::fmt($due),
            '{رابط_الموقع}' => config('app.url'),
            '{دخول_الحساب}' => static::loginText($c),
        ], $extra);
    }

    public static function render(string $text, array $vars): string
    {
        return strtr($text, array_map(fn ($v) => (string) ($v ?? ''), $vars));
    }

    /** يبعث قالب لزبون. يرجع true إذا أُرسلت. */
    public static function sendTemplate(string $type, ?Customer $customer, array $vars, ?Order $order = null, ?array $phones = null): bool
    {
        $tpl = static::template($type);
        if (!$tpl || !($tpl->enabled ?? true)) {
            return false;
        }
        $phones = $phones ?? array_values(array_filter([optional($customer)->phone_1, optional($customer)->phone_2]));

        return static::sendText($type, static::render($tpl->message, $vars), $phones, $customer, $order);
    }

    /** يجرب الرقم الأول، وإذا فشل يجرب الثاني */
    public static function sendText(string $type, string $text, array $phones, ?Customer $customer = null, ?Order $order = null, ?string $logText = null, ?string $session = null): bool
    {
        // نسخة في حساب العميل (الرسائل الإدارية ورموز التحقق لا تُسجَّل)
        if ($customer && !in_array($type, ['otp', 'alert', 'report', 'backup'], true)) {
            CustomerNotify::inbox($customer, $logText ?? $text, $order, $type);
        }
        // رسائل العملاء: نزيدو تحتها رابط حسابه في المنظومة (باش يتعود يدخل)
        if ($customer && !in_array($type, ['otp', 'alert', 'report', 'backup', 'shift_closed'], true)
            && !str_contains($text, parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'shopandgo.ly')) {
            $f = AppSettings::waFooter();
            $text .= $f !== '' ? "\n\n" . $f : '';
        }
        $phones = array_values(array_filter($phones));
        if (!$phones) {
            static::log($type, null, $logText ?? $text, 'failed', 'لا يوجد رقم', $customer, $order);

            return false;
        }
        foreach ($phones as $phone) {
            $error = null;
            $ok = false;
            try {
                $body = ['phone' => [static::normalize($phone)], 'message' => $text];
                // أزرار رد تحت الرسالة (وصّلهالي / توقف / ندفع كاش…)
                $ref = in_array($type, ['arrived', 'shelf_reminder', 'soldout_notice'], true) ? optional($order)->id : optional($customer)->id;
                if ($btns = SgButtons::for($type, $ref)) {
                    $body['buttons'] = $btns;
                }
                $response = app(WhatsAppService::class)->send($body, $session ?: null, 15);
                if (!$response->successful() && isset($body['buttons'])) {
                    unset($body['buttons']); // الأزرار ما مشتش؟ نبعتوها بدونها باش الرسالة توصل
                    $response = app(WhatsAppService::class)->send($body, $session ?: null, 15);
                }
                $ok = $response->successful();
                $error = $ok ? null : 'HTTP ' . $response->status();
            } catch (\Throwable $e) {
                $error = mb_substr($e->getMessage(), 0, 180);
            }
            static::log($type, $phone, $logText ?? $text, $ok ? 'sent' : 'failed', $error, $customer, $order);
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * الرقم بصيغة واتساب الدولية (بدون + ولا 00).
     * ليبي: 09xxxxxxxx أو 9xxxxxxxx أو 2189xxxxxxxx → 2189xxxxxxxx
     * دولي: +971… أو 00971… أو رقم طويل يبدا بكود دولة → يبقى زي ما هو
     */
    public static function normalize(string $phone): string
    {
        $raw = trim($phone);
        $intl = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $d = preg_replace('/\D/', '', $raw);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '218')) {
            return substr($d, 0, 12);
        }
        $local = ltrim($d, '0');
        // رقم ليبي محلي (9 أرقام تبدا بـ 9، أو 10 أرقام تبدا بـ 09)
        if (!$intl && strlen($local) === 9 && $local[0] === '9') {
            return '218' . $local;
        }
        if ($intl || strlen($local) >= 10) {
            return substr($local, 0, 15); // دولي
        }

        return substr('218' . $local, 0, 12);
    }

    protected static function log($type, $phone, $text, $status, $error, $customer, $order): void
    {
        try {
            WhatsappOutbox::create([
                'type' => $type, 'phone' => $phone, 'body' => $text, 'status' => $status, 'error' => $error,
                'customer_id' => optional($customer)->id, 'order_id' => optional($order)->id, 'user_id' => auth('web')->id(),
            ]);
        } catch (\Throwable $e) {
            // ما نوقفوش الشغل إذا السجل فشل
        }
    }
}
