<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * أزرار الرد تحت رسائل الواتساب (ID الزر: sg:الإجراء:الرقم).
 * العميل يضغط زر → المنظومة ترد عليه وتبلّغ الإدارة لو لازم.
 */
class SgButtons
{
    /** الأزرار حسب نوع الرسالة: [id-prefix, العنوان (أقل من 20 حرف)] */
    public const MAP = [
        'arrived' => [['arr_deliver', '🛵 وصّلهالي'], ['arr_pickup', '🏪 نجي نستلم']],
        'shelf_reminder' => [['arr_deliver', '🛵 وصّلهالي'], ['arr_pickup', '🏪 نجي نستلم']],
        'soldout_notice' => [['so_cancel', '❌ إلغاء وخصم'], ['so_replace', '🔄 نبي بديل']],
        'campaign' => [['camp_ok', '👍 وصلتني'], ['camp_stop', '🚫 توقف']],
        'debt_reminder' => [['debt_cash', '💵 ندفع كاش'], ['debt_bank', '🏦 ندفع تحويل']],
        'quote_link' => [['q_courier', '🛵 نبي مندوب'], ['q_staff', '💬 كلّم موظف']],
    ];

    public static function enabled(): bool
    {
        return (bool) AppSettings::get('wa_buttons', true);
    }

    /** أزرار رسالة معينة جاهزة للإرسال (أو [] لو ما فيش) */
    public static function for(string $type, $ref): array
    {
        if (!static::enabled() || !isset(self::MAP[$type]) || !$ref) {
            return [];
        }

        return array_map(fn ($b) => ['id' => 'sg:' . $b[0] . ':' . $ref, 'title' => $b[1]], self::MAP[$type]);
    }

    /** اللي ما يبيش رسايل الحملات */
    public static function optedOut(string $phone): bool
    {
        $tail = substr(preg_replace('/\D/', '', $phone), -9);

        return $tail !== '' && in_array($tail, (array) AppSettings::get('wa_optout', []), true);
    }

    /** يتنادى من الـ webhook لما العميل يضغط زر sg:… — يرجع true لو اتعامل معاه */
    public static function handle(array $message, string $session, ?string $phone): bool
    {
        $id = (string) data_get($message, 'interactive.button_reply.id');
        if (!str_starts_with($id, 'sg:')) {
            return false;
        }
        $eventId = (string) ($message['id'] ?? '');
        if ($eventId !== '' && !Cache::add('sgbtn:' . md5($eventId), 1, now()->addDay())) {
            return true; // مكرر
        }
        [, $action, $ref] = array_pad(explode(':', $id, 3), 3, '');
        $ref = (int) $ref;
        $to = WhatsAppMessageContext::recipient((string) ($message['from'] ?? ''));
        if (!$to) {
            return true;
        }
        $tail = substr(preg_replace('/\D/', '', (string) ($phone ?: $to)), -9);
        $owns = fn (?Customer $c) => $c && $tail !== '' && (str_ends_with(preg_replace('/\D/', '', (string) $c->phone_1), $tail) || str_ends_with(preg_replace('/\D/', '', (string) $c->phone_2), $tail));
        $site = rtrim((string) config('app.url'), '/');
        $reply = null;
        $reply2 = null;
        $alert = null;

        if (in_array($action, ['arr_deliver', 'arr_pickup', 'so_cancel', 'so_replace'], true)) {
            $o = Order::with('customer')->find($ref);
            $c = optional($o)->customer;
            $who = $c ? $c->name . ' (' . $c->code . ')' : 'عميل';
            $known = $owns($c);
            if ($action === 'arr_deliver') {
                $reply = "تمام 🛵 باش نوصلوهالك، حدد عنوانك وطريقة الدفع من هنا 👇\n" . $site . '/customers/dashboard?tab=delivery' . "\nأو رد علينا بعنوانك وموظفنا يكمّل معاك.";
                $alert = ['request', '🛵 ' . $who . ' يبي توصيل', 'ضغط «وصّلهالي» على ' . optional($o)->code, $o ? route('sg.orders.show', $o) : null];
            } elseif ($action === 'arr_pickup') {
                $hours = (string) AppSettings::get('shop_hours', '');
                $reply = "نستنوك 🌷" . ($hours ? "\n🕐 أوقات الدوام: " . $hours : '') . (AppSettings::get('shop_address') ? "\n📍 " . AppSettings::get('shop_address') : '');
            } elseif ($action === 'so_cancel') {
                $reply = "تمام ✅ وصلنا قرارك: نلغو القطع اللي نفدت ونخصمو قيمتها من طلبك. موظفنا يأكدلك قريب.";
                $alert = ['order_issue', '❌ ' . $who . ' اختار إلغاء النواقص', 'طلب ' . optional($o)->code . ' — سجّل القرار من صفحة منتجات نفدت', route('sg.soldouts')];
            } else {
                $reply = "تمام 🔄 ابعتلنا هنا رابط القطعة البديلة اللي تبيها، وموظفنا يكمّل معاك.";
                $alert = ['order_issue', '🔄 ' . $who . ' يبي بديل للنواقص', 'طلب ' . optional($o)->code . ' — يستنى يبعت رابط البديل', route('sg.soldouts')];
            }
            if (!$known && $alert) {
                $alert[2] .= ' · ⚠ الرقم اللي ضغط مش رقم العميل المسجل، تأكد';
            }
        } elseif (in_array($action, ['camp_ok', 'camp_stop'], true)) {
            if ($action === 'camp_stop') {
                $list = (array) AppSettings::get('wa_optout', []);
                if ($tail !== '' && !in_array($tail, $list, true)) {
                    $list[] = $tail;
                    AppSettings::put(['wa_optout' => array_values($list)]);
                }
                $reply = "تمام 🙏 ما عادش نبعتولك رسايل عروض. رسايل طلبياتك تجيك عادي.";
            } else {
                $reply = "يسعدنا 💙 أي وقت تحتاجنا إحنا هني.";
            }
        } elseif (in_array($action, ['debt_cash', 'debt_bank'], true)) {
            $c = Customer::withoutGlobalScopes()->find($ref);
            $who = $c ? $c->name . ' (' . $c->code . ')' : 'عميل';
            if ($action === 'debt_cash') {
                $reply = "تمام 💵 تقدر تدفع في المحل وقت ما يناسبك" . (AppSettings::get('shop_hours') ? "\n🕐 " . AppSettings::get('shop_hours') : '') . "\nأو اطلب توصيل وادفع للمندوب.";
            } else {
                $reply = "تمام 🏦 موظفنا يبعتلك بيانات الحساب، وبعد التحويل ابعتلنا صورة الإيصال هنا.";
                $alert = ['debt', '🏦 ' . $who . ' يبي يدفع دينه بتحويل', 'ابعتله بيانات الحساب', $c ? route('sg.clients', ['id' => $c->id]) : null];
            }
        } elseif (in_array($action, ['c_ok', 'c_edit', 'c_cour', 'c_now', 'c_tr'], true)) {
            // 🛒 أزرار رسالة سعر السلة (PDF)
            $cart = \App\Models\CartQuote::find($ref);
            if (!$cart) {
                return true;
            }
            $total = \App\Http\Controllers\Portal\CartsController::cartTotal($cart);
            [, $pct] = \App\Http\Controllers\Portal\CartsController::deposit();
            $dep = number_format(ceil($total * $pct / 100));
            $who = optional($cart->customer)->name ?: ($phone ?: $to);
            $buttons = [];
            if ($action === 'c_ok') {
                $reply = (string) (AppSettings::get('wa_rp_confirm') ?: "تمام! 🤍 اختار كيف تبي تكمل حجزك:\n\n⚡ *حجز فوري عبر منظومتنا*: تأكد وتدفع من رابط سلتك\n🛵 *مندوب*: يجيك يستلم العربون\n🏦 *تحويل مصرفي*: تحوّل العربون وترفع الإيصال");
                $buttons = [['id' => 'sg:c_now:' . $cart->id, 'title' => '⚡ حجز عبر منظومتنا'], ['id' => 'sg:c_cour:' . $cart->id, 'title' => '🛵 مندوب للعربون'], ['id' => 'sg:c_tr:' . $cart->id, 'title' => '🏦 تحويل مصرفي']];
                if ($cart->portal_status === null) {
                    $cart->portal_status = 'draft';
                    $cart->save();
                }
            } elseif ($action === 'c_edit') {
                // ⚡ رابط سلته هو: يفتحها طول ويعدّل الكميات ويحجز (من غير حساب)
                $cLink = \App\Http\Controllers\Portal\CartsController::publicUrl($cart->id);
                $reply = (string) (AppSettings::get('wa_rp_edit') ?: "📸 لو فيه قطع مكررة في سلتك (نفس القطعة أكثر من وحدة)، ابعتلنا صورتها واكتب كم تبي منها، عشان نحسبوها صح 👌\n\n⚡ *تقدر تحجز وتعدّل كمياتك فوري* من هنا 👇\n{رابط}");
                $reply = str_contains($reply, '{رابط}') ? str_replace('{رابط}', $cLink, $reply) : rtrim($reply) . "\n\n⚡ *تقدر تحجز وتعدّل كمياتك فوري* من هنا 👇\n" . $cLink;
                $alert = ['request', '✏️ ' . $who . ' يبي يعدل كميات سلة #' . $cart->id, 'بيبعت صور القطع المكررة', route('sg.carts')];
            } elseif ($action === 'c_cour') {
                $reply = str_replace(['{العربون}', '{رابط}'], [$dep, \App\Http\Controllers\Portal\CartsController::courierUrl($cart->id)],
                    (string) (AppSettings::get('wa_rp_courier') ?: "تبي مندوب يجيك ويستلم منك العربون؟ 🛵\n💵 العربون: *{العربون} د.ل* (" . (int) $pct . "% من قيمة السلة)\n\nاضغط الرابط وعبّي بياناتك (العنوان ورقمك)، وطلبك يمشي للمندوب طول 👇\n{رابط}\n\nSHOP AND GO 🤍💙"));
            } elseif ($action === 'c_now') {
                $reply = "⚡ حجز فوري عبر منظومتنا!\nافتح سلتك من الرابط، أكد الكميات واختار طريقة الدفع 👇\n" . \App\Http\Controllers\Portal\CartsController::publicUrl($cart->id);
            } else {
                $acc = (string) AppSettings::get('transfer_account', '');
                // كل البيانات في رسالة وحدة · الإيصال يبعته صورة هنا والفريق يرد عليه
                $reply = "🏦 *تحويل مصرفي*\n\n💵 العربون: *{$dep} د.ل* (" . (int) $pct . "% من قيمة السلة " . number_format($total) . " د.ل)\n\n"
                    . (AppSettings::get('transfer_holder') ? '👤 باسم: ' . AppSettings::get('transfer_holder') . "\n" : '')
                    . (AppSettings::get('transfer_bank_name') ? '🏛 المصرف: ' . AppSettings::get('transfer_bank_name') . "\n" : '')
                    . ($acc !== '' ? "🔢 رقم الحساب: *{$acc}*\n" : '')
                    . "\n📸 بعد التحويل، ابعتلنا صورة الإيصال هنا، وسيتم الرد عليك من قبل فريقنا المختص 🤍\n\nSHOP AND GO 🤍💙";
            }
            // ⚡ رابط سلته تحت كل رد (لو مش موجود فيه)
            $reply .= \App\Http\Controllers\Portal\CartsController::linkLine((int) $cart->id, (string) $reply);
            if ($buttons) {
                try {
                    $ok = app(WhatsAppService::class)->sendMessage($to, $reply, $session, $buttons);
                    if ($ok) {
                        $reply = null;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } elseif (in_array($action, ['q_courier', 'q_staff'], true)) {
            $cart = \App\Models\CartQuote::find($ref);
            if ($action === 'q_courier') {
                $reply = "تمام 🛵 افتح سلتك من الرابط، أكد الطلب واختار «كاش عند المندوب» 👇\n" . ($cart ? \App\Http\Controllers\Portal\CartsController::publicUrl($cart->id) : $site . '/customers/carts');
            } else {
                $reply = "تمام 💬 موظفنا بيكلمك هني في أقرب وقت.";
                $alert = ['request', '💬 عميل يبي يكلم موظف', 'بخصوص سلة #' . $ref . ' · الرقم ' . ($phone ?: $to), $cart ? route('sg.carts') : null];
            }
        } else {
            return false;
        }
        if ($alert) {
            try {
                AlertService::push($alert[0], $alert[1], $alert[2], $alert[3], 'warn', true);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($reply) {
            try {
                app(WhatsAppService::class)->sendMessage($to, $reply, $session);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if (!empty($reply2)) {
            try {
                app(WhatsAppService::class)->sendMessage($to, $reply2, $session);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return true;
    }
}
