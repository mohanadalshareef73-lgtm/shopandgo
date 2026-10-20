<?php

namespace App\Services;

use App\Models\Trade\Invoice;
use App\Models\Trade\Move;
use App\Models\Trade\Partner;
use App\Models\Trade\Share;
use App\Models\Trade\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 🌍 التجارة الخارجية — كل الفلوس بالدينار:
 *  الخزينة (كاش/مصرف التجارة) = الفلوس الموجودة فعلاً · رصيد الشريك = حقه عند التجارة.
 *  إيداع: خزينة + · رصيد +   |  سحب: خزينة − · رصيد −
 *  مساهمة في فاتورة: رصيد − (فلوسه داخلة في البضاعة)  |  دفع الفاتورة: خزينة −
 *  سداد العميل: خزينة +  |  إقفال الفاتورة: كل شريك يرجعله اللي دفعه + نصيبه من الربح (على قد اللي دفعه)
 */
class TradeService
{
    public static function company(): Partner
    {
        return Partner::where('is_company', true)->first() ?: Partner::create(['name' => 'Shop & Go', 'is_company' => true]);
    }

    /** حركة وحدة: تعدّل الخزينة و/أو رصيد الشريك وتتسجل */
    public static function move(string $type, ?Wallet $w, float $walletChange, ?Partner $p, float $partnerChange, ?Invoice $inv = null, ?string $note = null): Move
    {
        $walletChange = round($walletChange, 2);
        $partnerChange = round($partnerChange, 2);
        if ($w) {
            $w = Wallet::lockForUpdate()->find($w->id);
            if ($walletChange < 0 && (float) $w->balance + $walletChange < -0.009) {
                throw new RuntimeException('رصيد ' . $w->name . ' ما يكفيش (فيه ' . TreasuryService::fmt($w->balance) . ' د.ل)');
            }
            $w->balance = round((float) $w->balance + $walletChange, 2);
            $w->save();
        }
        if ($p) {
            $p = Partner::lockForUpdate()->find($p->id);
            $p->balance = round((float) $p->balance + $partnerChange, 2);
            $p->save();
        }

        return Move::create([
            'wallet_id' => optional($w)->id, 'partner_id' => optional($p)->id, 'invoice_id' => optional($inv)->id, 'type' => $type,
            'wallet_change' => $w ? $walletChange : 0, 'partner_change' => $p ? $partnerChange : 0,
            'wallet_after' => optional($w)->balance, 'partner_after' => optional($p)->balance, 'note' => $note, 'created_id' => auth()->id(),
        ]);
    }

    public static function deposit(Partner $p, Wallet $w, float $amt, ?string $note = null): void
    {
        DB::transaction(fn () => static::move('deposit', $w, $amt, $p, $amt, null, $note));
    }

    public static function withdraw(Partner $p, Wallet $w, float $amt, ?string $note = null): void
    {
        DB::transaction(function () use ($p, $w, $amt, $note) {
            if ((float) $p->fresh()->balance + 0.009 < $amt) {
                throw new RuntimeException('رصيد ' . $p->name . ' (' . TreasuryService::fmt($p->balance) . ') أقل من ' . TreasuryService::fmt($amt));
            }
            static::move('withdraw', $w, -$amt, $p, -$amt, null, $note);
        });
    }

    /** 🏢 نزيدو رصيد Shop & Go: يطلع من خزينة الشركة الرئيسية (الطريقة اللي تختارها) ويدخل خزينة التجارة */
    public static function companyIn(Wallet $w, float $amt, int $paymentId, ?string $kind = null): void
    {
        DB::transaction(function () use ($w, $amt, $paymentId, $kind) {
            $mv = static::move('company_in', $w, $amt, static::company(), $amt, null, trim('من خزينة الشركة' . ($kind ? ' · ' . $kind : '')));
            TreasuryService::withdraw($amt, 'dinar', $paymentId, 'trade', $mv->id, 'تحويل للتجارة الخارجية' . ($kind ? ' · ' . $kind : ''));
        });
    }

    /** 🏢 إدراج ربح Shop & Go لمالية الشركة: يطلع من خزينة التجارة ويدخل خزينة الشركة */
    public static function toCompany(Wallet $w, float $amt, int $paymentId, ?string $note = null): void
    {
        DB::transaction(function () use ($w, $amt, $paymentId, $note) {
            $c = static::company();
            if ((float) $c->fresh()->balance + 0.009 < $amt) {
                throw new RuntimeException('رصيد Shop & Go في التجارة (' . TreasuryService::fmt($c->balance) . ') أقل من ' . TreasuryService::fmt($amt));
            }
            $mv = static::move('to_company', $w, -$amt, $c, -$amt, null, $note ?: 'إدراج لمالية الشركة');
            TreasuryService::deposit($amt, 'dinar', $paymentId, 'trade', $mv->id, 'أرباح التجارة الخارجية' . ($note ? ' · ' . $note : ''));
        });
    }

    /**
     * 🧾 فاتورة شراء جديدة.
     * $shares = [[partner_id, paid, source]] · source: balance (من رصيده) · cash (دفعها توا كاش للتجارة) · main:{payment_id} (للشركة: من خزينة الشركة)
     */
    public static function createInvoice(array $d, array $shares): Invoice
    {
        return DB::transaction(function () use ($d, $shares) {
            $w = Wallet::findOrFail($d['wallet_id']);
            $inv = Invoice::create([
                'code' => 'TR-' . now()->format('ymd') . '-' . random_int(100, 999),
                'kind' => $d['kind'] ?? null, 'source' => $d['source'] ?? null,
                'usd_total' => (float) ($d['usd_total'] ?? 0), 'rate' => (float) ($d['rate'] ?? 0),
                'cost_lyd' => round((float) ($d['cost_lyd'] ?? 0), 2), 'extra_lyd' => round((float) ($d['extra_lyd'] ?? 0), 2),
                'sale_lyd' => round((float) ($d['sale_lyd'] ?? 0), 2), 'wallet_id' => $w->id,
            ] + (\Illuminate\Support\Facades\Schema::hasColumn('trade_invoices', 'qty') ? ['qty' => max(1, (int) ($d['qty'] ?? 1))] : []) + [
                'customer_id' => $d['customer_id'] ?? null, 'note' => $d['note'] ?? null, 'created_id' => auth()->id(),
            ]);
            foreach ($shares as $s) {
                $amt = round((float) ($s['paid'] ?? 0), 2);
                if ($amt <= 0.009 || empty($s['partner_id'])) {
                    continue;
                }
                $p = Partner::findOrFail($s['partner_id']);
                $src = (string) ($s['source'] ?? 'balance');
                $label = 'من رصيده';
                if ($src === 'cash') {
                    static::move('deposit', $w, $amt, $p, $amt, $inv, 'دفع لفاتورة ' . $inv->code);
                    $label = 'دفع توا';
                } elseif (str_starts_with($src, 'main:')) {
                    $pid = (int) substr($src, 5);
                    $kind = trim((string) ($s['kind'] ?? ''));
                    $mv = static::move('company_in', $w, $amt, $p, $amt, $inv, trim('من خزينة الشركة' . ($kind ? ' · ' . $kind : '') . ' · فاتورة ' . $inv->code));
                    TreasuryService::withdraw($amt, 'dinar', $pid, 'trade', $mv->id, 'تجارة خارجية · فاتورة ' . $inv->code . ($kind ? ' · ' . $kind : ''));
                    $label = 'خزينة الشركة · ' . (optional(\App\Models\PaymentMethod::find($pid))->name ?: '') . ($kind ? ' · ' . $kind : '');
                } elseif ((float) $p->fresh()->balance + 0.009 < $amt) {
                    throw new RuntimeException('رصيد ' . $p->name . ' (' . TreasuryService::fmt($p->balance) . ') ما يكفيش لـ' . TreasuryService::fmt($amt) . ' — اختار «دفع توا»');
                }
                static::move('invest', null, 0, $p, -$amt, $inv, 'مساهمة في فاتورة ' . $inv->code);
                Share::create(['invoice_id' => $inv->id, 'partner_id' => $p->id, 'paid' => $amt, 'source' => $label]);
            }
            $sum = round(Share::where('invoice_id', $inv->id)->sum('paid'), 2);
            if (abs($sum - $inv->totalCost()) > 0.01) {
                throw new RuntimeException('مجموع اللي دفعوه الشركاء (' . TreasuryService::fmt($sum) . ') لازم يساوي تكلفة الفاتورة كاملة (' . TreasuryService::fmt($inv->totalCost()) . ')');
            }
            // 🛒 دفع الفاتورة نفسها من خزينة التجارة
            if ($inv->totalCost() > 0.009) {
                static::move('purchase', $w, -$inv->totalCost(), null, 0, $inv, 'دفع فاتورة ' . $inv->code . ($inv->source ? ' · ' . $inv->source : ''));
            }

            if ($inv->customer_id && (float) $inv->sale_lyd > 0.009) {
                static::makeOrder($inv);
            }

            return $inv;
        });
    }

    /** 📦 طلبية للعميل في حسابه (عادي كأنها من الشركة) — مربوطة بالفاتورة وفلوسها تمشي لخزينة التجارة */
    public static function makeOrder(Invoice $inv): ?\App\Models\Order
    {
        if ($inv->order_id || !$inv->customer_id || (float) $inv->sale_lyd <= 0.009 || !\Illuminate\Support\Facades\Schema::hasColumn('orders', 'trade_invoice_id')) {
            return null;
        }
        $src = mb_strtolower((string) $inv->source);
        $platform = $src !== '' ? \App\Models\Platform::whereRaw('LOWER(name) LIKE ?', ['%' . $src . '%'])->first() : null;
        $platform = $platform ?: \App\Models\Platform::first();
        $o = new \App\Models\Order;
        $o->code = \App\Services\OrderNumber::next();
        $o->customer_id = $inv->customer_id;
        $o->platform_id = optional($platform)->id;
        $o->qty = max(1, (int) ($inv->qty ?? 1));
        $o->total_price = round((float) $inv->sale_lyd, 2);
        $o->paid_price = 0;
        $o->status = 'unpaid';
        $o->currency = 'dinar';
        $o->currency_sign = 'د.ل';
        $o->payment_id = AppSettings::cashPaymentId();
        $o->note = trim(($inv->kind ? $inv->kind . ' · ' : '') . ($inv->source ?: ''));
        $o->created_id = auth()->id();
        $o->trade_invoice_id = $inv->id;
        $o->save();
        $inv->order_id = $o->id;
        $inv->save();
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' طلبية تجارة خارجية (' . $o->code . ')', 'log' => ' ' . $inv->code . ' · ' . TreasuryService::fmt($o->total_price) . ' د.ل']);

        return $o;
    }

    /** خزينة التجارة المناسبة لطريقة الدفع (كاش ← كاش التجارة · غيره ← مصرف التجارة) */
    public static function walletFor(int $paymentId): Wallet
    {
        $kind = $paymentId === AppSettings::cashPaymentId() ? 'cash' : 'bank';

        return Wallet::where('kind', $kind)->orderBy('id')->first() ?: Wallet::orderBy('id')->firstOrFail();
    }

    /** 🔁 دفعة على طلبية تجارة من أي مكان في المنظومة (التسليم، الإيصال…) — تدخل خزينة التجارة بدل خزينة الشركة */
    public static function orderPaid(\App\Models\Order $o, float $amt, int $paymentId, string $label): void
    {
        $inv = Invoice::find($o->trade_invoice_id);
        if (!$inv) {
            return;
        }
        static::move('customer_pay', static::walletFor($paymentId), $amt, null, 0, $inv, trim($label . ' · ' . $o->code));
        $inv->collected = round((float) $inv->collected + $amt, 2);
        $inv->save();
    }

    /** 💵 سداد من العميل (دفعات) يدخل خزينة التجارة */
    public static function customerPay(Invoice $inv, Wallet $w, float $amt, ?string $note = null): void
    {
        DB::transaction(function () use ($inv, $w, $amt, $note) {
            static::move('customer_pay', $w, $amt, null, 0, $inv, trim('سداد العميل · ' . $inv->code . ($note ? ' · ' . $note : '')));
            $inv->collected = round((float) $inv->collected + $amt, 2);
            $inv->save();
            $o = $inv->order_id ? \App\Models\Order::find($inv->order_id) : null;
            if ($o) {
                // يتسجل على طلبية العميل (يشوفها في حسابه) — بدون خزينة الشركة
                $ph = new \App\Models\PaidHistory;
                $ph->customer_id = $o->customer_id;
                $ph->order_id = $o->id;
                $ph->paid_price = $amt;
                $ph->payment_id = $w->kind === 'cash' ? AppSettings::cashPaymentId() : (\App\Models\PaymentMethod::where('id', '!=', AppSettings::cashPaymentId())->where('status', true)->value('id') ?: AppSettings::cashPaymentId());
                if (\Illuminate\Support\Facades\Schema::hasColumn('paid_histories', 'note')) {
                    $ph->note = 'تجارة خارجية · ' . $w->name;
                }
                $ph->save();
                $o->paid_price = round((float) $o->paid_price + $amt, 2);
                $o->refreshPaymentStatus();
                $o->save();
            }
        });
    }

    /**
     * 🔒 إقفال الفاتورة: الربح = اللي سدده العميل − التكلفة كاملة.
     * كل شريك يرجعله اللي دفعه + نصيبه من الربح (أو الخسارة) على قد اللي دفعه.
     */
    public static function close(Invoice $inv): float
    {
        return DB::transaction(function () use ($inv) {
            $inv = Invoice::with('shares.partner')->lockForUpdate()->findOrFail($inv->id);
            if ($inv->status === 'closed') {
                throw new RuntimeException('الفاتورة مقفولة من قبل');
            }
            $base = $inv->paidByPartners();
            if ($base <= 0.009) {
                throw new RuntimeException('ما فيش مساهمات شركاء في الفاتورة');
            }
            $profit = round((float) $inv->collected - $inv->totalCost(), 2);
            $left = $profit;
            $n = $inv->shares->count();
            foreach ($inv->shares->values() as $i => $s) {
                $part = $i === $n - 1 ? $left : round($profit * (float) $s->paid / $base, 2);
                $left = round($left - $part, 2);
                static::move('invest', null, 0, $s->partner, (float) $s->paid, $inv, 'رجوع مساهمته · ' . $inv->code);
                if (abs($part) > 0.009) {
                    static::move($part > 0 ? 'profit' : 'loss', null, 0, $s->partner, $part, $inv, ($part > 0 ? 'ربح' : 'خسارة') . ' فاتورة ' . $inv->code . ' (' . round((float) $s->paid / $base * 100, 1) . '%)');
                }
                $s->profit = $part;
                $s->save();
            }
            $inv->profit = $profit;
            $inv->status = 'closed';
            $inv->closed_at = now();
            $inv->save();

            return $profit;
        });
    }
}
