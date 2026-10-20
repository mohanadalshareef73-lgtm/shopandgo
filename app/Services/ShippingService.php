<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Log;
use App\Models\Order;
use RuntimeException;

/**
 * الشحن فقط: العميل يشتري بنفسه ونحن نشحن له من الصين / دبي / أمريكا.
 * الشحنة تُسجَّل كإيصال (kind = shipping) حتى تمر بالتسليم والدفع والديون والفاتورة كبقية الطلبات.
 *  - السعر بالدولار: الوزن × (سعر الكيلو − التخفيض) مع حد أدنى للشحنة
 *  - يتحول للدينار بسعر الصرف وقت التسجيل (ship_price)
 */
class ShippingService
{
    public const STAGES = [
        'expected' => ['سجّلها العميل', '#8a90a3'],
        'warehouse' => ['في المخزن بالخارج', '#6250d6'],
        'transit' => ['في الطريق', '#2a78d6'],
        'arrived' => ['وصلت ليبيا', '#12805c'],
        'courier' => ['مع المندوب', '#eda100'],
        'delivered' => ['تم التسليم', '#b7bccb'],
        'cancelled' => ['ملغاة', '#c4352f'],
    ];

    public static function stageOf(Order $o): string
    {
        if ($o->status === 'canceld') {
            return 'cancelled';
        }
        if ($o->delivered) {
            return 'delivered';
        }
        if ($o->delivery_id) {
            return 'courier';
        }
        if ($o->arrived) {
            return 'arrived';
        }

        return $o->ship_stage ?: 'warehouse';
    }

    public static function rate(): float
    {
        return (float) (\App\Models\CurrencyExchange::latestRate() ?: 1);
    }

    /** 💱 سعر صرف الشحن (دينار لكل 1$): كاش ومصرف — من إعدادات أسعار الشحن، وإن ما اتكتبوش نرجعو لآخر سعر صرف */
    public static function rates(): array
    {
        $base = static::rate();
        $cash = (float) AppSettings::get('ship_rate_cash', 0);
        $bank = (float) AppSettings::get('ship_rate_bank', 0);

        return ['cash' => $cash > 0 ? $cash : $base, 'bank' => $bank > 0 ? $bank : $base];
    }

    /** الشحنة سعرها بالدولار (يتحسب بسعرين)؟ لو الموظف كتبه بالدينار مباشرة، أو شحنة قديمة قبل السعرين، يكون سعر واحد */
    public static function isDual(Order $o): bool
    {
        return $o->kind === 'shipping' && ($o->ship_cur ?? null) !== 'lyd' && (float) $o->usd_amount > 0 && (float) ($o->ship_rate_cash ?? 0) > 0 && (float) ($o->ship_rate_bank ?? 0) > 0;
    }

    /** السعرين بالدينار لشحنة: ['cash' => ..., 'bank' => ...] (المحفوظ وقت التسجيل) */
    public static function dualPrices(Order $o): array
    {
        $r = static::rates();
        $rc = (float) ($o->ship_rate_cash ?: $r['cash']);
        $rb = (float) ($o->ship_rate_bank ?: $r['bank']);

        return ['cash' => round((float) $o->usd_amount * $rc, 2), 'bank' => round((float) $o->usd_amount * $rb, 2)];
    }

    /** يختار السعر المعتمد (كاش/مصرف) ويحدّث سعر الشحن على الإيصال */
    public static function applyPay(Order $o, string $pay): void
    {
        if (!static::isDual($o) || !in_array($pay, ['cash', 'bank'], true)) {
            return;
        }
        $o->ship_pay = $pay;
        $o->ship_price = static::dualPrices($o)[$pay];
        $o->refreshPaymentStatus();
    }

    /** أنواع الشحن لعناوين المخازن */
    public const MODES = ['جوي' => '✈️', 'بحري' => '🚢'];

    /** 📍 عناوين البلد: كل عنوان (جوي/بحري) بسعره ونقدر نلغيه — القديم (عنوان واحد) يولّي «جوي» */
    public static function addrs(array $o, bool $activeOnly = true): array
    {
        $l = array_values(array_filter((array) ($o['addrs'] ?? []), fn ($a) => is_array($a) && trim((string) ($a['address'] ?? '')) !== ''));
        if (!$l) {
            $l = [['mode' => 'جوي', 'address' => (string) ($o['address'] ?? ''), 'per_kg' => (float) ($o['per_kg'] ?? 0), 'active' => true]];
        }

        return $activeOnly ? array_values(array_filter($l, fn ($a) => !isset($a['active']) || $a['active'])) : $l;
    }

    /** سعر الكيلو حسب نوع الشحن (لو العنوان متاعه موجود ومفعّل) وإلا سعر البلد */
    public static function modePrice(array $o, ?string $mode): float
    {
        if ($mode) {
            foreach (static::addrs($o) as $a) {
                if (($a['mode'] ?? '') === $mode) {
                    return (float) ($a['per_kg'] ?? 0);
                }
            }
        }

        return (float) ($o['per_kg'] ?? 0);
    }

    /** سعر الكيلو للعميل: السعر الخاص إن وُجد وإلا سعر البلد (حسب نوع الشحن) */
    public static function perKg(?Customer $c, string $origin, ?string $mode = null): float
    {
        $o = AppSettings::origins()[$origin] ?? null;
        $base = $o ? static::modePrice($o, $mode) : 0.0;
        $special = $c ? (json_decode((string) $c->ship_prices, true) ?: []) : [];

        if (isset($special[$origin]) && $special[$origin] !== '' && $special[$origin] !== null) {
            return (float) $special[$origin]; // السعر الخاص متفق عليه، ما يتزادلهش خصم المستوى
        }

        return max(0, $base - LoyaltyService::shipDiscount($c)); // خصم مستوى العميل (برونزي/فضي/ذهبي)
    }

    public static function calc(string $origin, float $kg, float $perKg, float $discount): array
    {
        $o = AppSettings::origins()[$origin] ?? [];
        $perKg = static::tierPrice($o, $kg, $perKg); // تخفيض حسب الوزن
        $net = max(0, $perKg - $discount);
        $usd = $kg > 0 ? max((float) ($o['min'] ?? 0), round($kg * $net, 2)) : 0;
        $r = static::rates();

        // lyd = سعر الكاش (الافتراضي)، و lyd_bank = سعر المصرف
        return ['usd' => $usd, 'rate' => $r['cash'], 'rate_cash' => $r['cash'], 'rate_bank' => $r['bank'], 'lyd' => round($usd * $r['cash'], 2),
            'lyd_cash' => round($usd * $r['cash'], 2), 'lyd_bank' => round($usd * $r['bank'], 2), 'net_per_kg' => $net, 'saved' => round($kg * $discount, 2)];
    }

    /** إنشاء أو تعديل شحنة */
    public const PLATFORM = 'شحن فقط';

    /** 📦 رسالة «وصل طلبك» الافتراضية (تتعدل من قوالب الرسائل: ship_arrived) */
    public const ARRIVED_MSG = "شركة SHOP AND GO للشراء و الشحن 🤍\n\n"
        . "اهلا عميلنا العزيز ({الاسم})\n"
        . "رقم الكود الخاص بك: ({كود_الزبون})\n\n"
        . "✅ تم وصول طلبك من {بلد_الشحن}\n"
        . "🛍 المنصة: {المنصة}\n"
        . "✈️ نوع الشحن: {نوع_الشحن}\n"
        . "🔢 رقم الشحنة: {رقم_الإيصال}\n"
        . "⚖️ الوزن: {الوزن} كغ\n"
        . "🚚 سعر الشحن: {سعر_الشحن}\n\n"
        . "📦 طلبك جاهز للاستلام\n"
        . "باقي التفاصيل في الرابط 👇\n"
        . "{رابط_الحساب}\n\n"
        . "🛵 تبي نوصلوهولك لباب بيتك؟ اطلب مندوب من هني 👇\n"
        . "{رابط_التوصيل}\n\n"
        . "📍 مقر الشركة: بنغازي - الفويهات - الرحبة بجانب النفق";

    /** نص رسالة «وصل طلبك» (للمعاينة قبل الإرسال) */
    public static function arrivedText(Order $o): string
    {
        $tpl = MessageService::template('ship_arrived');

        return \App\Services\ArrivalNotify::withIssues($o, static::unitize($o, MessageService::render($tpl->message ?? static::ARRIVED_MSG, static::vars($o))));
    }

    /** 🚢 الشحن البحري يُحسب بالـ CBM (متر مكعب) بدل الكيلو */
    public static function isSea(?Order $o): bool
    {
        return $o && str_contains((string) $o->package_type, 'بحري');
    }

    public static function unit(?Order $o): string
    {
        return static::isSea($o) ? 'CBM' : 'كغ';
    }

    /** يبدّل «كغ/الكيلو/الوزن» في نص الرسالة لـ CBM/الحجم لو الشحنة بحري */
    public static function unitize(?Order $o, string $text): string
    {
        return static::isSea($o)
            ? str_replace(['⚖️ الوزن', 'الوزن', ' كغ', 'كغ', 'سعر الكيلو', 'الكيلو'], ['📦 الحجم', 'الحجم', ' CBM', 'CBM', 'سعر الـCBM', 'الـCBM'], $text)
            : $text;
    }

    /** ✅ يبعت «وصل طلبك» + PDF الشحنة — ينفع يتعاود أكثر من مرة · $custom = النص بعد ما عدلوه في المعاينة */
    public static function sendArrived(Order $o, ?string $custom = null): bool
    {
        $c = $o->customer;
        if (!$c || !$c->phone_1) {
            return false;
        }
        $tpl = MessageService::template('ship_arrived');
        if ($tpl && !($tpl->enabled ?? true)) {
            return false;
        }
        $text = trim((string) $custom) !== '' ? trim((string) $custom) : static::arrivedText($o);
        try {
            $file = \App\Support\OrderPdf::make($o);
            $ok = app(\App\Services\WhatsAppService::class)->sendDocument(MessageService::normalize((string) $c->phone_1), $file, 'شحنة-' . $o->code . '.pdf', $text);
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }
        if (!$ok) { // الملف ما مشاش: نبعتو الرسالة بروحها
            $ok = app(\App\Services\WhatsAppService::class)->sendMessage(MessageService::normalize((string) $c->phone_1), $text);
        }
        if ($ok) {
            try {
                \App\Services\CustomerNotify::inbox($c, $text, $o, 'ship_arrived');
                \App\Models\WhatsappOutbox::create(['type' => 'ship_arrived', 'phone' => $c->phone_1, 'body' => $text . ' [PDF]', 'status' => 'sent',
                    'customer_id' => $c->id, 'order_id' => $o->id, 'user_id' => auth('web')->id()]);
            } catch (\Throwable $e) {
            }
        }

        return (bool) $ok;
    }

    /** الشحنات تحتاج منصة (العمود إجباري)، فنستخدم منصة داخلية مخفية عن القوائم */
    public static function platformId(): int
    {
        $p = \App\Models\Platform::where('name', static::PLATFORM)->first();
        if (!$p) {
            $p = new \App\Models\Platform;
            $p->name = static::PLATFORM;
            $p->save();
        }

        return (int) $p->id;
    }

    public static function save(?Order $o, Customer $c, array $d): Order
    {
        $origins = AppSettings::origins();
        if (!isset($origins[$d['origin']])) {
            throw new RuntimeException('اختر بلد الشحن');
        }
        $new = !$o;
        $o = $o ?: new Order;
        if ($new) {
            $o->code = OrderNumber::nextShipping();
            $o->kind = 'shipping';
            $o->status = 'unpaid';
            $o->currency = 'dinar';
            $o->currency_sign = 'د.ل';
            $o->total_price = 0;
            $o->paid_price = 0;
            $o->qty = 0;
            $o->created_id = auth('web')->id();
            $o->platform_id = static::platformId();
        }
        $o->customer_id = $c->id;
        $o->ship_origin = $d['origin'];
        $o->tracking_no = $d['tracking_no'] ?? null;
        $o->bags_num = (int) ($d['bags'] ?? 1) ?: 1;
        $o->package_type = $d['package_type'] ?? null;
        $o->kilo = isset($d['kilo']) && $d['kilo'] !== '' ? (float) $d['kilo'] : null;
        $o->note = $d['note'] ?? $o->note;
        $o->usd_per_kg = isset($d['per_kg']) && $d['per_kg'] !== '' ? (float) $d['per_kg'] : static::perKg($c, $d['origin'], $d['package_type'] ?? null);
        $o->usd_discount = (float) ($d['discount'] ?? 0);
        $calc = static::calc($d['origin'], (float) $o->kilo, (float) $o->usd_per_kg, (float) $o->usd_discount);
        $o->usd_amount = $calc['usd'];
        $o->usd_rate = $calc['rate'];
        $o->ship_price = $calc['lyd'];
        $hasDual = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'ship_rate_cash');
        if ($hasDual) { // 💱 السعرين المحفوظين: لو الشحنة موجودة من قبل نخلي سعرها القديم (تغيير الصرف ما يغيرش شحنات مسجلة)
            $o->ship_rate_cash = $o->ship_rate_cash ?: $calc['rate_cash'];
            $o->ship_rate_bank = $o->ship_rate_bank ?: $calc['rate_bank'];
        }
        // ✍️ سعر الشحن النهائي اللي كتبه الموظف بيده (يغلب الحساب) — بالعملة اللي اختارها ($ ولا د.ل)
        if (isset($d['ship_amt']) && $d['ship_amt'] !== '' && $d['ship_amt'] !== null) {
            if (($d['ship_cur'] ?? 'usd') === 'lyd') {
                $d['ship_lyd'] = $d['ship_amt'];
                $o->usd_amount = $calc['rate'] > 0 ? round((float) $d['ship_amt'] / (float) $calc['rate'], 2) : $o->usd_amount;
            } else {
                $d['ship_usd'] = $d['ship_amt'];
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'ship_cur')) {
            $o->ship_cur = ($d['ship_cur'] ?? 'usd') === 'lyd' ? 'lyd' : 'usd';
        }
        if (isset($d['ship_usd']) && $d['ship_usd'] !== '' && $d['ship_usd'] !== null) {
            $o->usd_amount = round((float) $d['ship_usd'], 2);
            $o->ship_price = round($o->usd_amount * (float) $calc['rate'], 2);
        }
        if (isset($d['ship_lyd']) && $d['ship_lyd'] !== '' && $d['ship_lyd'] !== null) {
            $o->ship_price = round((float) $d['ship_lyd'], 2);
        }
        // 💵/🏦 الشحنة بالدولار: نحسب السعر بسعر الكاش ولا المصرف (اللي اختاره الموظف، والافتراضي كاش)
        if ($hasDual && static::isDual($o)) {
            $pay = ($d['ship_pay'] ?? $o->ship_pay ?: 'cash') === 'bank' ? 'bank' : 'cash';
            if ($new || !$o->delivered) {
                static::applyPay($o, $pay);
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'ship_platform') && array_key_exists('ship_platform', $d)) {
            $o->ship_platform = $d['ship_platform'] ? mb_substr(trim((string) $d['ship_platform']), 0, 80) : null;
        }
        $o->trip_details = null;
        if (!empty($d['stage'])) {
            static::applyStage($o, $d['stage'], $d['storage'] ?? null);
        } elseif (isset($d['storage'])) {
            $o->storage_num = $d['storage'];
        }
        $o->refreshPaymentStatus();
        $o->save();
        Log::create(['user_id' => auth('web')->id(), 'type' => 'order', 'title' => ($new ? ' شحنة جديدة (' : ' تعديل شحنة (') . $o->code . ')',
            'log' => ' ' . $c->name . ' · ' . ($origins[$d['origin']]['name'] ?? '') . ' · ' . $o->kilo . ' كغ × ' . ($o->usd_per_kg - $o->usd_discount) . '$ = ' . $o->usd_amount . '$']);

        return $o;
    }

    /** تغيير المرحلة (الوصول = يدخل الرف ويصبح جاهزاً للتسليم) */
    public static function applyStage(Order $o, string $stage, ?string $storage = null): void
    {
        if (!in_array($stage, ['expected', 'warehouse', 'transit', 'arrived'], true)) {
            return;
        }
        $o->ship_stage = $stage;
        if ($stage === 'arrived') {
            if (!$o->arrived) {
                $o->arrived = true;
                $o->arrived_at = now()->toDateString();
                $o->received_at = now();
                $o->received_by = auth('web')->id();
                $o->received_qty = $o->qty;
            }
        } elseif (!$o->delivered) {
            $o->arrived = false;
        }
        if ($storage !== null && $storage !== '') {
            $o->storage_num = $storage;
        }
    }

    /** نص عنوان المخزن للعميل */
    /** شرائح الوزن: سطر لكل شريحة «من_كم_كيلو:السعر» مثلاً 5:10 ثم 20:9 */
    public static function tiers(array $o): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,،]+/u', (string) ($o['tiers'] ?? '')) as $line) {
            if (preg_match('/^\s*([\d.]+)\s*[:=]\s*([\d.]+)\s*$/', $line, $m)) {
                $out[(string) (float) $m[1]] = (float) $m[2];
            }
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }

    public static function tierPrice(array $o, float $kg, float $perKg): float
    {
        $best = $perKg;
        foreach (static::tiers($o) as $from => $price) {
            if ($kg >= (float) $from) {
                $best = min($best, $price);
            }
        }

        return $best;
    }

    /** صورة ثابتة مكتوب عليها كود العميل — ترجع مسار الصورة للواتساب أو null */
    public static function cardImage(Customer $c): ?string
    {
        $cfg = (array) AppSettings::get('ship_card', []);
        if (empty($cfg['bg']) || !\Illuminate\Support\Facades\Storage::disk('local')->exists($cfg['bg']) || !function_exists('imagettftext')) {
            return null;
        }
        $bytes = \Illuminate\Support\Facades\Storage::disk('local')->get($cfg['bg']);
        $im = @imagecreatefromstring($bytes);
        if (!$im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $text = 'SG' . $c->code;
        $size = max(8, (float) ($cfg['size'] ?? 6) / 100 * $w);
        $font = resource_path('fonts/somar/Somar-Bold.ttf');
        $hex = ltrim((string) ($cfg['color'] ?? '#ffffff'), '#');
        $col = imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        $x = (int) ((float) ($cfg['x'] ?? 50) / 100 * $w - $tw / 2);
        $y = (int) ((float) ($cfg['y'] ?? 80) / 100 * $h + $th / 2);
        imagettftext($im, $size, 0, $x, $y, $col, $font, $text);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);
        $path = 'whatsapp/reply-images/' . (string) \Illuminate\Support\Str::uuid() . '.png';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $png);

        return $path;
    }

    public static function addressFor(array $origin, Customer $c, ?array $addr = null): string
    {
        if ($addr) {
            $origin['address'] = $addr['address'] ?? '';
        }
        $first = trim(explode(' ', trim((string) $c->name))[0] ?? '');

        return strtr((string) ($origin['address'] ?? ''), ['{الكود}' => 'SG' . $c->code, '{الاسم}' => $first]);
    }

    public static function vars(Order $o): array
    {
        $og = AppSettings::origins()[$o->ship_origin] ?? [];

        return MessageService::orderVars($o, [
            '{بلد_الشحن}' => trim(($og['name'] ?? '') . ' ' . ($og['flag'] ?? '')), '{الشحن_دولار}' => TreasuryService::fmt($o->usd_amount),
            '{المنصة}' => $o->ship_platform ?? '—', '{نوع_الشحن}' => $o->package_type ?: 'جوي',
            // 💱 بالدولار: نعرض السعرين (كاش / مصرف) · لو كتبه الموظف بالدينار نعرضو دينار بس
            '{سعر_الشحن}' => static::priceText($o),
            '{سعر_الشحن_كاش}' => static::isDual($o) ? TreasuryService::fmt(static::dualPrices($o)['cash']) . ' د.ل' : TreasuryService::fmt($o->ship_price) . ' د.ل',
            '{سعر_الشحن_مصرف}' => static::isDual($o) ? TreasuryService::fmt(static::dualPrices($o)['bank']) . ' د.ل' : TreasuryService::fmt($o->ship_price) . ' د.ل',
            '{الحالة}' => static::STAGES[static::stageOf($o)][0] ?? '',
        ]);
    }

    /** نص سعر الشحن للرسائل: السعرين لو بالدولار، وإلا الدينار */
    public static function priceText(Order $o): string
    {
        if (!static::isDual($o)) { // شحنة بسعر واحد: نزيد قيمتها بالدولار لو موجودة
            return TreasuryService::fmt($o->ship_price) . ' د.ل' . ((float) $o->usd_amount > 0 ? ' (' . TreasuryService::fmt($o->usd_amount) . ' $)' : '');
        }
        $p = static::dualPrices($o);

        return TreasuryService::fmt($o->usd_amount) . ' $' . "\n"
            . '   💵 كاش: ' . TreasuryService::fmt($p['cash']) . " د.ل\n"
            . '   🏦 مصرف: ' . TreasuryService::fmt($p['bank']) . ' د.ل';
    }
}
