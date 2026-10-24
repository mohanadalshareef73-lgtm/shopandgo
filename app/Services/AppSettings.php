<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Status;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الواجهة الجديدة، محفوظة كـ JSON في settings.general
 */
class AppSettings
{
    public const DEFAULTS = [
        'shop_name' => 'Shop & Go',
        'shop_phone' => '',
        'shop_address' => '',
        'shop_hours' => 'من 10 الصبح لين 10 الليل',
        'ship_price_per_kg' => 28,
        'ship_min' => 15,
        'cash_payment_id' => 1,
        'package_types' => ['كيس', 'ظرف', 'كرتونة صغيرة', 'كرتونة كبيرة', 'شنطة'],
        'shelf_rows' => ['A', 'B', 'C'],
        'shelf_per_row' => 8,
        'shelf_reminder_days' => 5,
        'report_phones' => [],
        'monthly_report_enabled' => true,
        // سطر رابط الحساب تحت رسائل واتساب للعملاء
        'wa_footer_enabled' => true,
        'wa_footer' => "👈 اضغط هنا وشوف كل التفاصيل:\n{رابط}",
        'wa_quote_link_mode' => false,
        'wa_quote_link_text' => "✅ *سعر سلتك جاهز!*\n\nلمعرفة السعر وتفاصيل كل قطعة اضغط هنا 👇\n{رابط}\n\nتقدر تعدل الكميات، وكيف يعجبك أكد طلبك أو اطلب مندوب يجيك لين الحوش 🛵",
        'wa_ack_promo' => "⚡ *تبي تحجز أسرع؟*\nحط رابط سلتك في حسابك، تشوف السعر في ثواني وتأكد حجزك بروحك من غير ما تستنى 🛒✨\n👇\n{رابط}",
        'wa_ack_link' => "⚡ احسب واحجز سلتك بروحك في ثواني 👇\n{رابط}",
        'wa_cart_link_line' => "⚡ احسب واحجز سلتك بروحك في ثواني 👇\n{رابط}",
        'courier_wa_notify' => true,
        'wa_ack_text' => "وصلت سلتك!\n\nمن هنا تبدأ السهولة 🤍\nجاري الآن حساب سلتك وتجهيز السعر بدقة…\n\n⏳ لحظات ونرجعولك بكل التفاصيل.\n\nSHOP AND GO 🤍💙",
        'wa_cart_footer' => "💻 تبي تحسب سلتك بروحك وتحجز في ثواني؟ حطها من حسابك 👇\n{رابط}",
        // النقاط
        'points_enabled' => false,
        'points_per_order' => 0,      // نقاط ثابتة لكل إيصال
        'points_per_100' => 1,        // نقاط لكل 100 د.ل من قيمة الإيصال
        'point_value' => 0.5,         // كم دينار تسوى النقطة وقت الخصم
        'points_min_redeem' => 10,    // أقل عدد نقاط يقدر يستعملهن
        'points_notify' => true,      // ابعث واتساب لما تزيد النقاط
        // التوصيل والمندوبين
        'courier_default_fee' => 10,
        'courier_alert_whatsapp' => false, // ابعث تنبيه المشاكل لأرقام التقارير
        'portal_delivery_enabled' => true,
        'couriers_enabled' => false, // 🛵 المناديب موقوفين: ما فيش تعيين أوتوماتيك ولا واجهة مندوب // الزبون يقدر يطلب توصيل من حسابه
        'points_mode' => 'money',          // money · coupon · both
        'coupon_points' => 100,            // نقاط مقابل كوبون
        'coupon_percent' => 10,            // نسبة خصم الكوبون
        'coupon_max' => 0,                 // أقصى خصم للكوبون (0 = بلا حد)
        'coupon_days' => 30,               // صلاحية الكوبون بالأيام
        'signup_enabled' => true,          // التسجيل الذاتي للعملاء (بموافقة الإدارة)
        'otp_enabled' => true,             // رمز تحقق عبر واتساب عند التسجيل وأول دخول
        'bank_accounts_text' => '',        // بيانات الحساب المصرفي اللي تبان للزبون
        // التذكيرات
        'shelf_auto_remind' => true,       // تذكير الرف يمشي أوتوماتيك كل يوم
        'shelf_remind_every' => 3,         // يتعاود كل كم يوم
        'shelf_remind_max' => 3,           // أقصى عدد تذكيرات لنفس الطرد (0 = بلا حد)
        'shelf_remind_time' => '11:00',
        'debt_remind_enabled' => true,     // يجهز قائمة تذكير الديون (ما ينبعتش إلا بتأكيد)
        'debt_remind_days' => 14,          // عمر الدين قبل ما يستحق تذكير
        'debt_remind_every' => 7,          // ما يتذكرش قبل كم يوم من آخر تذكير
        'debt_remind_min' => 5,            // أقل مبلغ
        // التقرير اليومي
        'daily_report_enabled' => false,
        'daily_report_time' => '22:00',
        // النسخ الاحتياطي
        'backup_enabled' => true,
        'backup_email' => '',
        'backup_whatsapp' => true,         // رسالة لأرقام الإدارة إن النسخة جاهزة
        'backup_day' => 5,                 // 0 الأحد … 5 الجمعة 6 السبت
        'backup_time' => '03:00',
        'backup_keep' => 8,                // كم نسخة نخلوا على السيرفر
        'backup_files' => true,            // نزيدو الصور والإيصالات المرفوعة
        // بطاقات SHEIN
        'shein_card_digits' => 16,
        'shein_pin_digits' => 4,
        'card_sale_rate' => 0,            // سعر الدولار لبيع بطاقات SHEIN (0 = آخر سعر شراء)
        // ترقيم الطلبات
        'order_prefix' => 'SG-',
        'order_start' => 20001,
        'ship_prefix' => 'SH-',
        'ship_start' => 1001,
        // الشحن فقط: سعر الكيلو بالدولار لكل بلد + عنوان المخزن ({الكود} يتبدل بكود العميل)
        'ship_origins' => [
            ['key' => 'cn', 'name' => 'الصين', 'flag' => '🇨🇳', 'city' => 'قوانغجو', 'per_kg' => 11, 'min' => 5, 'days' => '12 – 18 يوماً', 'active' => true,
                'address' => "{الكود} {الاسم}\nRoom 1203, Baiyun Rd 88\nBaiyun District, Guangzhou\nGuangdong 510000, China\nTel: +86 138 0000 1234"],
            ['key' => 'ae', 'name' => 'دبي', 'flag' => '🇦🇪', 'city' => 'ديرة', 'per_kg' => 6, 'min' => 3, 'days' => '5 – 8 أيام', 'active' => true,
                'address' => "{الكود} {الاسم}\nWarehouse 7, Al Ras\nDeira, Dubai, UAE\nTel: +971 50 000 1234"],
            ['key' => 'us', 'name' => 'أمريكا', 'flag' => '🇺🇸', 'city' => 'ديلاوير', 'per_kg' => 14, 'min' => 7, 'days' => '15 – 25 يوماً', 'active' => true,
                'address' => "{الكود} {الاسم}\n123 Commerce St, Suite 400\nWilmington, DE 19801, USA\nTel: +1 302 000 1234"],
        ],
        'platform_notes' => [],            // ملاحظة لكل منصة تظهر للعميل (المدة ونوع البضاعة)
        // التنبيهات
        'free_order_alert_days' => 2,      // طلب ليس في رحلة منذ كم يوم
        'trip_late_days' => 10,            // رحلة لم تصل منذ كم يوم
        'kpi_late_days' => 21,             // 📊 الإيصال يعتبر متأخر لو وصل بعد كم يوم
        'review_requests' => true,         // ⭐ رسالة تقييم بعد التسليم
        // المندوب
        'courier_writeoff_max' => 5,       // أقصى فرق يُشطب على الشركة إذا قبض المندوب أقل من المطلوب
    ];

    protected static ?array $cache = null;

    protected static ?array $stageCache = null;

    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }
        $saved = [];
        try {
            if (Schema::hasColumn('settings', 'general')) {
                $raw = Setting::query()->value('general');
                $saved = $raw ? (json_decode($raw, true) ?: []) : [];
            }
        } catch (\Throwable $e) {
            $saved = [];
        }

        return static::$cache = array_merge(self::DEFAULTS, $saved);
    }

    public static function defaultFaq(): string
    {
        return "س: كم تاخد الطلبية لين توصل؟\nج: من الصين عادة 12 – 18 يوم، ومن دبي 5 – 8 أيام، ومن أمريكا 15 – 25 يوم بعد الشراء.\n"
            . "س: كيف ندفع؟\nج: تقدر تدفع عربون بتحويل مصرفي وترفع الإيصال من حسابك، أو كاش في المحل، أو للمندوب.\n"
            . "س: شن الممنوع شحنه؟\nج: السوائل القابلة للاشتعال، البطاريات المنفصلة، الأدوية بدون وصفة، وأي حاجة ممنوعة قانونياً.\n"
            . "س: لو القطعة جت غلط أو فيها عيب؟\nج: بلغنا من «لدي مشكلة» خلال 3 أيام من الاستلام مع صورة، ونتابعوها مع المتجر.\n"
            . "س: سياسة الإرجاع؟\nج: المرتجع يكون حسب سياسة المتجر الأصلي، وقيمة الشحن ما ترجعش.\n"
            . "س: الجمارك والرسوم؟\nج: السعر اللي يطلعلك في حسابك شامل، وما فيه رسوم مخفية إلا لو تبلغت بيها قبل.";
    }

    public static function get(string $key, $default = null)
    {
        return static::all()[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        $merged = array_merge(static::all(), $values);
        $row = Setting::query()->first();
        if (!$row) {
            $row = new Setting;
            $row->id = 1;
        }
        $row->general = json_encode($merged, JSON_UNESCAPED_UNICODE);
        $row->save();
        static::$cache = $merged;
    }

    /** أرقام الإدارة اللي تجيها التقارير والتنبيهات */
    public static function reportPhones(): array
    {
        $v = static::get('report_phones', []);
        if (is_string($v)) {
            $v = preg_split('/[\s,،]+/u', $v);
        }

        return array_values(array_filter(array_map('trim', (array) $v)));
    }

    /** أنواع رسائل واتساب اللي تجي لأرقام الإدارة (نقدر نوقف أي نوع لأي رقم) */
    public const WA_KINDS = [
        'alert_orders' => '📦 تنبيهات الطلبات', 'alert_trips' => '✈ تنبيهات الرحلات', 'alert_customers' => '👤 بلاغات وتنبيهات العملاء',
        'alert_couriers' => '🛵 مشاكل المندوبين', 'alert_staff' => '🔐 الموظفين والأمان', 'alert_system' => '⚙ تنبيهات النظام',
        'shift' => '💵 تقفيل الورديات', 'daily' => '📊 التقرير اليومي', 'biweekly' => '📊 تقرير كل 14 يوم', 'monthly' => '📊 التقرير الشهري',
        'backup' => '🔒 النسخ الاحتياطي', 'errors' => '🛠 أخطاء النظام',
    ];

    /** أرقام الإدارة اللي مفعّل عندها هذا النوع من الرسائل */
    /** 🔕 إشعارات الجوال للمسؤول موقفة؟ (تقعد في المنظومة بس) */
    public static function mobileOff(): bool
    {
        return (bool) static::get('admin_mobile_off', false);
    }

    public static function phonesFor(string $kind): array
    {
        if (static::mobileOff()) {
            return []; // ما يمشي حتى واتساب للمسؤول
        }
        $mute = (array) static::get('wa_mute', []);

        return array_values(array_filter(static::reportPhones(), fn ($ph) => !in_array($kind, (array) ($mute[$ph] ?? []), true)));
    }

    /** السطر اللي يتزاد تحت رسائل العملاء (مع رابط المنظومة) */
    public static function waFooter(string $key = 'wa_footer'): string
    {
        if (!static::get('wa_footer_enabled', true)) {
            return '';
        }
        try {
            $link = route('customer.login');
        } catch (\Throwable $e) {
            $link = rtrim((string) config('app.url'), '/') . '/login';
        }
        $t = trim((string) static::get($key, self::DEFAULTS[$key] ?? ''));

        return $t === '' ? '' : str_replace('{رابط}', $link, $t);
    }

    public static function cashPaymentId(): int
    {
        return (int) static::get('cash_payment_id', 1);
    }

    /** prep أو way حسب إعداد حالة الرحلة */
    public static function statusStage(?string $status): string
    {
        if (!$status) {
            return 'prep';
        }
        if (static::$stageCache === null) {
            static::$stageCache = Status::query()->pluck('stage', 'name')->all();
        }

        return static::$stageCache[$status] ?? 'way';
    }

    public static function shelves(): array
    {
        $out = [];
        $rows = array_values(array_filter((array) static::get('shelf_rows'), fn ($r) => trim((string) $r) !== ''));
        if (!$rows) {
            // بدون حروف: الرفوف أرقام بس (1، 2، 3 …)
            return array_map('strval', range(1, max(1, (int) static::get('shelf_per_row', 15))));
        }
        foreach ($rows as $row) {
            for ($i = 1; $i <= (int) static::get('shelf_per_row', 8); $i++) {
                $out[] = $row . '-' . $i;
            }
        }

        return $out;
    }

    /** بلدان الشحن (المفعّلة فقط إذا $active) مفهرسة بالمفتاح */
    public static function origins(bool $active = false): array
    {
        $out = [];
        foreach ((array) static::get('ship_origins', []) as $o) {
            if (!is_array($o) || empty($o['key'])) {
                continue;
            }
            if ($active && isset($o['active']) && !$o['active']) {
                continue;
            }
            $out[$o['key']] = $o;
        }

        return $out;
    }

    public static function shipPriceFor(float $kilo): float
    {
        $price = round($kilo * (float) static::get('ship_price_per_kg', 28));

        return max($price, (float) static::get('ship_min', 0));
    }
}
