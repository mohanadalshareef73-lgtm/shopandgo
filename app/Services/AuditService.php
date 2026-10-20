<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * سجل التعديلات الأوتوماتيكي: أي إضافة/تعديل/مسح على الجداول المهمة يُسجَّل
 * مع اسم الذي داره، والقيمة القديمة والجديدة لكل خانة.
 */
class AuditService
{
    /** الجداول الذي نراقبها => اسمها بالعربي */
    public const MODELS = [
        \App\Models\Order::class => 'إيصال',
        \App\Models\OrderItem::class => 'منتج في إيصال',
        \App\Models\Customer::class => 'عميل',
        \App\Models\PaidHistory::class => 'دفعة',
        \App\Models\TripPurchase::class => 'رحلة شراء',
        \App\Models\Purchase::class => 'مصروف',
        \App\Models\ShippingCharges::class => 'مصروف شحن',
        \App\Models\TreasuryHistory::class => 'حركة خزينة',
        \App\Models\Dept::class => 'دين علينا',
        \App\Models\DeptHistory::class => 'تسديد دين',
        \App\Models\User::class => 'موظف',
        \App\Models\PaymentMethod::class => 'خزينة',
        \App\Models\Delivery::class => 'توصيل',
        \App\Models\CourierSettlement::class => 'إقفال مندوب',
        \App\Models\SheinCard::class => 'بطاقة SHEIN',
        \App\Models\SheinCardUse::class => 'استخدام بطاقة',
        \App\Models\OrderReturn::class => 'مرتجع',
        \App\Models\OrderNotes::class => 'ملاحظة',
        \App\Models\Setting::class => 'الإعدادات',
        \App\Models\Status::class => 'حالة رحلة',
        \App\Models\Platform::class => 'منصة',
        \App\Models\CurrencyExchange::class => 'سعر صرف',
        \App\Models\Shift::class => 'وردية',
    ];

    /** خانات ما نسجلوهاش */
    public const SKIP = ['updated_at', 'created_at', 'last_seen', 'last_seen_at', 'remember_token', 'email_verified_at'];

    /** خانات حساسة: تسجيل إنها تبدلت فقط، بدون القيمة */
    public const SECRET = ['password', 'pin'];

    /** خانات نُخفي أغلبها (نُبقي آخر 4 فقط) */
    public const MASK = ['card_no'];

    /** خانات تقنية ما نعرضوهاش في الإضافة/المسح */
    public const HIDE = ['created_id', 'procedure_id', 'order_id', 'customer_id', 'user_id', 'trip_id', 'treasury_id', 'shift_id', 'settlement_id', 'delivery_id', 'card_id', 'trip_purchase_id', 'batch', 'currency_sign', 'payment_currency'];

    /** ترجمة بعض القيم */
    public const VALUES = [
        'paid_full' => 'مدفوع كامل', 'paid' => 'مدفوع البضاعة', 'unpaid' => 'غير مدفوع', 'canceld' => 'ملغي', 'dinar' => 'دينار', 'dollar' => 'دولار', 'euro' => 'يورو',
        'pull' => 'سحب', 'push' => 'إيداع', 'deposit' => 'إيداع', 'withdraw' => 'سحب', 'return' => 'مرتجع', 'exchange' => 'استبدال',
        'deduct' => 'ينقص من الذي عليه', 'cash' => 'رجعناله أموال', 'credit' => 'رصيد', 'none' => 'بدون أموال', 'shop' => 'للمحل', 'supplier' => 'للمورد', 'damaged' => 'تالفة',
        'new' => 'جديدة', 'partial' => 'مستخدمة جزئيا', 'used' => 'خلصت', 'expired' => 'انتهت', 'invalid' => 'خطأ',
    ];

    public static function val($v): string
    {
        return self::VALUES[(string) $v] ?? (string) $v;
    }

    public const FIELDS = [
        'code' => 'الرقم/الكود', 'name' => 'الاسم', 'phone_1' => 'الهاتف', 'phone_2' => 'هاتف 2', 'address' => 'العنوان',
        'total_price' => 'قيمة البضاعة', 'paid_price' => 'المدفوع', 'ship_price' => 'الشحن', 'discount' => 'الخصم', 'discount_reason' => 'سبب الخصم',
        'qty' => 'الكمية', 'received_qty' => 'القطع الواصلة', 'kilo' => 'الوزن', 'bags_num' => 'الطرود', 'status' => 'الحالة', 'trip_status' => 'حالة الرحلة',
        'trip_details' => 'الرحلة', 'storage_num' => 'الرف', 'note' => 'ملاحظة', 'arrived' => 'وصلت', 'delivered' => 'تسلّمت', 'delivered_at' => 'تاريخ التسليم',
        'customer_id' => 'العميل', 'payment_id' => 'الخزينة', 'cost' => 'المبلغ', 'currency' => 'العملة', 'rate' => 'سعر الصرف', 'date' => 'التاريخ',
        'credit' => 'رصيد العميل', 'points' => 'النقاط', 'price' => 'السعر', 'quantity' => 'الكمية', 'amount' => 'المبلغ', 'balance' => 'الرصيد',
        'face_value' => 'قيمة البطاقة', 'card_no' => 'رقم البطاقة', 'type' => 'النوع', 'type_id' => 'نوع الحساب', 'email' => 'الإيميل', 'password' => 'كلمة المرور', 'pin' => 'الرمز',
        'fee' => 'أجرة التوصيل', 'fee_payer' => 'الأجرة على', 'courier_id' => 'المندوب', 'collected_cash' => 'قبض نقدي', 'collected_bank' => 'قبض مصرف', 'general' => 'الإعدادات',
        'price_before' => 'الرصيد قبل', 'price_after' => 'الرصيد بعد', 'method' => 'المبلغ', 'items' => 'ماذا رجع', 'extra' => 'زيادة', 'new_items' => 'بدالها',
        'cost_dinar' => 'التكلفة بالدينار', 'cost_currency' => 'عملة التكلفة', 'returned_value' => 'قيمة المرتجع', 'refunded' => 'رجع من الخزينة', 'goods' => 'البضاعة', 'reason' => 'السبب',
        'trip_code' => 'رقم الرحلة', 'cards_amount' => 'دفع بالبطاقات', 'supplier' => 'المورد', 'expires_at' => 'تنتهي', 'purchased_at' => 'تاريخ الشراء', 'received_at' => 'تاريخ الوصول',
        'missing_action' => 'الناقص', 'delivery_discount' => 'خصم التسليم', 'points_used' => 'نقاط استعملها', 'points_earned' => 'نقاط خذاها', 'storage' => 'الرف', 'desc_id' => 'البند',
        'missing_qty' => 'قطع ناقصة', 'missing_value' => 'قيمة الناقص', 'platform_id' => 'المنصة', 'order_type' => 'نوع الطرد', 'paid_price_dinar' => 'المدفوع بالدينار',
    ];

    protected static ?bool $ready = null;

    public static function register(): void
    {
        foreach (array_keys(self::MODELS) as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $class::created(fn (Model $m) => static::log('created', $m));
            $class::updated(fn (Model $m) => static::log('updated', $m));
            $class::deleted(fn (Model $m) => static::log('deleted', $m));
        }
    }

    protected static function ready(): bool
    {
        if (static::$ready === null) {
            try {
                static::$ready = Schema::hasTable('audit_logs');
            } catch (\Throwable $e) {
                static::$ready = false;
            }
        }

        return static::$ready;
    }

    public static function label(Model $m): ?string
    {
        foreach (['code', 'trip_code', 'name', 'card_no'] as $f) {
            $v = $m->getAttribute($f);
            if ($v) {
                return $f === 'card_no' ? '••••' . substr((string) $v, -4) : (string) $v;
            }
        }
        if ($m->getAttribute('order_id') && ($o = \App\Models\Order::find($m->getAttribute('order_id')))) {
            return $o->code;
        }

        return null;
    }

    public static function log(string $action, Model $m): void
    {
        if (!static::ready() || $m instanceof AuditLog) {
            return;
        }
        try {
            $changes = [];
            if ($action === 'updated') {
                foreach ($m->getChanges() as $k => $new) {
                    if (in_array($k, self::SKIP, true)) {
                        continue;
                    }
                    $old = $m->getRawOriginal($k);
                    if ((string) $old === (string) $new || (is_numeric($old) && is_numeric($new) && abs((float) $old - (float) $new) < 0.0001)) {
                        continue;
                    }
                    $changes[$k] = in_array($k, self::SECRET, true) ? ['•••', '•••'] : (in_array($k, self::MASK, true) ? [static::mask($old), static::mask($new)] : [static::short($old), static::short($new)]);
                }
                if (!$changes) {
                    return;
                }
            } else {
                foreach ($m->getAttributes() as $k => $v) {
                    if (in_array($k, self::SKIP, true) || $v === null || $v === '' || $k === 'id') {
                        continue;
                    }
                    $changes[$k] = in_array($k, self::SECRET, true) ? '•••' : (in_array($k, self::MASK, true) ? static::mask($v) : static::short($v));
                }
            }
            [$uid, $utype, $uname] = static::who();
            $req = app()->runningInConsole() ? null : request();
            AuditLog::create([
                'user_id' => $uid, 'user_type' => $utype, 'user_name' => $uname,
                'action' => $action, 'model' => class_basename($m), 'model_id' => $m->getKey(), 'label' => mb_substr((string) static::label($m), 0, 150) ?: null,
                'changes' => $changes, 'ip' => $req ? $req->ip() : null, 'url' => $req ? mb_substr($req->method() . ' ' . $req->path(), 0, 255) : 'console',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // السجل ما يوقفش الشغل أبدا
        }
    }

    protected static function mask($v): string
    {
        $v = (string) $v;

        return $v === '' ? '' : '••••' . substr($v, -4);
    }

    protected static function short($v)
    {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $v = (string) $v;

        return mb_strlen($v) > 300 ? mb_substr($v, 0, 300) . '…' : $v;
    }

    protected static function who(): array
    {
        try {
            if ($u = auth('web')->user()) {
                return [$u->id, $u->type_id == 4 ? 'courier' : 'user', $u->name];
            }
            if (config('auth.guards.customer') && ($c = auth('customer')->user())) {
                return [$c->id, 'customer', $c->name . ' (عميل)'];
            }
        } catch (\Throwable $e) {
        }

        return [null, 'system', app()->runningInConsole() ? 'النظام (أوتوماتيك)' : 'غير معروف'];
    }

    public static function modelLabel(string $base): string
    {
        foreach (self::MODELS as $class => $label) {
            if (class_basename($class) === $base) {
                return $label;
            }
        }

        return $base;
    }

    public static function fieldLabel(string $f): string
    {
        return self::FIELDS[$f] ?? $f;
    }

    /** رابط للسجل إذا لديه صفحة */
    public static function link(AuditLog $a): ?string
    {
        try {
            return match ($a->model) {
                'Order' => $a->action !== 'deleted' ? route('sg.orders.show', $a->model_id) : null,
                'Customer' => route('sg.clients', ['id' => $a->model_id]),
                'TripPurchase' => $a->action !== 'deleted' ? route('finance.trips.show', $a->model_id) : null,
                'SheinCard' => route('sg.shein', ['id' => $a->model_id, 'tab' => 'all']),
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }
}
