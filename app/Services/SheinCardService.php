<?php

namespace App\Services;

use App\Models\CurrencyExchange;
use App\Models\Log;
use App\Models\Order;
use App\Models\SheinCard;
use App\Models\SheinCardUse;
use App\Models\TripPurchase;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * بطاقات SHEIN:
 *  - شراء: البطاقات تُسجَّل والمبلغ يُصرف من الخزينة. تكلفة كل بطاقة بالدينار تتحفظ (للربح).
 *  - استخدام على رحلة: رصيد البطاقة ينقص، والرحلة تأخذ تكلفة البطاقة الحقيقية بدل سعر الصرف.
 *    (الجزء المدفوع بالبطاقة لا يظهر من الخزينة مرة ثانية)
 *  - استخدام على إيصال بدون رحلة: يُسجَّل على الإيصال ويتحسب تكلفة بضاعة.
 *  - انتهت / خطأ: الرصيد الباقي يُحسب خسارة.
 */
class SheinCardService
{
    /** يفك سطور البطاقات: "الكود" أو "الكود الرقم_السري" أو "الكود,الرقم_السري,القيمة" */
    public static function parseLines(string $text): Collection
    {
        return collect(preg_split('/\r?\n/', $text))
            ->map(fn ($l) => trim($l))->filter()
            ->map(function ($l) {
                $p = array_values(array_filter(preg_split('/[\s,;|،]+/u', $l), fn ($x) => $x !== ''));

                return ['card_no' => $p[0] ?? '', 'pin' => $p[1] ?? null, 'face' => isset($p[2]) && is_numeric($p[2]) ? (float) $p[2] : null];
            })
            ->filter(fn ($r) => $r['card_no'] !== '')->values();
    }

    /** سعر الدولار الافتراضي لتحويل التكلفة لدينار إذا دفعنا بالدولار */
    public static function defaultRate(): float
    {
        return (float) (CurrencyExchange::latestRate() ?: (TripPurchase::latest('purchased_at')->value('rate') ?: 1));
    }

    /** التحقق من صيغة البطاقات: عدد أرقام البطاقة والرمز كما في الإعدادات، ومنع المكرر */
    public static function validateFormat(Collection $cards): void
    {
        $cd = (int) AppSettings::get('shein_card_digits', 16);
        $pd = (int) AppSettings::get('shein_pin_digits', 4);
        if ($cards->isEmpty()) {
            throw new RuntimeException('أدخل بطاقة واحدة على الأقل');
        }
        foreach ($cards as $c) {
            if (!ctype_digit((string) $c['card_no']) || strlen((string) $c['card_no']) !== $cd) {
                throw new RuntimeException('رقم البطاقة ' . $c['card_no'] . ' يجب أن يكون ' . $cd . ' رقماً بالضبط');
            }
            if (!$c['pin'] || !ctype_digit((string) $c['pin']) || strlen((string) $c['pin']) !== $pd) {
                throw new RuntimeException('رمز البطاقة ' . $c['card_no'] . ' يجب أن يكون ' . $pd . ' أرقام');
            }
        }
        $dupes = SheinCard::whereIn('card_no', $cards->pluck('card_no'))->pluck('card_no');
        if ($dupes->count()) {
            throw new RuntimeException('البطاقة موجودة مسبقاً في النظام: ' . $dupes->implode('، '));
        }
    }

    /**
     * شراء مجموعة بطاقات.
     * $cards: [['card_no','pin','face'], ...]  — القيمة لكل بطاقة إذا غير مكتوبة تأخذ $face
     * $cost: إجمالي الذي دفعناه في الكل، يتوزع على البطاقات حسب قيمتها
     */
    public static function buy(Collection $cards, float $face, float $cost, string $currency, ?int $paymentId, array $meta = []): Collection
    {
        if ($cards->isEmpty()) {
            throw new RuntimeException('اكتب كود بطاقة واحدة على الأقل');
        }
        $dupes = SheinCard::whereIn('card_no', $cards->pluck('card_no'))->pluck('card_no');
        if ($dupes->count()) {
            throw new RuntimeException('البطاقات هذه مسجلة قبل: ' . $dupes->implode('، '));
        }
        if ($cards->pluck('card_no')->duplicates()->count()) {
            throw new RuntimeException('في كود مكرر في القائمة: ' . $cards->pluck('card_no')->duplicates()->implode('، '));
        }
        $cards = $cards->map(fn ($c) => $c + ['value' => $c['face'] ?: $face]);
        if ($cards->contains(fn ($c) => $c['value'] <= 0)) {
            throw new RuntimeException('اكتب قيمة البطاقة بالدولار');
        }
        $totalFace = $cards->sum('value');
        $rate = $currency === 'dollar' ? (float) ($meta['rate'] ?? static::defaultRate()) : 1;
        $costDinar = $currency === 'dinar' ? $cost : ($currency === 'dollar' ? $cost * $rate : $cost * $rate);
        $batch = $cards->count() > 1 ? 'S' . now()->format('ymdHi') : null;
        $made = collect();
        foreach ($cards as $c) {
            $share = $totalFace > 0 ? $c['value'] / $totalFace : 0;
            $made->push(SheinCard::create([
                'card_no' => $c['card_no'], 'pin' => $c['pin'] ?: null, 'face_value' => $c['value'], 'balance' => $c['value'],
                'cost' => round($cost * $share, 2), 'cost_currency' => $currency, 'cost_dinar' => round($costDinar * $share, 2),
                'payment_id' => $paymentId, 'supplier' => $meta['supplier'] ?? null, 'batch' => $batch, 'status' => 'new',
                'account' => $meta['account'] ?? null, 'purchased_at' => $meta['purchased_at'] ?? now()->toDateString(),
                'expires_at' => $meta['expires_at'] ?? null, 'note' => $meta['note'] ?? null, 'created_id' => auth()->id(),
            ]));
        }
        if ($cost > 0 && $paymentId) {
            TreasuryService::withdraw($cost, $currency, $paymentId, 'shein_card', $made->first()->id,
                'شراء ' . $made->count() . ' بطاقة SHEIN بقيمة ' . TreasuryService::fmt($totalFace) . ' $' . (!empty($meta['supplier']) ? ' من ' . $meta['supplier'] : ''));
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' شراء بطاقات SHEIN', 'log' => ' ' . $made->count() . ' بطاقة بقيمة ' . $totalFace . ' $ بتكلفة ' . $cost . ' ' . TreasuryService::sign($currency)]);

        return $made;
    }

    /**
     * استخدام بطاقة على رحلة و/أو إيصال.
     * $refundTrip: الرحلة كانت مخصومة كاملة من الخزينة → إرجاع الجزء الذي دُفع بالبطاقة
     */
    public static function use(SheinCard $card, float $amount, ?TripPurchase $trip, ?Order $order, ?string $note = null, bool $refundTrip = true): SheinCardUse
    {
        $amount = round($amount, 2);
        if (!$card->isUsable()) {
            throw new RuntimeException('البطاقة ' . $card->masked() . ' غير صالحة للاستخدام (' . $card->statusLabel() . ')');
        }
        if ($amount <= 0 || $amount > $card->balance + 0.009) {
            throw new RuntimeException('رصيد البطاقة ' . $card->masked() . ' هو ' . TreasuryService::fmt($card->balance) . ' $ فقط');
        }
        if (!$trip && $order && $order->trip_details) {
            $trip = TripPurchase::where('trip_code', $order->trip_details)->first();
        }
        if ($trip) {
            if ($trip->cost_currency !== 'dollar') {
                throw new RuntimeException('الرحلة ' . $trip->trip_code . ' مسجلة بالدينار، البطاقات للرحلات بالدولار فقط');
            }
            if ((float) $trip->cards_amount + $amount > (float) $trip->cost + 0.009) {
                throw new RuntimeException('تكلفة الرحلة ' . $trip->trip_code . ' هي ' . TreasuryService::fmt($trip->cost) . ' $، والبطاقات لا تزيد عليها');
            }
        }
        $costDinar = round($amount * $card->costPerDollar(), 2);
        $use = SheinCardUse::create([
            'card_id' => $card->id, 'trip_purchase_id' => optional($trip)->id, 'order_id' => optional($order)->id,
            'amount' => $amount, 'cost_dinar' => $costDinar, 'note' => $note, 'created_id' => auth()->id(),
        ]);
        $card->balance = round($card->balance - $amount, 2);
        if (!$card->redeemed_at) {
            $card->redeemed_at = now();
        }
        $card->refreshStatus();
        $card->save();
        if ($trip) {
            $trip->cards_amount = round((float) $trip->cards_amount + $amount, 2);
            $trip->cards_cost_dinar = round((float) $trip->cards_cost_dinar + $costDinar, 2);
            $trip->save();
            if ($refundTrip && $trip->payment_id) {
                [$tAmt, $tCur] = $trip->treasuryCharge($amount);
                TreasuryService::deposit($tAmt, $tCur, (int) $trip->payment_id, 'trip_purchase_reverse', $trip->id, 'جزء من رحلة ' . $trip->trip_code . ' دُفع ببطاقة SHEIN ' . $card->masked());
            }
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' استخدام بطاقة SHEIN', 'log' => ' ' . $amount . ' $ من البطاقة ' . $card->masked() . ($trip ? ' على رحلة ' . $trip->trip_code : '') . ($order ? ' للإيصال ' . $order->code : '')]);

        return $use;
    }

    /** إرجاع استخدام (يرجع الرصيد للبطاقة، والرحلة ترجع تُخصم من الخزينة) */
    public static function reverse(SheinCardUse $use, bool $chargeTrip = true): void
    {
        $card = $use->card;
        $trip = $use->trip;
        $card->balance = round($card->balance + $use->amount, 2);
        if (in_array($card->status, ['used', 'partial', 'new'], true)) {
            $card->refreshStatus();
        }
        $card->save();
        if ($trip) {
            $trip->cards_amount = max(0, round((float) $trip->cards_amount - $use->amount, 2));
            $trip->cards_cost_dinar = max(0, round((float) $trip->cards_cost_dinar - $use->cost_dinar, 2));
            $trip->save();
            if ($chargeTrip && $trip->payment_id) {
                [$tAmt, $tCur] = $trip->treasuryCharge((float) $use->amount);
                TreasuryService::withdraw($tAmt, $tCur, (int) $trip->payment_id, 'trip_purchase', $trip->id, 'رجوع بطاقة SHEIN من رحلة ' . $trip->trip_code, true);
            }
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إلغاء استخدام بطاقة SHEIN', 'log' => ' رجع ' . $use->amount . ' $ للبطاقة ' . $card->masked()]);
        $use->delete();
    }

    /** البطاقة انتهت أو طلعت خطأ: الرصيد الباقي خسارة */
    public static function writeOff(SheinCard $card, string $status, ?string $note = null): void
    {
        if (!in_array($status, ['expired', 'invalid'], true)) {
            throw new RuntimeException('حالة غير معروفة');
        }
        $card->lost_dinar = round((float) $card->lost_dinar + $card->balanceDinar(), 2);
        $card->lost_at = now();
        $card->balance = 0;
        $card->status = $status;
        if ($note) {
            $card->note = trim(($card->note ? $card->note . ' · ' : '') . $note);
        }
        $card->save();
        Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' بطاقة SHEIN ' . SheinCard::STATUSES[$status][0], 'log' => ' البطاقة ' . $card->masked() . ' خسارة ' . $card->lost_dinar . ' د.ل']);
    }

    /** حذف بطاقة غير المستخدمة: المبلغ ترجع للخزينة */
    public static function delete(SheinCard $card): void
    {
        if ($card->uses()->exists()) {
            throw new RuntimeException('البطاقة مستخدمة، لا يمكن تنحذف. رجّع الاستخدامات الأول');
        }
        if ($card->cost > 0 && $card->payment_id) {
            TreasuryService::deposit((float) $card->cost, $card->cost_currency, (int) $card->payment_id, 'shein_card_reverse', $card->id, 'حذف بطاقة SHEIN ' . $card->masked());
        }
        Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' حذف بطاقة SHEIN', 'log' => ' ' . $card->masked() . ' ورجع ' . $card->cost . ' للخزينة']);
        $card->delete();
    }

    /** يخلص مبلغ من رحلة بأكثر من بطاقة بالترتيب (الأقدم والأرخص أول) */
    public static function payTrip(TripPurchase $trip, array $amounts, bool $refundTrip = true): float
    {
        $total = 0;
        foreach ($amounts as $cardId => $amount) {
            if ((float) $amount <= 0) {
                continue;
            }
            $card = SheinCard::lockForUpdate()->findOrFail($cardId);
            static::use($card, (float) $amount, $trip, null, null, $refundTrip);
            $total += (float) $amount;
        }

        return $total;
    }
}
